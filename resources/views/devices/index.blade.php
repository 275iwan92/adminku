{{-- REMARK
 Tujuan       : Layar Perangkat Saya: ringkasan ringan perangkat ini (dibaca browser: inti CPU, RAM kira-kira, layar, jaringan), daftar sesi login pengguna sendiri (perangkat, IP, terakhir aktif, penanda perangkat ini) dan tombol mengeluarkan perangkat lain.
 Sumber hulu  : DeviceController@index (devices, current).
 Output       : POST devices.destroy (satu perangkat), devices.destroy-others (semua selain ini).
 Catatan      : Browser tidak bisa membaca sisa disk dan pemakaian bandwidth sebenarnya; itu tugas agen Python ("More details", tahap berikut).
 Dipakai oleh : route devices.index (menu profil > Perangkat Saya, semua peran).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'My devices'])
@section('body')
    @include('partials.flash')
    <div style="max-width: 1000px">
        <h2 style="margin: 4px 0 12px"><i class="fa-solid fa-laptop"></i> Perangkat Saya</h2>

        <x-dl::kpi-row id="device-summary">
            <x-dl::stat-box data-k="cpu" label="Prosesor" value="-" tone="accent" sub="thread CPU" />
            <x-dl::stat-box data-k="ram" label="Memori (RAM)" value="-" tone="accent" sub="perkiraan browser" />
            <x-dl::stat-box data-k="screen" label="Layar" value="-" tone="neutral" sub="piksel" />
            <x-dl::stat-box data-k="net" label="Jaringan" value="-" tone="success" sub="-" />
        </x-dl::kpi-row>
        <p class="dl-note">Angka kasar yang dibaca browser Anda. Sisa disk dan pemakaian bandwidth sebenarnya butuh pemeriksaan lengkap (menyusul).</p>
        <script>
            (() => {
                const n = navigator, c = n.connection, set = (k, v, sub) => {
                    const box = document.querySelector('#device-summary [data-k="' + k + '"]');
                    box.querySelector('.dl-stat__value').textContent = v;
                    if (sub) box.querySelector('.dl-stat__sub').textContent = sub;
                };
                set('cpu', n.hardwareConcurrency ?? 'n/a');
                set('ram', n.deviceMemory ? '≥ ' + n.deviceMemory + ' GB' : 'n/a', n.deviceMemory ? null : 'tidak dilaporkan browser');
                set('screen', screen.width + '×' + screen.height);
                set('net', c ? (c.downlink ?? '-') + ' Mbps' : 'n/a', c ? (c.effectiveType || '-') + ' · latensi ' + (c.rtt ?? '-') + ' ms' : 'tidak dilaporkan browser');
            })();
        </script>

        <x-dl::divider label="Perangkat yang sedang masuk" />
        <p class="dl-note">Bila ada yang tidak Anda kenal, keluarkan perangkat itu lalu ubah password.</p>
        @foreach ($devices as $d)
            @php $here = $d->session_id === $current; @endphp
            <x-dl::card :title="\App\Models\UserDevice::describe($d->agent)" :icon="str_contains((string) $d->agent, 'Mobile') ? 'mobile-screen' : 'laptop'" style="margin-bottom: 10px">
                @unless ($here)
                    <x-slot:actions>
                        <x-dl::action-form :action="route('devices.destroy', $d)">
                            <x-dl::button type="submit" variant="secondary" size="sm" icon="right-from-bracket" label="Keluarkan" />
                        </x-dl::action-form>
                    </x-slot:actions>
                @endunless
                <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center">
                    @if ($here) <x-dl::badge label="Perangkat ini" tone="success" /> @endif
                    <x-dl::badge :label="'IP '.($d->ip ?? '-')" tone="neutral" />
                    <x-dl::badge :label="'Masuk '.$d->created_at?->format('d/m/Y H:i')" tone="neutral" />
                    <x-dl::badge :label="'Aktif '.$d->last_active_at->diffForHumans()" tone="info" />
                </div>
            </x-dl::card>
        @endforeach
        @if ($devices->count() > 1)
            <x-dl::action-form :action="route('devices.destroy-others')">
                <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="right-from-bracket" label="Keluarkan Semua Perangkat Lain" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </div>
@endsection
