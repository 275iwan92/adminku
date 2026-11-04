{{-- REMARK
 Tujuan       : Layar More details Perangkat Saya: persetujuan + unduh agen Python (pemeriksaan lengkap sekali jalan), lalu rincian laporan terakhir bertab gaya CPU-X (CPU, Memori, Grafis, Penyimpanan, Mainboard, Software, Patch).
 Sumber hulu  : DeviceController@details (report = DeviceReport terakhir pengguna atau null).
 Output       : POST devices.agent (persetujuan, mengunduh skrip dengan token sekali pakai).
 Catatan      : Nilai yang tidak terbaca tampil "Tidak dilaporkan", bukan 0. Isi laporan dari PC pengguna: selalu di-escape (Blade) dan tidak pernah dijalankan.
 Dipakai oleh : route devices.details (tombol More Details di Perangkat Saya).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'My devices'])
@section('body')
    @include('partials.flash')
    @php
        $d = $report?->payload ?? [];
        $v = fn ($x, $unit = '') => ($x === null || $x === '' || $x === []) ? 'Tidak dilaporkan' : $x.$unit;
        $kv = fn (array $pairs) => collect($pairs)->map(fn ($val, $k) => ['k' => $k, 'v' => $v($val)])->values()->all();
        $cols = ['k' => 'Item', 'v' => 'Nilai'];
        $tone = fn (float $pct) => $pct >= 90 ? 'danger' : ($pct >= 80 ? 'warn' : 'success');
    @endphp
    <div style="max-width: 1100px">
        <x-dl::button href="{{ route('devices.index') }}" variant="secondary" size="sm" icon="fa-arrow-left" label="Kembali" />

        <x-dl::divider label="Pemeriksaan lengkap" />
        <x-dl::card title="Periksa komputer ini" icon="fa-shield-halved">
            <p class="dl-note">Membaca <b>prosesor, memori, kartu grafis, disk, mainboard, sistem operasi, software, dan patch</b> komputer ini, lalu mengirimnya ke AdminKU. <b>Tidak</b> membaca isi berkas, password, atau riwayat browser. Hanya Windows, Linux, dan Mac.</p>
            <x-dl::action-bar>
                <x-dl::button type="button" id="agent-go" variant="primary" icon="fa-download" label="Setuju & Periksa Komputer Ini" />
            </x-dl::action-bar>
            <p class="dl-note" id="agent-msg">Langkah 1: klik tombol di atas. Langkah 2: buka berkas yang terunduh. Hasilnya muncul di sini otomatis.</p>
            <p class="dl-note"><a href="#" id="agent-other">Pakai Linux / Mac? Unduh versi Python</a></p>
        </x-dl::card>
        <script>
            (() => {
                const $ = (s) => document.querySelector(s), btn = $('#agent-go'), msg = $('#agent-msg'), csrf = document.querySelector('meta[name="csrf-token"]').content;
                const win = /Windows/.test(navigator.userAgent), mobile = /Android|iPhone|iPad|Mobile/.test(navigator.userAgent);
                let latest = {{ (int) ($history->first()->id ?? 0) }};
                if (mobile) { btn.disabled = true; msg.textContent = 'Telepon dan tablet tidak didukung. Buka dari komputer.'; }
                const go = async (kind) => {
                    msg.textContent = 'Menyiapkan berkas...';
                    const res = await fetch('{{ route('devices.agent') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'consent=1&kind=' + kind });
                    if (!res.ok) { msg.textContent = 'Gagal menyiapkan berkas. Coba lagi.'; return; }
                    const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await res.blob()), download: 'Periksa-Komputer-AdminKU.' + kind });
                    document.body.appendChild(a); a.click(); a.remove();
                    msg.textContent = kind === 'cmd'
                        ? 'Langkah 2: klik berkas "Periksa-Komputer-AdminKU" yang baru muncul di pojok kanan atas browser (atau di folder Downloads). Jika Windows bertanya, klik "More info" lalu "Run anyway". Jendela hitam akan terbuka sebentar dan tertutup sendiri. Menunggu hasil...'
                        : 'Langkah 2: buka Terminal lalu jalankan: python3 ~/Downloads/Periksa-Komputer-AdminKU.py. Menunggu hasil...';
                    const timer = setInterval(async () => {
                        const s = await (await fetch('{{ route('devices.status') }}')).json();
                        if (s.latest > latest) { clearInterval(timer); location.reload(); }
                    }, 3000);
                    setTimeout(() => clearInterval(timer), 30 * 60 * 1000);
                };
                btn.addEventListener('click', () => go(win ? 'cmd' : 'py'));
                $('#agent-other').addEventListener('click', (e) => { e.preventDefault(); go(win ? 'py' : 'cmd'); });
            })();
        </script>

        @if ($report)
            <x-dl::divider :label="'Laporan '.$report->checked_at->format('d/m/Y H:i').' · '.$v($report->hostname)" />
            @if ($history->count() > 1)
                <x-dl::table :columns="['date' => 'Riwayat pemeriksaan', 'host' => 'Komputer', 'cpu' => 'Prosesor', 'ram' => 'RAM']" :stack="true"
                    :rows="$history->map(fn ($h) => ['date' => ['href' => route('devices.details', ['r' => $h->id]), 'text' => $h->checked_at->format('d/m/Y H:i').($h->id === $report->id ? ' (ditampilkan)' : '')], 'host' => $v($h->hostname), 'cpu' => $v($h->cpu), 'ram' => $v($h->ram_gb, ' GB')])->all()" />
            @endif
            @php
                $cpu = $d['cpu'] ?? []; $mem = $d['memory'] ?? []; $os = $d['os'] ?? []; $b = $d['board'] ?? [];
                $disks = $d['storage'] ?? [];
                $swRows = collect($d['software'] ?? [])->map(fn ($s) => ['k' => $s['name'] ?? '-', 'v' => $v($s['version'] ?? null)])->all();
                $fixRows = collect($d['patches'] ?? [])->map(fn ($p) => ['k' => $p['id'] ?? '-', 'v' => trim(($p['installed'] ?? '').' '.($p['desc'] ?? ''))])->all();
            @endphp
            <x-dl::tabs :tabs="['cpu' => ['CPU', 'fa-microchip'], 'memory' => ['Memori', 'fa-memory'], 'graphics' => ['Grafis', 'fa-display'], 'storage' => ['Penyimpanan', 'fa-hard-drive'], 'board' => ['Mainboard', 'fa-server'], 'software' => ['Software ('.count($swRows).')', 'fa-box-open'], 'patches' => ['Patch ('.count($fixRows).')', 'fa-shield']]">
                <x-slot:cpu>
                    <x-dl::table :columns="$cols" :rows="$kv(['Nama' => $cpu['name'] ?? null, 'Pembuat' => $cpu['maker'] ?? null, 'Core' => $cpu['cores'] ?? null, 'Thread' => $cpu['threads'] ?? null,
                        'Kecepatan maks (MHz)' => $cpu['mhz'] ?? null, 'Kecepatan saat diperiksa (MHz)' => $cpu['mhz_now'] ?? null, 'Cache L2 (KB)' => $cpu['l2_kb'] ?? null, 'Cache L3 (KB)' => $cpu['l3_kb'] ?? null])" />
                </x-slot:cpu>
                <x-slot:memory>
                    <x-dl::table :columns="$cols" :rows="$kv(['Total' => $v($mem['total_gb'] ?? null, ' GB')])" />
                    <x-dl::table :columns="['slot' => 'Slot', 'size' => 'Ukuran', 'speed' => 'Kecepatan', 'maker' => 'Pembuat', 'part' => 'Nomor part']" empty="Rincian slot (SPD) tidak dilaporkan di sistem ini."
                        :rows="collect($mem['modules'] ?? [])->map(fn ($m) => ['slot' => $v($m['slot'] ?? null), 'size' => $v($m['size_gb'] ?? null, ' GB'), 'speed' => $v($m['speed'] ?? null, ' MT/s'), 'maker' => $v($m['maker'] ?? null), 'part' => $v($m['part'] ?? null)])->all()" />
                </x-slot:memory>
                <x-slot:graphics>
                    <x-dl::table :columns="['name' => 'Kartu grafis', 'ram' => 'Memori', 'driver' => 'Versi driver']" empty="Tidak dilaporkan."
                        :rows="collect($d['graphics'] ?? [])->map(fn ($g) => ['name' => $v($g['name'] ?? null), 'ram' => $v($g['ram_mb'] ?? null, ' MB'), 'driver' => $v($g['driver'] ?? null)])->all()" />
                </x-slot:graphics>
                <x-slot:storage>
                    @forelse ($disks as $disk)
                        @php $total = (float) ($disk['total_gb'] ?? 0); $used = $total - (float) ($disk['free_gb'] ?? 0); $pct = $total > 0 ? round($used / $total * 100) : 0; @endphp
                        <x-dl::progress :value="$pct" :tone="$tone($pct)" :label="'Disk '.($disk['name'] ?? '-')" :text="round($used, 1).' / '.$total.' GB ('.$pct.'%)'" />
                    @empty
                        <p class="dl-note">Tidak dilaporkan.</p>
                    @endforelse
                </x-slot:storage>
                <x-slot:board>
                    <x-dl::table :columns="$cols" :rows="$kv(['Sistem operasi' => $os['name'] ?? null, 'Versi OS' => $os['version'] ?? null, 'Arsitektur' => $os['arch'] ?? null, 'Nama komputer' => $os['hostname'] ?? null,
                        'Pembuat sistem' => $b['system_maker'] ?? null, 'Model sistem' => $b['system_model'] ?? null, 'Pembuat mainboard' => $b['maker'] ?? null, 'Model mainboard' => $b['model'] ?? null,
                        'BIOS' => $b['bios'] ?? null, 'Pembuat BIOS' => $b['bios_maker'] ?? null])" />
                </x-slot:board>
                <x-slot:software>
                    <x-dl::table :columns="['k' => 'Nama', 'v' => 'Versi']" :rows="$swRows" empty="Tidak dilaporkan di sistem ini." />
                </x-slot:software>
                <x-slot:patches>
                    <x-dl::table :columns="['k' => 'Patch', 'v' => 'Dipasang']" :rows="$fixRows" empty="Tidak dilaporkan di sistem ini." />
                </x-slot:patches>
            </x-dl::tabs>
        @else
            <x-dl::empty-state title="Belum ada pemeriksaan" message="Setujui dan jalankan skrip di atas; hasilnya tampil di sini sebagai tab CPU, Memori, Grafis, Penyimpanan, Mainboard, Software, dan Patch." />
        @endif
    </div>
@endsection
