<?php

namespace App\Http\Controllers;

use App\Models\UserDevice;
use App\Support\Wallpapers;
use Devlib2\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/* REMARK
 * Tujuan       : Login / logout. Username atau e-mail + password (tanpa "ingat saya": sesi habis sesuai SESSION_LIFETIME); akun nonaktif ditolak; percobaan dibatasi per login + IP.
 * Tampilan     : kolom ui (hp | desktop) dari halaman login disimpan di cookie ui.
 * Output       : redirect sesuai peran: Supervisor ke dashboard, Data Entry ke layar Receiving.
 * Dipakai oleh : routes/web.php; tampilan: resources/views/login.blade.php.
 * Status       : Aktif.
 */
class AuthController extends Controller
{
    public function show()
    {
        defer(fn () => Wallpapers::sync());   // sesudah respons terkirim: login tidak menunggu konversi

        // Ponsel (atau pilihan pengguna) = halaman login HP; selain itu login desktop (di layar sempit dengan popup tawaran tampilan HP).
        return view(Device::view(request()) === Device::PHONE ? 'login-hp' : 'login', ['error' => session('error'), 'username' => old('login', '')]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string'], 'password' => ['required', 'string']], [
            'login.required' => 'Isi username atau e-mail Anda.',
            'password.required' => 'Isi password Anda.',
        ]);

        $key = Str::lower($data['login']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->fail($request, 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($key).' detik.');
        }

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        if (! Auth::attempt([$field => $data['login'], 'password' => $data['password'], 'is_active' => true])) {
            RateLimiter::hit($key);

            return $this->fail($request, 'Username/e-mail atau password salah, atau akun tidak aktif.');
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        // fresh: buang tab kerja sisa user sebelumnya di browser yang sama. ui: pilihan Tampilan HP / Desktop di halaman login disimpan di cookie (pilihan pengguna menang atas tebakan perangkat).
        $response = redirect()->intended($request->user()->isSupervisor() ? route('home') : route('home', ['open' => 'RCV']))->with('fresh', true);

        return ($ui = Device::normalize($request->input('ui'))) ? $response->withCookie(cookie(Device::COOKIE, $ui, 60 * 24 * 365))->withCookie(Cookie::forget(Device::AUTO_COOKIE)) : $response;
    }

    public function logout(Request $request): RedirectResponse
    {
        UserDevice::where('session_id', $request->session()->getId())->delete();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function fail(Request $request, string $message): RedirectResponse
    {
        return back()->withInput($request->only('login', 'ui'))->with('error', $message);
    }
}
