<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SampleData;
use App\Services\SampleHr;
use App\Support\ActivityLog;
use Devlib\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/* REMARK
 * Tujuan       : Layar Administrator "Data Contoh": melihat isi database saat ini dan menyegarkan seluruh data contoh dengan satu tombol (konfirmasi dengan mengetik SEGARKAN).
 *                Semua data (termasuk akun) diganti data contoh; data wilayah dipertahankan. Dimatikan di production kecuali SSTOCK_SAMPLE_REFRESH=true.
 * Sumber hulu  : App\Services\SampleData.
 * Output       : view sample.index; setelah penyegaran pengguna keluar dan masuk ulang dengan akun seeder.
 * Dipakai oleh : routes/web.php (sample.*, middleware administrator).
 * Status       : Aktif.
 */
class SampleDataController extends Controller
{
    public function index(Request $request)
    {
        $last = SampleData::last();

        return view('sample.index', [
            'enabled' => SampleData::allowed($request->user()->username),
            'superOnly' => User::superAdminUsername() !== null,
            'counts' => array_combine(array_values(SampleData::COUNTED), SampleData::counts()),
            'last' => $last ? Locale::stamp(Carbon::parse($last['at'])).' oleh '.$last['by'] : 'Belum pernah',
            'summary' => SampleData::summary(),
        ]);
    }

    // Reset data contoh HR (karyawan dan kandidat contoh) ke snapshot kanonik; data nyata dan bagian lain database tidak disentuh, pengguna tidak perlu keluar.
    public function resetHr(Request $request): RedirectResponse
    {
        $request->validate(['confirm_hr' => ['required', 'in:RESET']], ['confirm_hr.in' => 'Ketik RESET (huruf besar) untuk melanjutkan.', 'confirm_hr.required' => 'Ketik RESET untuk melanjutkan.']);
        $log = SampleHr::apply(true);
        ActivityLog::write((string) $request->user()->username, (string) $request->ip(), 'Reset data contoh HR', implode('; ', $log));

        return redirect()->route('sample.index')->with('status', 'Data contoh HR dikembalikan ke snapshot: '.implode('; ', $log).'.');
    }

    public function refresh(Request $request): RedirectResponse
    {
        abort_unless(SampleData::allowed($request->user()->username), 403, 'Penyegaran data contoh dimatikan di lingkungan ini.');
        $request->validate(['confirm' => ['required', 'in:SEGARKAN']], ['confirm.in' => 'Ketik SEGARKAN (huruf besar) untuk melanjutkan.', 'confirm.required' => 'Ketik SEGARKAN untuk melanjutkan.']);

        SampleData::refresh($request->user()->username);

        // Akun ikut diganti seeder: keluar dan masuk ulang supaya tidak memakai sesi lama.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Data contoh berhasil disegarkan. Silakan masuk kembali.');
    }
}
