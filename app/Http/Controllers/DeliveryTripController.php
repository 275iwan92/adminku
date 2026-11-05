<?php

namespace App\Http\Controllers;

use App\Enums\StockDocumentType;
use App\Models\DeliveryStop;
use App\Models\DeliveryTrip;
use App\Models\StockDocument;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Text;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/* REMARK
 * Tujuan       : Pengiriman (PGK): planner satu trip untuk satu kendaraan dan satu crew. Admin menyusun urutan alamat antar / ambil, barang diketik atau diambil dari Surat Jalan (Pengeluaran Barang terkonfirmasi, boleh banyak,
 *                satu surat jalan hanya di satu stop), lalu Tugaskan (nomor lahir, trip terkunci). Batalkan (Supervisor, alasan) melepas surat jalannya lagi. Hapus hanya draf.
 *                Stop dikirim layar sebagai JSON (stops_json), divalidasi di sini.
 * Sumber hulu  : delivery_trip, delivery_stop, delivery_stop_doc, m_vehicle, rm_stock.
 * Output       : grid trip, form planner.
 * Dipakai oleh : routes/web.php (trips.*), DeliveryNoteController.
 * Status       : Aktif.
 */
class DeliveryTripController extends Controller
{
    public function index()
    {
        $rows = DeliveryTrip::query()->with('vehicle:id,plate,name')->withCount('stops')->orderByDesc('trip_date')->orderByDesc('id')->limit(1000)->get()->map(fn (DeliveryTrip $t) => [
            'id' => $t->id, 'doc_no' => $t->number(), 'trip_date' => $t->trip_date->format('Y-m-d'), 'vehicle' => $t->vehicle->plate, 'driver' => $t->driver,
            'crew' => trim($t->driver.($t->assistant ? ' + '.$t->assistant : '')), 'stops' => $t->stops_count, 'state' => $t->statusLabel(),
        ])->all();

        return view('delivery-trips.index', ['rows' => $rows]);
    }

    public function create()
    {
        return view('delivery-trips.form', $this->formData(new DeliveryTrip(['trip_date' => today(), 'status' => 'draft', 'depart_time' => '08:00', 'return_min' => 25, 'driver' => ''])));
    }

    public function show(DeliveryTrip $trip)
    {
        return view('delivery-trips.form', $this->formData($trip->load(['stops.docs', 'creator'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $trip = DB::transaction(function () use ($data, $request) {
            $trip = DeliveryTrip::create($this->header($data) + ['status' => 'draft', 'created_by' => $request->user()->id]);
            $this->saveStops($trip, $data['stops']);

            return $trip;
        });

        return redirect()->route('trips.show', $trip)->with('status', 'Draf trip disimpan. Nomor dibuat saat Tugaskan.');
    }

    // Siklus: save, confirm (Tugaskan), cancel (Supervisor, alasan), delete (draf).
    public function update(Request $request, DeliveryTrip $trip): RedirectResponse
    {
        $action = $request->input('_action', 'save');
        $user = $request->user();

        $message = DB::transaction(function () use ($action, $request, $trip, $user) {
            $trip = DeliveryTrip::query()->lockForUpdate()->findOrFail($trip->id);
            $need = fn (array $from) => abort_unless(in_array($trip->status, $from, true), 422, 'Status trip sudah berubah. Muat ulang halaman.');

            switch ($action) {
                case 'save':
                case 'confirm':
                    $need(['draft']);
                    abort_unless($user->id === $trip->created_by || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang mengubah draf ini.');
                    $data = $this->validated($request, $action === 'confirm', $trip);
                    $trip->update($this->header($data));
                    $this->saveStops($trip, $data['stops']);
                    if ($action === 'save') {
                        return 'Draf disimpan.';
                    }
                    $trip->update(['doc_no' => $trip->doc_no ?? DeliveryTrip::nextNo($trip->trip_date), 'token' => $trip->token ?? Str::random(40), 'status' => 'assigned', 'assigned_by' => $user->id, 'assigned_at' => now()]);

                    return "{$trip->doc_no} ditugaskan ke {$trip->driver}.";
                case 'cancel':
                    $need(['assigned']);
                    abort_unless($user->isSupervisor(), 403, 'Hanya Supervisor yang membatalkan trip.');
                    $reason = Text::of($request->input('_reason'));
                    abort_if(mb_strlen($reason) < 3, 422, 'Isi alasan pembatalan (minimal 3 huruf).');
                    DB::table('delivery_stop_doc')->whereIn('stop_id', $trip->stops()->pluck('id'))->delete();   // surat jalan bebas dipakai lagi
                    $trip->update(['status' => 'cancelled', 'reason' => $reason]);

                    return "{$trip->doc_no} dibatalkan; surat jalannya bebas lagi.";
                case 'delete':
                    $need(['draft']);
                    abort_unless($user->id === $trip->created_by || $user->isSupervisor(), 403, 'Hanya pembuat atau Supervisor yang menghapus draf ini.');
                    $trip->delete();

                    return 'Draf trip dihapus.';
                default:
                    abort(422, 'Aksi tidak dikenal.');
            }
        });

        return $action === 'delete' ? redirect()->route('trips.index')->with('status', $message) : redirect()->route('trips.show', $trip)->with('status', $message);
    }

    private function header(array $data): array
    {
        return [
            'trip_date' => $data['trip_date'], 'vehicle_id' => $data['vehicle_id'], 'driver' => Text::of($data['driver']), 'assistant' => Text::of($data['assistant'] ?? '') ?: null,
            'depart_time' => $data['depart_time'], 'return_min' => $data['return_min'] ?? 25, 'note' => Text::of($data['note'] ?? '') ?: null,
        ];
    }

    // Header + stops (stops_json). Tugaskan (confirm) mewajibkan minimal satu stop dengan barang, dan surat jalan terkonfirmasi yang belum dipakai stop lain.
    private function validated(Request $request, bool $confirm = false, ?DeliveryTrip $trip = null): array
    {
        $data = $request->validate([
            'trip_date' => ['required', 'date'],
            'vehicle_id' => ['required', 'exists:m_vehicle,id'],
            'driver' => ['required', 'string', 'max:100'],
            'assistant' => ['nullable', 'string', 'max:100'],
            'depart_time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'return_min' => ['nullable', 'integer', 'min:0', 'max:600'],
            'note' => ['nullable', 'string', 'max:255'],
            'stops_json' => ['nullable', 'string'],
        ], ['trip_date.required' => 'Isi tanggal trip.', 'vehicle_id.required' => 'Pilih kendaraan.', 'driver.required' => 'Isi nama sopir.', 'depart_time.regex' => 'Jam berangkat harus berbentuk JJ:MM.']);

        $raw = json_decode($data['stops_json'] ?? '[]', true);
        if (! is_array($raw)) {
            throw ValidationException::withMessages(['stops_json' => 'Data alamat tidak terbaca. Muat ulang halaman.']);
        }
        if ($confirm && ! $raw) {
            throw ValidationException::withMessages(['stops_json' => 'Tambahkan minimal satu alamat sebelum Tugaskan.']);
        }
        $usedHere = [];
        $stops = [];
        foreach (array_values($raw) as $i => $s) {
            $n = $i + 1;
            $kind = ($s['kind'] ?? '') === 'amb' ? 'amb' : 'ant';
            $customer = Text::of($s['customer'] ?? '');
            if ($customer === '') {
                throw ValidationException::withMessages(['stops_json' => "Alamat {$n}: isi nama pelanggan."]);
            }
            $docs = $kind === 'ant' ? array_values(array_unique(array_map('intval', (array) ($s['docs'] ?? [])))) : [];
            $items = [];
            foreach ((array) ($s['items'] ?? []) as $r) {
                $name = Text::of($r[0] ?? '');
                if ($name === '') {
                    continue;
                }
                $items[] = [$name, max(0, (float) ($r[1] ?? 0)), Text::of($r[2] ?? '') ?: 'pcs', max(0, (float) ($r[3] ?? 0))];
            }
            if ($docs) {
                $items = [];   // barang dari surat jalan, bukan ketikan
                $ok = StockDocument::query()->where('type', StockDocumentType::Issuance)->where('status', 'confirmed')->whereIn('id', $docs)->count();
                abort_if($ok !== count($docs), 422, "Alamat {$n}: surat jalan harus Pengeluaran Barang yang Terkonfirmasi.");
                if (array_intersect($docs, $usedHere)) {
                    throw ValidationException::withMessages(['stops_json' => "Alamat {$n}: surat jalan sudah dipakai alamat lain di trip ini."]);
                }
                $taken = DB::table('delivery_stop_doc as d')->join('delivery_stop as s', 's.id', '=', 'd.stop_id')->whereIn('d.stock_document_id', $docs)
                    ->when($trip?->exists, fn ($q) => $q->where('s.trip_id', '!=', $trip->id))->exists();
                if ($taken) {
                    throw ValidationException::withMessages(['stops_json' => "Alamat {$n}: surat jalan sudah dipakai trip lain."]);
                }
                $usedHere = array_merge($usedHere, $docs);
            } elseif ($confirm && ! $items) {
                throw ValidationException::withMessages(['stops_json' => "Alamat {$n} ({$customer}): isi barang atau pilih surat jalan."]);
            }
            $stops[] = ['kind' => $kind, 'customer' => $customer, 'address' => Text::of($s['address'] ?? '') ?: null, 'travel_min' => min(600, max(0, (int) ($s['travel_min'] ?? 15))), 'lat' => self::coord($s['lat'] ?? null, 90), 'lng' => self::coord($s['lng'] ?? null, 180), 'items' => $items, 'docs' => $docs];
        }
        $data['stops'] = $stops;

        return $data;
    }

    // Koordinat peta: angka dalam rentang, selain itu kosong (alamat tanpa titik tidak digambar).
    private static function coord(mixed $v, int $max): ?float
    {
        return is_numeric($v) && abs((float) $v) <= $max ? round((float) $v, 6) : null;
    }

    private function saveStops(DeliveryTrip $trip, array $stops): void
    {
        DB::table('delivery_stop_doc')->whereIn('stop_id', $trip->stops()->pluck('id'))->delete();
        $trip->stops()->delete();
        foreach ($stops as $i => $s) {
            $stop = $trip->stops()->create(['seq' => $i + 1, 'kind' => $s['kind'], 'customer' => $s['customer'], 'address' => $s['address'], 'travel_min' => $s['travel_min'], 'lat' => $s['lat'], 'lng' => $s['lng'], 'items' => $s['items']]);
            $stop->docs()->sync($s['docs']);
        }
    }

    private function formData(DeliveryTrip $trip): array
    {
        $mine = $trip->exists ? $trip->stops->flatMap(fn ($s) => $s->docs->pluck('id'))->all() : [];
        $docs = StockDocument::query()->where('type', StockDocumentType::Issuance)->where('status', 'confirmed')
            ->where(fn ($q) => $q->whereNotIn('id', DB::table('delivery_stop_doc')->select('stock_document_id'))->orWhereIn('id', $mine))
            ->with('lines.batch:id,name,unit')->orderByDesc('doc_at')->limit(200)->get()
            ->map(fn (StockDocument $d) => ['id' => $d->id, 'no' => $d->doc_no, 'cu' => $d->recipient ?? '', 'it' => $d->lines->map(fn ($l) => [$l->batch->name, (float) $l->qty, $l->batch->unit, 0])->all()])->all();

        $stops = $trip->exists ? $trip->stops->map(fn (DeliveryStop $s) => [
            'kind' => $s->kind, 'customer' => $s->customer, 'address' => $s->address ?? '', 'arr' => $s->arrived_at ? $s->arrived_at->hour * 60 + $s->arrived_at->minute : null, 'travel_min' => $s->travel_min, 'lat' => $s->lat !== null ? (float) $s->lat : null, 'lng' => $s->lng !== null ? (float) $s->lng : null, 'items' => $s->items ?? [], 'docs' => $s->docs->pluck('id')->all(),
        ])->all() : [];

        $log = [];
        if ($trip->exists) {
            $log[] = ['at' => $trip->created_at->format('Y-m-d H:i:s'), 'by' => $trip->creator->name, 'action' => 'Dibuat', 'note' => null];
            if ($trip->assigned_at) {
                $log[] = ['at' => $trip->assigned_at->format('Y-m-d H:i:s'), 'by' => User::find($trip->assigned_by)?->name ?? '-', 'action' => 'Ditugaskan', 'note' => $trip->driver];
            }
            if ($trip->status === 'cancelled') {
                $log[] = ['at' => $trip->updated_at->format('Y-m-d H:i:s'), 'by' => '-', 'action' => 'Dibatalkan', 'note' => $trip->reason];
            }
        }

        return [
            'trip' => $trip, 'office' => config('sstock.office_geo'), 'stops' => old('stops_json') ? (json_decode(old('stops_json'), true) ?: []) : $stops, 'docs' => $docs, 'log' => $log,
            'vehicles' => Vehicle::query()->where(fn ($q) => $q->where('is_active', true)->orWhere('id', $trip->vehicle_id))->orderBy('plate')->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label()])->all(),
        ];
    }
}
