{{-- REMARK
 Tujuan       : Layar Perangkat Saya: ringkasan hasil pemeriksaan lengkap terakhir (More Details), dua speedometer hasil uji nyata ke server (Unduh dan Unggah, plus latensi),
                daftar sesi login pengguna sendiri (perangkat, IP, terakhir aktif, penanda perangkat ini) dan tombol mengeluarkan perangkat lain.
 Sumber hulu  : DeviceController@index (devices, current); uji kecepatan: devices.speed-down (GET), devices.speed-up (POST).
 Output       : POST devices.destroy (satu perangkat), devices.destroy-others (semua selain ini).
 Catatan      : Browser tidak bisa membaca nama prosesor, RAM terpasang, sisa disk, dan pemakaian bandwidth OS; itu dari agen "More details" (baris hasil pemeriksaan lengkap). Kecepatan = ke server aplikasi ini, bukan ke internet.
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
        <div style="display: flex; justify-content: space-between; align-items: center">
            <x-dl2::divider label="Perangkat ini" style="flex: 1" />
            <x-dl2::button href="{{ route('devices.details') }}" variant="primary" size="lg" icon="fa-circle-info" label="More Details" />
        </div>
        @if ($report)
            @php
                $r = $report->payload; $cpu = $r['cpu'] ?? []; $mods = $r['memory']['modules'] ?? []; $gpu = $r['graphics'][0] ?? [];
                $disks = collect($r['storage'] ?? []);
                $sys = $disks->firstWhere('name', 'C:') ?? $disks->first();
                $total = (float) ($sys['total_gb'] ?? 0); $usedGb = $total - (float) ($sys['free_gb'] ?? 0); $pct = $total > 0 ? (int) round($usedGb / $total * 100) : 0;
            @endphp
            <x-dl2::divider :label="'Hasil pemeriksaan lengkap · '.$report->checked_at->format('d/m/Y H:i')" />
            <x-dl2::kpi-row :style="$grid">
                <x-dl2::status-card title="Prosesor" status="Terukur" tone="success" icon="fa-microchip" :value="$cpu['name'] ?? 'Tidak dilaporkan'" :desc="($cpu['cores'] ?? '?').' core · '.($cpu['threads'] ?? '?').' thread'" />
                <x-dl2::status-card title="Memori (RAM)" status="Terukur" tone="success" icon="fa-memory" :value="isset($r['memory']['total_gb']) ? $r['memory']['total_gb'].' GB' : 'Tidak dilaporkan'" :desc="$mods ? count($mods).' modul · '.($mods[0]['speed'] ?? '?').' MT/s' : 'Rincian slot tidak dilaporkan'" />
                <x-dl2::status-card title="Grafis" status="Terukur" tone="success" icon="fa-display" :value="$gpu['name'] ?? 'Tidak dilaporkan'" :desc="isset($gpu['ram_mb']) ? 'Memori grafis '.$gpu['ram_mb'].' MB' : '-'" />
                <x-dl2::card title="Penyimpanan" icon="fa-hard-drive">
                    @if ($sys)
                        <x-dl2::progress :value="$pct" :tone="$pct >= 90 ? 'danger' : ($pct >= 80 ? 'warn' : 'success')" :label="'Disk '.($sys['name'] ?? '-')" :text="round($usedGb, 1).' / '.$total.' GB ('.$pct.'%)'" />
                    @else
                        <p class="ak-note">Tidak dilaporkan.</p>
                    @endif
                </x-dl2::card>
            </x-dl2::kpi-row>
        @endif
        @unless ($report)
            <p class="ak-note">Belum ada pemeriksaan lengkap. Klik More Details untuk membaca prosesor, RAM, grafis, dan disk komputer ini.</p>
        @endunless

        <x-dl2::divider label="Kecepatan jaringan ke server" />
        <x-dl2::kpi-row id="device-speed" :style="$grid">
            <x-dl2::card data-k="down" title="Unduh" icon="fa-arrow-down">
                <x-dl2::gauge :value="0" :max="100" unit=" Mbps" label="Unduh" :bands="$bands" />
            </x-dl2::card>
            <x-dl2::card data-k="up" title="Unggah" icon="fa-arrow-up">
                <x-dl2::gauge :value="0" :max="100" unit=" Mbps" label="Unggah" :bands="$bands" />
            </x-dl2::card>
            <x-dl2::status-card data-k="ping" title="Latensi" status="Menguji" tone="info" icon="fa-stopwatch" value="-" desc="ke server aplikasi" />
        </x-dl2::kpi-row>
        <p class="ak-note" id="speed-note">Menguji kecepatan...</p>
        <x-dl2::button type="button" variant="secondary" size="sm" icon="fa-rotate" label="Uji Ulang" id="speed-again" />

        <script>
            (() => {
                const n = navigator, $ = (s) => document.querySelector(s);
                const card = (id, k) => $(id + ' [data-k="' + k + '"]');
                const tone = (v) => (v <= 5 ? 'danger' : v <= 20 ? 'warn' : 'success');

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

        <x-dl2::divider label="Perangkat yang sedang masuk dengan akun Anda" />
        <x-dl2::kpi-row :style="$grid">
            @foreach ($devices as $d)
                @php $here = $d->session_id === $current; @endphp
                <div>
                    <x-dl2::status-card :title="\App\Models\UserDevice::describe($d->agent)" :status="$here ? 'Perangkat ini' : 'Aktif'" :tone="$here ? 'success' : 'info'"
                        :icon="preg_match('/iPhone|Android|Mobile/', (string) $d->agent) ? 'fa-mobile-screen' : 'fa-laptop'"
                        :value="'IP '.($d->ip ?? '-')" :desc="'Masuk '.$d->created_at?->format('d/m/Y H:i')" :meta="'Aktif '.$d->last_active_at->diffForHumans()" />
                    @unless ($here)
                        <x-dl2::action-form :action="route('devices.destroy', $d)">
                            <x-dl2::button type="submit" variant="secondary" size="sm" icon="fa-right-from-bracket" label="Keluarkan Perangkat Ini" />
                        </x-dl2::action-form>
                    @endunless
                </div>
            @endforeach
        </x-dl2::kpi-row>
        <p class="ak-note">Bila ada yang tidak Anda kenal, keluarkan perangkat itu lalu ubah password.</p>
        @if ($devices->count() > 1)
            <x-dl2::action-form :action="route('devices.destroy-others')">
                <x-dl2::action-bar><x-dl2::button type="submit" variant="danger" icon="fa-right-from-bracket" label="Keluarkan Semua Perangkat Lain" /></x-dl2::action-bar>
            </x-dl2::action-form>
        @endif
    </div>
@endsection
