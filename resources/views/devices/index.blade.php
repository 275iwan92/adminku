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
    <div style="max-width: 1100px">
        <x-dl::divider label="Perangkat ini" />
        <x-dl::kpi-row id="device-summary" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px">
            <x-dl::status-card data-k="cpu" title="Prosesor" status="Membaca" tone="accent" icon="fa-microchip" value="-" desc="thread CPU" />
            <x-dl::status-card data-k="ram" title="Memori (RAM)" status="Membaca" tone="accent" icon="fa-memory" value="-" desc="perkiraan browser" />
            <x-dl::status-card data-k="screen" title="Layar" status="Membaca" tone="info" icon="fa-desktop" value="-" desc="piksel" />
            <x-dl::card data-k="net" title="Kecepatan jaringan" icon="fa-gauge-high">
                <x-dl::gauge :value="0" :max="100" unit=" Mbps" label="Unduh (perkiraan browser)" :bands="[[5, 'danger'], [20, 'warn'], [100, 'success']]" />
                <small class="dl-note" data-net-sub style="display: block; text-align: center">-</small>
            </x-dl::card>
        </x-dl::kpi-row>
        <p class="dl-note">Angka kasar dari browser Anda. Sisa disk dan pemakaian bandwidth sebenarnya butuh pemeriksaan lengkap (menyusul).</p>
        <script>
            (() => {
                const n = navigator, c = n.connection, set = (k, ok, v, desc) => {
                    const box = document.querySelector('#device-summary [data-k="' + k + '"]');
                    box.querySelector('.dl-scard__value').textContent = v;
                    if (desc) box.querySelector('.dl-scard__desc').textContent = desc;
                    box.querySelector('.dl-badge').lastChild.textContent = ok ? 'Terbaca' : 'Tidak dilaporkan';
                };
                set('cpu', n.hardwareConcurrency, n.hardwareConcurrency ?? 'n/a');
                set('ram', n.deviceMemory, n.deviceMemory ? '≥ ' + n.deviceMemory + ' GB' : 'n/a');
                set('screen', screen.width, screen.width + '×' + screen.height);
                // Speedometer: jarum digeser sesuai unduh (Mbps, skala 0-100); sudut sama dengan Gauge::point di server.
                const box = document.querySelector('#device-summary [data-k="net"]'), v = c?.downlink ?? 0, a = Math.PI * (1 - Math.min(v, 100) / 100);
                box.querySelector('.dl-gauge__needle').setAttribute('x2', (100 + 62 * Math.cos(a)).toFixed(2));
                box.querySelector('.dl-gauge__needle').setAttribute('y2', (100 - 62 * Math.sin(a)).toFixed(2));
                const big = box.querySelector('.dl-gauge__big');
                big.textContent = c ? v.toFixed(1) + ' Mbps' : 'n/a';
                big.className = 'dl-gauge__big is-' + (v <= 5 ? 'danger' : v <= 20 ? 'warn' : 'success');
                box.querySelector('[data-net-sub]').textContent = c ? (c.effectiveType || '-') + ' · latensi ' + (c.rtt ?? '-') + ' ms' : 'browser tidak melaporkan';
            })();
        </script>

        <x-dl::divider label="Perangkat yang sedang masuk dengan akun Anda" />
        <x-dl::kpi-row style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px">
            @foreach ($devices as $d)
                @php $here = $d->session_id === $current; @endphp
                <div>
                    <x-dl::status-card :title="\App\Models\UserDevice::describe($d->agent)" :status="$here ? 'Perangkat ini' : 'Aktif'" :tone="$here ? 'success' : 'info'"
                        :icon="preg_match('/iPhone|Android|Mobile/', (string) $d->agent) ? 'fa-mobile-screen' : 'fa-laptop'"
                        :value="'IP '.($d->ip ?? '-')" :desc="'Masuk '.$d->created_at?->format('d/m/Y H:i')" :meta="'Aktif '.$d->last_active_at->diffForHumans()" />
                    @unless ($here)
                        <x-dl::action-form :action="route('devices.destroy', $d)">
                            <x-dl::button type="submit" variant="secondary" size="sm" icon="right-from-bracket" label="Keluarkan Perangkat Ini" />
                        </x-dl::action-form>
                    @endunless
                </div>
            @endforeach
        </x-dl::kpi-row>
        <p class="dl-note">Bila ada yang tidak Anda kenal, keluarkan perangkat itu lalu ubah password.</p>
        @if ($devices->count() > 1)
            <x-dl::action-form :action="route('devices.destroy-others')">
                <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="right-from-bracket" label="Keluarkan Semua Perangkat Lain" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </div>
@endsection
