<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Wallpapers;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;
use App\Support\Text;

/* REMARK
 * Tujuan       : Lupa password dari layar login: minta tautan reset lewat e-mail (broker password Laravel, token berlaku sesuai config auth.passwords.users.expire), lalu atur password baru.
 *                Pesan permintaan selalu sama (tidak membocorkan apakah akun ada); akun nonaktif tidak dikirimi tautan.
 * Sumber hulu  : users (username / e-mail), password_reset_tokens, notifikasi ResetPassword (App\Providers\AppServiceProvider).
 *                Surel gagal terkirim: dicatat di log (Log::error), pengguna melihat pesan ramah, bukan error 500.
 * Output       : form lupa password, form password baru, redirect ke login dengan pesan.
 * Dipakai oleh : routes/web.php (password.request / email / reset / update, hanya tamu).
 * Status       : Aktif.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        defer(fn () => Wallpapers::sync());   // sesudah respons terkirim: login tidak menunggu konversi

        return view('auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:150']], ['login.required' => 'Isi username atau e-mail Anda.']);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $user = User::visible()->where($field, Str::lower(trim($data['login'])))->where('is_active', true)->first();
        if ($user) {
            try {
                Password::sendResetLink(['email' => $user->email]);
            } catch (Throwable $e) {
                // Pengiriman gagal (SMTP salah / port tertutup / ditolak): tercatat di log, pengguna dapat pesan ramah, token dibuang agar bisa minta lagi tanpa menunggu.
                Log::error('Surel reset password gagal dikirim: '.$e->getMessage(), ['user_id' => $user->id, 'mailer' => config('mail.default')]);
                Password::broker()->deleteToken($user);

                return back()->withErrors(['login' => 'Surel belum bisa dikirim saat ini. Coba lagi nanti atau hubungi Administrator.']);
            }
        }

        return back()->with('status', 'Jika akun terdaftar, tautan untuk mengatur ulang password sudah dikirim ke e-mail akun tersebut. Periksa kotak masuk Anda.');
    }

    public function reset(Request $request, string $token)
    {
        defer(fn () => Wallpapers::sync());   // sesudah respons terkirim: login tidak menunggu konversi

        return view('auth.reset-password', ['token' => $token, 'email' => Text::of($request->query('email'))]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ], ['password.confirmed' => 'Konfirmasi password tidak sama.', 'password.min' => 'Password minimal 8 karakter.']);

        $status = Password::reset($data, function (User $user, string $password) {
            abort_unless($user->is_active, 403, 'Akun tidak aktif.');
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Tautan tidak valid atau sudah kedaluwarsa. Minta tautan baru.']);
        }

        return redirect()->route('login')->with('status', 'Password berhasil diganti. Silakan masuk dengan password baru.');
    }
}
