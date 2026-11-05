<?php

namespace App\Http\Controllers;

use App\Models\DeliveryStop;
use App\Models\DeliveryTrip;
use Illuminate\Http\RedirectResponse;

/* REMARK
 * Tujuan       : Layar sopir di HP tanpa login: satu trip lewat tautan rahasia (token). Sopir menandai alamat yang sudah tiba (jam asli tercatat), ETA alamat berikutnya dihitung ulang (DeliveryTrip::schedule).
 *                Hanya trip Ditugaskan yang bisa dibuka dan ditandai; trip dibatalkan menampilkan pesan. Tanda tiba boleh dibatalkan bila salah pencet.
 * Sumber hulu  : delivery_trip.token, delivery_stop.arrived_at.
 * Output       : view driver.trip; POST tandai / batal tandai tiba (tanpa CSRF, token = kuncinya, dibatasi throttle).
 * Dipakai oleh : routes/web.php (driver.show, driver.arrive, driver.undo, di luar grup auth).
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
}
