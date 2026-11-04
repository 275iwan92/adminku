{{-- REMARK
 Tujuan       : Layar Perangkat Saya: ringkasan ringan perangkat ini (dibaca browser: thread CPU, RAM kira-kira, layar, kartu grafis), dua speedometer hasil uji nyata ke server (Unduh dan Unggah, plus latensi),
                daftar sesi login pengguna sendiri (perangkat, IP, terakhir aktif, penanda perangkat ini) dan tombol mengeluarkan perangkat lain.
 Sumber hulu  : DeviceController@index (devices, current); uji kecepatan: devices.speed-down (GET), devices.speed-up (POST).
 Output       : POST devices.destroy (satu perangkat), devices.destroy-others (semua selain ini).
 Catatan      : Browser tidak bisa membaca nama prosesor, RAM terpasang, sisa disk, dan pemakaian bandwidth OS; itu tugas agen Python ("More details", tahap berikut). Kecepatan = ke server aplikasi ini, bukan ke internet.
 Dipakai oleh : route devices.index (menu profil > Perangkat Saya, semua peran).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'My devices'])
@section('body')
    @include('partials.flash')
    @php
        $grid = 'display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px';
        $bands = [[5, 'danger'], [20, 'warn'], [100, 'success']];
    @endphp
    <div style="max-width: 1100px">
        <x-dl::divider label="Perangkat ini" />
        <x-dl::kpi-row id="device-summary" :style="$grid">
            <x-dl::status-card data-k="cpu" title="Prosesor" status="Membaca" tone="accent" icon="fa-microchip" value="-" desc="thread CPU" />
            <x-dl::status-card data-k="ram" title="Memori (RAM)" status="Membaca" tone="accent" icon="fa-memory" value="-" desc="perkiraan browser" />
            <x-dl::status-card data-k="gpu" title="Grafis" status="Membaca" tone="accent" icon="fa-display" value="-" desc="-" />
            <x-dl::status-card data-k="screen" title="Layar" status="Membaca" tone="info" icon="fa-desktop" value="-" desc="piksel" />
        </x-dl::kpi-row>
        <p class="dl-note">Dibaca browser Anda. Nama prosesor, RAM terpasang, dan sisa disk butuh pemeriksaan lengkap (menyusul).</p>

        <x-dl::divider label="Kecepatan jaringan ke server" />
        <x-dl::kpi-row id="device-speed" :style="$grid">
            <x-dl::card data-k="down" title="Unduh" icon="fa-arrow-down">
                <x-dl::gauge :value="0" :max="100" unit=" Mbps" label="Unduh" :bands="$bands" />
            </x-dl::card>
            <x-dl::card data-k="up" title="Unggah" icon="fa-arrow-up">
                <x-dl::gauge :value="0" :max="100" unit=" Mbps" label="Unggah" :bands="$bands" />
            </x-dl::card>
            <x-dl::status-card data-k="ping" title="Latensi" status="Menguji" tone="info" icon="fa-stopwatch" value="-" desc="ke server aplikasi" />
        </x-dl::kpi-row>
        <p class="dl-note" id="speed-note">Menguji kecepatan...</p>
        <x-dl::button type="button" variant="secondary" size="sm" icon="fa-rotate" label="Uji Ulang" id="speed-again" />

        <script>
            (() => {
                const n = navigator, $ = (s) => document.querySelector(s);
                const card = (id, k) => $(id + ' [data-k="' + k + '"]');
                const tone = (v) => (v <= 5 ? 'danger' : v <= 20 ? 'warn' : 'success');
                const fill = (k, ok, v, desc) => {
                    const box = card('#device-summary', k);
                    box.querySelector('.dl-scard__value').textContent = v;
                    if (desc) box.querySelector('.dl-scard__desc').textContent = desc;
                    box.querySelector('.dl-badge').lastChild.textContent = ok ? 'Terbaca' : 'Tidak dilaporkan';
                };
                let gpu = null;
                try {
                    const gl = document.createElement('canvas').getContext('webgl'), x = gl?.getExtension('WEBGL_debug_renderer_info');
                    gpu = x ? gl.getParameter(x.UNMASKED_RENDERER_WEBGL).replace(/^ANGLE \((?:[^,]+, )?/, '').replace(/ \(0x[0-9A-Fa-f]+\).*$|,? Direct3D.*$|\)$/g, '') : null;
                } catch (e) {}
                fill('cpu', n.hardwareConcurrency, n.hardwareConcurrency ?? 'n/a');
                fill('ram', n.deviceMemory, n.deviceMemory ? '≥ ' + n.deviceMemory + ' GB' : 'n/a');
                fill('gpu', gpu, gpu ? gpu.split(' ').slice(0, 3).join(' ') : 'n/a', gpu || 'browser tidak melaporkan');
                fill('screen', screen.width, screen.width + '×' + screen.height);

                // Speedometer: jarum digeser ke nilai Mbps (skala 0-100); sudut sama dengan Gauge::point di server.
                const needle = (k, v) => {
                    const box = card('#device-speed', k), a = Math.PI * (1 - Math.min(v, 100) / 100), line = box.querySelector('.dl-gauge__needle'), big = box.querySelector('.dl-gauge__big');
                    line.setAttribute('x2', (100 + 62 * Math.cos(a)).toFixed(2));
                    line.setAttribute('y2', (100 - 62 * Math.sin(a)).toFixed(2));
                    big.textContent = v.toFixed(1) + ' Mbps';
                    big.className = 'dl-gauge__big is-' + tone(v);
                };
                const csrf = document.querySelector('meta[name="csrf-token"]').content;
                const mbps = (bytes, ms) => (bytes * 8) / 1e6 / (ms / 1000);
                const run = async () => {
                    const note = $('#speed-note'), ping = card('#device-speed', 'ping'), t = () => performance.now();
                    note.textContent = 'Menguji kecepatan...';
                    try {
                        let t0 = t();
                        for (let i = 0; i < 3; i++) await fetch('{{ route('devices.speed-down', ['kb' => 1]) }}&r=' + Math.random(), { cache: 'no-store' });
                        const rtt = (t() - t0) / 3;
                        ping.querySelector('.dl-scard__value').textContent = Math.round(rtt) + ' ms';
                        ping.querySelector('.dl-badge').lastChild.textContent = rtt < 50 ? 'Baik' : rtt < 150 ? 'Cukup' : 'Lambat';
                        t0 = t();
                        const down = await (await fetch('{{ route('devices.speed-down', ['kb' => 4096]) }}&r=' + Math.random(), { cache: 'no-store' })).arrayBuffer();
                        needle('down', mbps(down.byteLength, t() - t0));
                        t0 = t();
                        const up = new Blob([new Uint8Array(2 * 1024 * 1024)]);
                        await fetch('{{ route('devices.speed-up') }}', { method: 'POST', body: up, headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/octet-stream' } });
                        needle('up', mbps(up.size, t() - t0));
                        note.textContent = 'Hasil uji singkat ke server aplikasi ini (' + new Date().toLocaleTimeString('id-ID') + '), bukan kecepatan internet Anda. Di jaringan lokal angkanya bisa sangat tinggi.';
                    } catch (e) {
                        note.textContent = 'Uji kecepatan gagal. Coba lagi.';
                    }
                };
                $('#speed-again').addEventListener('click', run);
                run();
            })();
        </script>

        <x-dl::divider label="Perangkat yang sedang masuk dengan akun Anda" />
        <x-dl::kpi-row :style="$grid">
            @foreach ($devices as $d)
                @php $here = $d->session_id === $current; @endphp
                <div>
                    <x-dl::status-card :title="\App\Models\UserDevice::describe($d->agent)" :status="$here ? 'Perangkat ini' : 'Aktif'" :tone="$here ? 'success' : 'info'"
                        :icon="preg_match('/iPhone|Android|Mobile/', (string) $d->agent) ? 'fa-mobile-screen' : 'fa-laptop'"
                        :value="'IP '.($d->ip ?? '-')" :desc="'Masuk '.$d->created_at?->format('d/m/Y H:i')" :meta="'Aktif '.$d->last_active_at->diffForHumans()" />
                    @unless ($here)
                        <x-dl::action-form :action="route('devices.destroy', $d)">
                            <x-dl::button type="submit" variant="secondary" size="sm" icon="fa-right-from-bracket" label="Keluarkan Perangkat Ini" />
                        </x-dl::action-form>
                    @endunless
                </div>
            @endforeach
        </x-dl::kpi-row>
        <p class="dl-note">Bila ada yang tidak Anda kenal, keluarkan perangkat itu lalu ubah password.</p>
        @if ($devices->count() > 1)
            <x-dl::action-form :action="route('devices.destroy-others')">
                <x-dl::action-bar><x-dl::button type="submit" variant="danger" icon="fa-right-from-bracket" label="Keluarkan Semua Perangkat Lain" /></x-dl::action-bar>
            </x-dl::action-form>
        @endif
    </div>
@endsection
