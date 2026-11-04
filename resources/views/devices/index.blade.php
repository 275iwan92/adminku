{{-- REMARK
 Tujuan       : Layar Perangkat Saya: ringkasan ringan perangkat ini (dibaca browser: inti CPU, RAM kira-kira, jaringan), daftar sesi login pengguna sendiri (perangkat, IP, terakhir aktif, penanda perangkat ini) dan tombol mengeluarkan perangkat lain.
 Sumber hulu  : DeviceController@index (devices, current).
 Output       : POST devices.destroy (satu perangkat), devices.destroy-others (semua selain ini).
 Catatan      : Browser tidak bisa membaca sisa disk dan pemakaian bandwidth sebenarnya; itu tugas agen Python ("More details", tahap berikut).
 Dipakai oleh : route devices.index (menu profil > Perangkat Saya, semua peran).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'My devices'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 900px">
        <h2 style="margin: 4px 0 12px">Perangkat Saya</h2>
        <x-dl::divider label="Ringkasan perangkat ini" />
        <p class="dl-note" id="device-summary">Membaca perangkat...</p>
        <script>
            (() => {
                const n = navigator, c = n.connection, parts = [
                    n.hardwareConcurrency ? n.hardwareConcurrency + ' thread CPU' : null,
                    n.deviceMemory ? 'RAM ≥ ' + n.deviceMemory + ' GB (perkiraan browser)' : null,
                    screen.width + '×' + screen.height + ' layar',
                    c ? 'Jaringan ' + (c.effectiveType || '-') + ', unduh ≈ ' + (c.downlink ?? '-') + ' Mbps, latensi ' + (c.rtt ?? '-') + ' ms' : null,
                ];
                document.getElementById('device-summary').textContent = parts.filter(Boolean).join(' · ') + '. Angka kasar dari browser.';
            })();
        </script>
        <p class="dl-note">Perangkat yang sedang masuk dengan akun Anda. Bila ada yang tidak Anda kenal, keluarkan perangkat itu lalu ubah password.</p>
        @foreach ($devices as $d)
            <x-dl::divider :label="\App\Models\UserDevice::describe($d->agent).($d->session_id === $current ? ' (perangkat ini)' : '')" />
            <p class="dl-note">IP {{ $d->ip ?? '-' }} · Masuk {{ $d->created_at?->format('d/m/Y H:i') }} · Terakhir aktif {{ $d->last_active_at->diffForHumans() }}</p>
            @if ($d->session_id !== $current)
                <x-dl::action-form :action="route('devices.destroy', $d)">
                    <x-dl::action-bar><x-dl::button type="submit" variant="secondary" icon="fa-right-from-bracket" label="Keluarkan Perangkat Ini" /></x-dl::action-bar>
                </x-dl::action-form>
            @endif
        @endforeach
        @if ($devices->count() > 1)
            <x-dl::divider label="Semua perangkat lain" />
            <x-dl::action-form :action="route('devices.destroy-others')">
                <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="fa-right-from-bracket" label="Keluarkan Semua Perangkat Lain" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </div>
@endsection
