<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPosition;
use App\Models\DeliveryStop;
use App\Models\DeliveryTrip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/* REMARK
 * Tujuan       : Layar sopir di HP tanpa login: satu trip lewat tautan rahasia (token). Sopir menandai alamat yang sudah tiba (jam asli tercatat), ETA alamat berikutnya dihitung ulang (DeliveryTrip::schedule).
 *                Hanya trip Ditugaskan yang bisa dibuka dan ditandai; trip dibatalkan menampilkan pesan. Tanda tiba boleh dibatalkan bila salah pencet.
 * Sumber hulu  : delivery_trip.token, delivery_stop.arrived_at.
 * Output       : view driver.trip; POST tandai / batal tandai tiba (tanpa CSRF, token = kuncinya, dibatasi throttle).
 * Dipakai oleh : routes/web.php (driver.show, driver.arrive, driver.undo, di luar grup auth).
 *                HP saja (kamera dan GPS butuh HTTPS): scan() mencocokkan kode yang dipindai dengan nomor surat jalan / kode alamat dan menyimpannya; position() menyimpan titik GPS sopir hanya selama trip Ditugaskan dan masih ada alamat belum tiba;
 *                manifest() = PWA "Pasang ke layar utama" yang membuka layar sopir trip ini.
 * Status       : Aktif.
 */
class DriverTripController extends Controller
{
    public function show(string $token)
    {
        $trip = DeliveryTrip::query()->where('token', $token)->with(['stops.docs.lines.batch:id,name,unit', 'vehicle:id,plate,name'])->firstOrFail();
        abort_if($trip->status === 'cancelled', 410, 'Trip ini dibatalkan. Hubungi admin.');

        return view('driver.trip', ['trip' => $trip, 'sched' => $trip->schedule()]);
    }

    public function arrive(string $token, int $stop): RedirectResponse
    {
        return $this->mark($token, $stop, now());
    }

    public function undo(string $token, int $stop): RedirectResponse
    {
        return $this->mark($token, $stop, null);
    }

    private function mark(string $token, int $stop, $at): RedirectResponse
    {
        $trip = DeliveryTrip::query()->where('token', $token)->firstOrFail();
        abort_unless($trip->status === 'assigned', 410, 'Trip ini tidak aktif. Hubungi admin.');
        DeliveryStop::query()->where('trip_id', $trip->id)->findOrFail($stop)->update(['arrived_at' => $at]);

        return redirect()->route('driver.show', $token);
    }

    // Hasil pindai kamera: kode cocok bila sama dengan nomor surat jalan alamat ini atau kode alamat (nomor trip-urutan); disimpan dengan waktu, yang terakhir menimpa.
    public function scan(Request $request, string $token, int $stop): JsonResponse
    {
        $code = $request->validate(['code' => ['required', 'string', 'max:80']])['code'];
        $trip = DeliveryTrip::query()->where('token', $token)->firstOrFail();
        abort_unless($trip->status === 'assigned', 410, 'Trip ini tidak aktif. Hubungi admin.');
        $s = DeliveryStop::query()->where('trip_id', $trip->id)->with(['trip', 'docs'])->findOrFail($stop);
        $ok = in_array(mb_strtoupper(trim($code)), $s->expectedCodes(), true);
        $s->update(['scanned_at' => now(), 'scan_code' => trim($code), 'scan_ok' => $ok]);

        return response()->json(['ok' => $ok]);
    }

    // Titik GPS sopir. Ditolak (409) bila trip tidak berjalan: bukan Ditugaskan atau semua alamat sudah tiba, supaya pelacakan berhenti sendiri.
    public function position(Request $request, string $token): JsonResponse
    {
        $d = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180'], 'accuracy' => ['nullable', 'numeric', 'min:0', 'max:65000']]);
        $trip = DeliveryTrip::query()->where('token', $token)->firstOrFail();
        if ($trip->status !== 'assigned' || ! $trip->stops()->whereNull('arrived_at')->exists()) {
            return response()->json(['active' => false], 409);
        }
        DeliveryPosition::create($d + ['trip_id' => $trip->id, 'recorded_at' => now()]);

        return response()->json(['active' => true]);
    }

    public function manifest(string $token): JsonResponse
    {
        $trip = DeliveryTrip::query()->where('token', $token)->firstOrFail();
        $icon = fn (int $n) => ['src' => asset("icons/adminku-$n.png"), 'sizes' => "{$n}x$n", 'type' => 'image/png', 'purpose' => 'any'];

        return response()->json(['name' => 'AdminKU Sopir '.$trip->number(), 'short_name' => 'Sopir', 'start_url' => route('driver.show', $token), 'scope' => route('driver.show', $token), 'display' => 'standalone', 'background_color' => '#ffffff', 'theme_color' => '#0f766e', 'icons' => [$icon(192), $icon(512)]])
            ->header('Content-Type', 'application/manifest+json');
    }
}
