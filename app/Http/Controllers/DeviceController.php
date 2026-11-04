<?php

namespace App\Http\Controllers;

use App\Models\DeviceReport;
use App\Models\UserDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Layar Perangkat Saya (menu profil, semua peran, tanpa hak Grup): sesi login milik pengguna sendiri dan mengeluarkan perangkat lain.
 *                Selalu dibatasi user_id pengguna yang login dan tidak pernah menyentuh sesi ini. Sesi yang sudah lewat SESSION_LIFETIME dibuang saat layar dibuka.
 * Dipakai oleh : routes/web.php (devices.index, devices.destroy, devices.destroy-others).
 * Status       : Aktif.
 */
class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $mine = UserDevice::where('user_id', $request->user()->id);
        (clone $mine)->where('last_active_at', '<', now()->subMinutes((int) config('session.lifetime')))->delete();

        return view('devices.index', ['devices' => $mine->orderByDesc('last_active_at')->get(), 'current' => $request->session()->getId()]);
    }

    public function destroy(Request $request, UserDevice $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id && $device->session_id !== $request->session()->getId(), 403);
        $this->kick(collect([$device]));

        return back()->with('status', 'Perangkat dikeluarkan.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $this->kick(UserDevice::where('user_id', $request->user()->id)->where('session_id', '!=', $request->session()->getId())->get());

        return back()->with('status', 'Semua perangkat lain dikeluarkan.');
    }

    // Laporan terakhir pengguna ini (bila ada) bertab gaya CPU-X; selalu menampilkan persetujuan + tombol unduh agen untuk memeriksa ulang.
    public function details(Request $request)
    {
        return view('devices.details', ['report' => DeviceReport::where('user_id', $request->user()->id)->latest('checked_at')->first()]);
    }

    // Pengguna sudah menekan "Setuju & Lanjutkan": buat token sekali pakai (30 menit) lalu unduh skrip dengan token + alamat laporan tertanam.
    public function agent(Request $request)
    {
        abort_unless($request->boolean('consent'), 422, 'Persetujuan diperlukan.');
        $token = Str::random(48);
        Cache::put('agent-token:'.$token, $request->user()->id, now()->addMinutes(30));
        $script = str_replace('__URL__', route('agent.report', $token), file_get_contents(resource_path('agent/adminku-perangkat.py')));

        return response($script, 200, ['Content-Type' => 'text/x-python; charset=utf-8', 'Content-Disposition' => 'attachment; filename="adminku-perangkat.py"', 'Cache-Control' => 'no-store']);
    }

    // Byte acak (tidak bisa dikompres) sebanyak ?kb (1 sampai 8192 KB) untuk mengukur kecepatan unduh.
    public function speedDown(Request $request)
    {
        $kb = max(1, min(8192, $request->integer('kb', 1024)));

        return response(random_bytes($kb * 1024), 200, ['Content-Type' => 'application/octet-stream', 'Cache-Control' => 'no-store']);
    }

    // Badan permintaan dibuang; waktu kirimnya diukur browser. Batas ukuran dijaga server web (post_max_size).
    public function speedUp()
    {
        return response()->noContent();
    }

    private function kick($devices): void
    {
        foreach ($devices as $device) {
            Session::getHandler()->destroy($device->session_id);
            $device->delete();
        }
    }
}
