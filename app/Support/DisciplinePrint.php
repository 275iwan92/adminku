<?php

namespace App\Support;

use App\Models\Discipline;
use Devlib\Locale;

/* REMARK
 * Tujuan       : Data cetakan surat SP 1 / SP 2 / SP 3 / PHK untuk x-dl::print-sheet (A4 potret): kop perusahaan (otomatis), judul surat, nomor, identitas karyawan (NIK disamarkan), pelanggaran, ketentuan masa berlaku, tanda tangan penerbit dan karyawan.
 * Sumber hulu  : Discipline (employee.department), Company (kop lewat DevLib).
 * Output       : array prop untuk x-dl::print-sheet (title, code, info, sections, signatures, note, landscape).
 * Dipakai oleh : DisciplineController@print (disciplines.print).
 * Status       : Aktif.
 */
final class DisciplinePrint
{
    /** @return array<string, mixed> */
    public function letter(Discipline $d): array
    {
        $d->loadMissing('employee.department');
        $e = $d->employee;
        $nik = $e->nik_ktp ? substr($e->nik_ktp, 0, 4).' **** **** '.substr($e->nik_ktp, -4) : '-';
        $isPhk = $d->type === 'phk';
        $previous = Discipline::query()->where('employee_id', $e->id)->where('id', '!=', $d->id)->where('status', 'issued')->where('issued_on', '<=', $d->issued_on)->whereIn('type', ['sp1', 'sp2', 'sp3'])->orderByDesc('issued_on')->orderByDesc('id')->first();

        $blocks = [['type' => 'text', 'text' => 'telah melakukan pelanggaran berupa: '.$d->reason.($d->rule_ref ? ' ('.$d->rule_ref.')' : '').'.'.($previous ? ' Sebelumnya yang bersangkutan telah menerima '.Discipline::LETTERS[$previous->type].' No. '.$previous->doc_no.'.' : '')]];
        $blocks[] = ['type' => 'text', 'text' => $isPhk
            ? 'Dengan surat ini perusahaan memutuskan hubungan kerja dengan yang bersangkutan, berlaku efektif pada tanggal '.Locale::d($d->until_on).'. Hak dan kewajiban yang tersisa diselesaikan sesuai peraturan perusahaan dan ketentuan yang berlaku.'
            : Discipline::LETTERS[$d->type].' ini berlaku '.Discipline::VALID_MONTHS.' bulan, sejak '.Locale::d($d->issued_on).' sampai '.Locale::d($d->until_on).'. Apabila pelanggaran terulang, perusahaan akan menerbitkan surat peringatan berikutnya atau mengambil tindakan lebih lanjut sesuai peraturan perusahaan.'];

        return [
            'title' => Discipline::LETTERS[$d->type], 'code' => $d->doc_no, 'landscape' => false,
            'info' => ['Yang bertanda tangan di bawah ini menerangkan bahwa:', 'Nama: '.$e->name.' ('.$e->code.')', 'NIK KTP: '.$nik, 'Jabatan: '.($e->position ?: '-').' · Bagian '.$e->department->name, 'Tanggal terbit: '.Locale::d($d->issued_on)],
            'sections' => [['title' => $isPhk ? 'Keputusan' : 'Pelanggaran dan ketentuan', 'blocks' => $blocks]],
            'note' => $d->status === 'revoked' ? 'SURAT DICABUT: dokumen ini tidak berlaku.' : null,
            'signatures' => [['role' => $d->signer_title, 'name' => $d->signer_name], ['role' => 'Karyawan yang bersangkutan', 'name' => $e->name]],
        ];
    }
}
