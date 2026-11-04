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
        <x-dl::card title="Apa yang diperiksa" icon="fa-shield-halved">
            <p class="dl-note">Skrip kecil dijalankan sekali di komputer ini atas persetujuan Anda. Ia membaca <b>prosesor, memori, kartu grafis, disk, mainboard/BIOS, sistem operasi, software terpasang, dan patch</b>, lalu mengirimnya ke AdminKU dan berhenti.
                Ia <b>tidak</b> membaca isi berkas, password, atau riwayat browser. Telepon/tablet tidak didukung (hanya Windows, Linux, Mac).</p>
            <x-dl::action-form :action="route('devices.agent')">
                <input type="hidden" name="consent" value="1">
                <x-dl::action-bar>
                    <x-dl::button type="submit" name="kind" value="cmd" variant="primary" icon="fa-laptop" label="Setuju & Unduh untuk Windows" />
                    <x-dl::button type="submit" name="kind" value="py" variant="secondary" icon="fa-download" label="Setuju & Unduh untuk Linux / Mac" />
                </x-dl::action-bar>
            </x-dl::action-form>
            <p class="dl-note">Windows: klik dua kali <code>adminku-perangkat.cmd</code> (tanpa Python; jika muncul peringatan SmartScreen pilih "Run anyway"). Linux / Mac: <code>python3 adminku-perangkat.py</code>. Berlaku 30 menit dan hanya sekali pakai. Lalu muat ulang layar ini.</p>
        </x-dl::card>

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
