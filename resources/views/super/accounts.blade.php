{{-- REMARK
 Tujuan       : Akun UAT (super admin): tiap akun dengan kata sandi BAWAAN seeder dan kolom untuk mengubah kata sandinya. Hanya program demo / UAT.
 Sumber hulu  : SuperAdminController@accounts (users tanpa super admin sendiri, UserSeeder::ACCOUNTS).
 Input        : users, defaults (username => kata sandi bawaan).
 Output       : POST super.password per akun (kata sandi baru disimpan sebagai hash, sesi akun itu dicabut).
 Dipakai oleh : route super.accounts (menu Keamanan > Akun UAT, hanya super admin).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Akun UAT'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 980px">
        <h2 class="sstock-pagetitle" style="margin: 4px 0 12px">Akun UAT</h2>
        <p class="ak-note">Kata sandi di database disimpan sebagai hash dan tidak bisa dibaca. Kolom <b>Bawaan UAT</b> adalah nilai dari seeder: berlaku selama akun itu belum diubah (lewat layar ini, layar Pengguna, atau lupa password) dan hilang saat data contoh disegarkan, yang mengembalikannya ke nilai ini. Kata sandi baru yang Anda ketik di sini disimpan sebagai hash dan sesi lama akun itu keluar.</p>
        <div class="dl2-table-responsive ak-stack">
            <table class="dl2-table is-striped">
                <thead><tr><th>Username</th><th>Nama</th><th>Peran</th><th>Aktif</th><th>Bawaan UAT</th><th>Kata sandi baru</th></tr></thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr>
                            <td data-label="Username">{{ $u->username }}</td>
                            <td data-label="Nama">{{ $u->name }}</td>
                            <td data-label="Peran">{{ $u->roleLabel() }}</td>
                            <td data-label="Aktif">{{ $u->is_active ? 'Ya' : 'Tidak' }}</td>
                            <td data-label="Bawaan UAT">{{ $defaults[$u->username] ?? '— (bukan akun bawaan)' }}</td>
                            <td data-label="Kata sandi baru">
                                <form method="POST" action="{{ route('super.password', $u) }}" style="display: flex; gap: 6px; align-items: center">
                                    @csrf
                                    <input type="text" name="password" required minlength="4" maxlength="100" autocomplete="off" placeholder="min. 4 karakter" style="width: 160px">
                                    <x-dl2::button type="submit" size="sm" variant="secondary" icon="fa-key" label="Ubah" />
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @error('password')<x-dl2::alert tone="danger" :message="$message" />@enderror
    </div>
@endsection
