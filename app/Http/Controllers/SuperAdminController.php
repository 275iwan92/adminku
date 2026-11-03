<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ActivityLog;
use App\Support\IpLocation;
use App\Support\Text;
use Database\Seeders\UserSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/* REMARK
 * Tujuan       : Layar khusus super admin (SSTOCK_SUPER_ADMIN, mis. johiwan): (1) Log Aktivitas: siapa, IP, kapan, buka / input / ubah / hapus apa, per bulan, bisa diunduh sebagai CSV;
 *                (2) Akun UAT: daftar akun dengan kata sandi BAWAAN seeder dan pengubahan kata sandi akun mana pun (untuk memulihkan akun UAT yang terkunci atau diubah penguji).
 *                Kata sandi yang tersimpan di database adalah hash, tidak bisa dibaca: yang tampil hanya nilai bawaan seeder (berlaku selama belum diubah). Kata sandi baru yang diketik di sini disimpan sebagai hash.
 *                Hanya program demo / UAT, bukan data nyata.
 * Sumber hulu  : ActivityLog (CSV bulanan), users, UserSeeder::ACCOUNTS.
 * Output       : view super.log, super.accounts; unduhan CSV; ubah kata sandi (sesi akun itu dicabut).
 * Dipakai oleh : routes/web.php (super.*, middleware super: selain super admin = 404).
 * Status       : Aktif.
 */
class SuperAdminController extends Controller
{
    public function log(Request $request)
    {
        $months = ActivityLog::months();
        $month = in_array($request->query('month'), $months, true) ? $request->query('month') : ($months[0] ?? now()->format('Y-m'));

        $rows = ActivityLog::read($month, 1500);
        $loc = IpLocation::label(array_column($rows, 'ip'));   // hanya tampilan; CSV unduhan tidak berubah
        $rows = array_map(fn ($row) => $row + ['loc' => $loc[$row['ip']] ?? ''], $rows);

        return view('super.log', ['rows' => $rows, 'month' => $month, 'months' => $months ?: [$month]]);
    }

    public function download(Request $request)
    {
        $month = Text::of($request->query('month'));
        abort_unless(in_array($month, ActivityLog::months(), true), 404);

        return response()->download(ActivityLog::file($month), "aktivitas-{$month}.csv", ['Content-Type' => 'text/csv']);
    }

    public function accounts()
    {
        $defaults = array_column(UserSeeder::accounts(), 'password', 'username');
        $users = User::visible()->orderBy('username')->get();

        return view('super.accounts', ['users' => $users, 'defaults' => $defaults]);
    }

    public function password(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 404);
        $data = $request->validate(['password' => ['required', 'string', 'min:4', 'max:100']], ['password.required' => 'Isi kata sandi baru.', 'password.min' => 'Kata sandi minimal 4 karakter.']);

        $user->forceFill(['password' => $data['password'], 'remember_token' => null])->save();   // cast "hashed"
        DB::table('sessions')->where('user_id', $user->id)->delete();                              // sesi lama akun itu keluar

        return redirect()->route('super.accounts')->with('status', "Kata sandi {$user->username} diubah.");
    }
}
