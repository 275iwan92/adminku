<?php

namespace App\Providers;

use App\Support\Company;
use App\Support\GeoLookup;
use App\Support\Indonesian;
use App\Support\ItemLookups;
use Devlib\AppVersion;
use Devlib\NetworkInfo;
use Devlib\PrintDoc\Document;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        ItemLookups::register();
        GeoLookup::register();
        Indonesian::register();
        Document::companyUsing(fn () => Company::kop());   // satu sumber kop semua cetakan: layar Perusahaan
        // Baris jaringan x-dl::net-line (IP client -> IP server + status koneksi) di bawah nama pengguna, semua pengguna: keluhan bisa ditelusuri dari mana asalnya.
        // Ping memakai rute bawaan DevLib /devlib/ping (tanpa sesi): session.ping memperpanjang sesi 30 menit dan login-redirect-nya bisa tampak sebagai gagal.
        // Versi di judul logo (menu kiri) dan halaman login: ver 0.<jumlah commit git> + huruf tahap (APP_STAGE) lewat Devlib\AppVersion.
        $this->app->bind('devlib.version', fn () => AppVersion::of(base_path()));
        $this->app->bind('devlib.net', fn () => NetworkInfo::of(request()));

        // E-mail reset password berbahasa Indonesia; tautan ke layar password baru, berlaku sesuai config auth.passwords.users.expire.
        ResetPassword::toMailUsing(function (object $user, string $token) {
            $url = route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()]);
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)->subject('Atur ulang password '.config('app.name'))
                ->greeting('Halo '.$user->name.',')
                ->line('Kami menerima permintaan untuk mengatur ulang password akun '.config('app.name').' Anda.')
                ->action('Atur password baru', $url)
                ->line("Tautan ini berlaku {$minutes} menit. Jika bukan Anda yang meminta, abaikan e-mail ini; password Anda tidak berubah.")
                ->salutation(config('app.name'));
        });
    }
}
