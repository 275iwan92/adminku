<?php

namespace App\Services;

use App\Mail\AcVisitNotice;
use App\Models\AcOrder;
use App\Models\AcOrderLine;
use App\Models\AcVendor;
use App\Models\User;
use App\Support\Company;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/* REMARK
 * Tujuan       : Siklus pesanan kunjungan vendor AC dengan urutan tahap yang DITEGAKKAN (tidak bisa melompat): Draf atau Dijadwal ulang > Kirim > Terkirim > Dikonfirmasi vendor > Selesai.
 *                Jadwal ulang (dengan alasan; tanggal / jam baru dicek hari, jam, dan kapasitas; riwayat tersimpan) dan Batal bisa dari tahap mana pun sebelum Selesai. Kirim = e-mail ke vendor (Mailable) + status Terkirim
 *                (waktu dan pengirim dicatat); WhatsApp dibuka petugas lewat tautan wa.me terisi (belum ada WhatsApp Cloud API). Pesan salinan PIC hanya disiapkan (siap kirim sehari sebelumnya pukul 15.00), tidak terkirim otomatis.
 *                Selesai mencatat tanggal tiap AC sehingga jatuh tempo berikutnya terhitung. Foto sebelum / sesudah per AC opsional (jpg / png, disk privat "ga").
 * Sumber hulu  : g_ac_service_order / line / reschedule, AcCare, AcMessage, Company.
 * Output       : noticeData(), vendorMessage(), picNotices(), send(), confirm(), complete(), reschedule(), cancel(), attachPhoto().
 * Dipakai oleh : AcVisitController, AcSeeder.
 * Status       : Aktif.
 */
final class AcVisits
{
    // ---- Pesan ------------------------------------------------------------------------------------------------------------------

    /** Data pesan dari pesanan (butuh vendor dan lines.unit.room.area). */
    public static function noticeData(AcOrder $order, string $sender): array
    {
        $order->loadMissing('vendor', 'lines.unit.room.area');

        return [
            'no' => $order->no, 'date' => $order->visit_date, 'time' => $order->timeText(), 'company' => Company::get()['name'], 'sender' => $sender,
            'greeting' => $order->vendor->contact_name ?: $order->vendor->name, 'vendor' => $order->vendor->name,
            'lines' => $order->lines->sortBy(fn ($l) => $l->unit->room->area->name.'|'.$l->unit->room->code.'|'.$l->unit->asset_code)->map(fn (AcOrderLine $l) => [
                'area' => $l->unit->room->area->name, 'room' => $l->unit->room->name, 'room_code' => $l->unit->room->code, 'asset_code' => $l->unit->asset_code,
                'type' => $l->unit->typeText(), 'job' => AcCare::JOBS[$l->job], 'pic' => $l->unit->room->pic_name,
                'pic_phone' => $l->unit->room->pic_phone, 'pic_email' => $l->unit->room->pic_email,
            ])->values()->all(),
        ];
    }

    /** Pesan vendor + tautan WhatsApp (null bila nomor vendor kosong / tidak sah). @return array{subject: string, text: string, wa: ?string} */
    public static function vendorMessage(AcOrder $order, string $sender): array
    {
        $d = self::noticeData($order, $sender);
        $text = AcMessage::vendor($d);

        return ['subject' => AcMessage::subject($d), 'text' => $text, 'wa' => AcMessage::waLink($order->vendor->phone, $text)];
    }

    /**
     * Pesan salinan per PIC (ruang dengan PIC yang sama digabung) dan daftar AC yang ruangnya belum punya PIC. Waktu kirim yang disarankan = sehari sebelum kunjungan pukul 15.00.
     *
     * @return array{notices: list<array>, missing: list<string>, send_at: Carbon}
     */
    public static function picNotices(AcOrder $order, string $sender): array
    {
        $d = self::noticeData($order, $sender);
        $groups = [];
        $missing = [];
        foreach ($d['lines'] as $l) {
            if (! $l['pic'] && ! $l['pic_phone']) {
                $missing[] = $l['room'].' ('.$l['room_code'].')';

                continue;
            }
            $groups[($l['pic'] ?? '').'|'.($l['pic_phone'] ?? '')][] = $l;
        }
        $notices = [];
        foreach ($groups as $lines) {
            $pic = $lines[0]['pic'] ?: 'Bapak/Ibu PIC';
            $text = AcMessage::pic(['pic' => $pic, 'lines' => $lines] + $d);
            $notices[] = ['pic' => $pic, 'phone' => $lines[0]['pic_phone'], 'email' => $lines[0]['pic_email'], 'count' => count($lines), 'text' => $text, 'wa' => AcMessage::waLink($lines[0]['pic_phone'], $text)];
        }
        [$h, $m] = array_map('intval', explode(':', AcCare::picNoticeTime()));

        return ['notices' => $notices, 'missing' => array_values(array_unique($missing)), 'send_at' => $order->visit_date->copy()->subDay()->setTime($h, $m)];
    }

    // ---- Siklus -----------------------------------------------------------------------------------------------------------------

    /**
     * Kirim pemberitahuan: e-mail ke vendor (bila ada alamatnya) lalu status Terkirim. Boleh diulang dari Terkirim (kirim ulang). Gagal kirim e-mail = status tidak berubah.
     *
     * @return array{email: ?string, wa: bool} e-mail tujuan (null bila vendor tanpa e-mail) dan apakah tautan WhatsApp tersedia
     */
    public static function send(AcOrder $order, User $by): array
    {
        return DB::transaction(function () use ($order, $by) {
            $o = self::lock($order, ['draft', 'rescheduled', 'sent'], 'Kirim');
            $o->load('vendor');
            if (! $o->lines()->exists()) {
                throw ValidationException::withMessages(['status' => 'Pesanan '.$o->no.' belum berisi AC.']);
            }
            if ($o->visit_date->lt(today())) {
                throw ValidationException::withMessages(['status' => 'Tanggal kunjungan '.$o->visit_date->format('d-m-Y').' sudah lewat. Jadwal ulang dulu sebelum mengirim.']);
            }
            $mail = trim((string) $o->vendor->email) ?: null;
            $wa = AcMessage::waNumber($o->vendor->phone) !== null;
            if (! $mail && ! $wa) {
                throw ValidationException::withMessages(['status' => 'Vendor '.$o->vendor->name.' belum punya e-mail maupun nomor WhatsApp yang sah. Lengkapi di Vendor AC.']);
            }
            if ($mail) {
                $m = self::vendorMessage($o, $by->name);
                try {
                    Mail::to($mail)->send(new AcVisitNotice($m['subject'], $m['text']));
                } catch (Throwable $e) {
                    Log::error('E-mail kunjungan AC gagal dikirim: '.$e->getMessage(), ['order' => $o->no, 'mailer' => config('mail.default')]);
                    throw ValidationException::withMessages(['status' => 'E-mail belum bisa dikirim saat ini. Coba lagi nanti atau hubungi Administrator.']);
                }
            }
            $o->update(['status' => 'sent', 'sent_at' => now(), 'sent_by' => $by->id]);

            return ['email' => $mail, 'wa' => $wa];
        });
    }

    public static function confirm(AcOrder $order, User $by): AcOrder
    {
        return DB::transaction(function () use ($order, $by) {
            $o = self::lock($order, ['sent'], 'Tandai dikonfirmasi vendor');
            $o->update(['status' => 'confirmed', 'confirmed_at' => now(), 'confirmed_by' => $by->id]);

            return $o;
        });
    }

    /** Tandai selesai: semua AC di pesanan dicatat dikerjakan pada $doneAt (tidak sebelum tanggal kunjungan, tidak setelah hari ini); jatuh tempo berikutnya dihitung dari tanggal itu. */
    public static function complete(AcOrder $order, string $doneAt, User $by): AcOrder
    {
        try {
            $day = Carbon::createFromFormat('Y-m-d', $doneAt)->startOfDay();
        } catch (Throwable) {
            throw ValidationException::withMessages(['done_at' => 'Tanggal selesai tidak valid.']);
        }

        return DB::transaction(function () use ($order, $day, $by) {
            $o = self::lock($order, ['confirmed'], 'Tandai selesai');
            if ($day->gt(today())) {
                throw ValidationException::withMessages(['done_at' => 'Tanggal selesai tidak boleh setelah hari ini.']);
            }
            if ($day->lt($o->visit_date)) {
                throw ValidationException::withMessages(['done_at' => 'Tanggal selesai tidak boleh sebelum tanggal kunjungan ('.$o->visit_date->format('d-m-Y').').']);
            }
            $o->lines()->update(['done_at' => $day->toDateString()]);
            $o->update(['status' => 'done', 'done_at' => now(), 'done_by' => $by->id]);

            return $o;
        });
    }

    /** Pindahkan kunjungan ke tanggal / jam baru: alasan wajib, hari / jam / kapasitas dicek seperti penjadwalan, riwayat tersimpan; status Dijadwal ulang (kirim ulang pemberitahuan). */
    public static function reschedule(AcOrder $order, string $date, ?string $time, string $reason, User $by): AcOrder
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan jadwal ulang wajib diisi (3 sampai 255 huruf).']);
        }
        $time = $time ?: AcCare::startTime();
        $day = AcCare::assertSlot($date, $time);

        return DB::transaction(function () use ($order, $day, $time, $reason, $by) {
            $vendor = AcVendor::query()->lockForUpdate()->findOrFail($order->vendor_id);   // urutan kunci sama dengan penjadwalan: vendor dulu
            $o = self::lock($order, AcOrder::OPEN, 'Jadwal ulang');
            if ($o->visit_date->isSameDay($day) && $o->timeText() === $time) {
                throw ValidationException::withMessages(['date' => 'Tanggal dan jam baru sama dengan jadwal sekarang.']);
            }
            AcCare::assertCapacity($vendor, $day, $o->lines()->count(), $o->id);
            $o->reschedules()->create(['from_date' => $o->visit_date->toDateString(), 'from_time' => $o->timeText(), 'to_date' => $day->toDateString(), 'to_time' => $time, 'reason' => $reason, 'created_by' => $by->id]);
            $o->update([
                'visit_date' => $day->toDateString(), 'visit_time' => $time, 'status' => 'rescheduled', 'reschedule_reason' => $reason,
                'sent_at' => null, 'sent_by' => null, 'confirmed_at' => null, 'confirmed_by' => null,
            ]);

            return $o;
        });
    }

    public static function cancel(AcOrder $order, string $reason, User $by): AcOrder
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 255) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan wajib diisi (3 sampai 255 huruf).']);
        }

        return DB::transaction(function () use ($order, $reason, $by) {
            $o = self::lock($order, AcOrder::OPEN, 'Batalkan');
            $o->update(['status' => 'cancelled', 'cancel_reason' => $reason, 'cancelled_at' => now(), 'cancelled_by' => $by->id]);

            return $o;
        });
    }

    /** Foto bukti sebelum / sesudah satu AC (opsional): hanya saat pesanan Dikonfirmasi atau Selesai; jpg / png sudah diperiksa pemanggil. Foto lama diganti. */
    public static function attachPhoto(AcOrderLine $line, string $kind, UploadedFile $file): AcOrderLine
    {
        if (! in_array($kind, ['before', 'after'], true)) {
            throw ValidationException::withMessages(['kind' => 'Jenis foto tidak dikenal.']);
        }
        $order = $line->order;
        if (! in_array($order->status, ['confirmed', 'done'], true)) {
            throw ValidationException::withMessages(['photo' => 'Foto hanya bisa dilampirkan setelah pesanan Dikonfirmasi vendor.']);
        }
        $path = $file->store("ac/service/{$order->id}", 'ga');
        $old = $line->{"photo_{$kind}_path"};
        try {
            $line->update(["photo_{$kind}_path" => $path, "photo_{$kind}_name" => mb_substr($file->getClientOriginalName(), 0, 150)]);
        } catch (Throwable $e) {
            Storage::disk('ga')->delete($path);
            throw $e;
        }
        $old && Storage::disk('ga')->delete($old);

        return $line;
    }

    // Kunci baris pesanan lalu pastikan tahapnya boleh; tahap lain = galat yang menyebut urutan tahap.
    private static function lock(AcOrder $order, array $allowed, string $action): AcOrder
    {
        $o = AcOrder::query()->lockForUpdate()->findOrFail($order->id);
        if (! in_array($o->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $action.' tidak bisa: pesanan '.$o->no.' berstatus '.AcCare::ORDER_STATUS[$o->status].'. Urutan tahap: Draf, Terkirim, Dikonfirmasi vendor, Selesai.']);
        }

        return $o;
    }
}
