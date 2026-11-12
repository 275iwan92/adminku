{{-- REMARK
 Tujuan       : Daftar penerbitan Kedisiplinan (modul HRD): nomor surat, tanggal, karyawan, bagian, jenis (SP 1 / SP 2 / SP 3 / PHK), berlaku s/d, status (Aktif / Diganti / Kedaluwarsa / Dicabut / Terbit) dan alasan.
                Dua bagian: penerbitan BULAN INI sebagai kartu pita berwarna per jenis (SP 1 kuning, SP 2 jingga, SP 3 merah muda tua, PHK merah), dan BULAN SEBELUMNYA sebagai tabel yang bisa disaring bulan demi bulan (filter Bulan).
 Sumber hulu  : DisciplineController@index.
 Input        : current (penerbitan bulan berjalan), history (bulan-bulan sebelumnya), monthName.
 Output       : dua x-dl2::grid; klik 2x membuka penerbitan (detail, cetak, cabut).
 Dipakai oleh : route disciplines.index (menu HR > Kedisiplinan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kedisiplinan'])
@section('body')
    @include('partials.flash')
    <style>
        /* Warna SP tegas (bukan pastel): kepala kartu solid dan lencana Jenis solid. SP 1 kuning, SP 2 jingga, SP 3 merah muda tua, PHK merah.
           DevLib 2 belum punya nada yellow / tangerine / pink; kelas lencananya hanya dipakai di halaman ini. */
        #sp-vivid .dl2-g88-band-head{--bc:hsl(var(--h) 90% 46%)}
        #sp-vivid .dl2-g88-band-head{background:var(--bc);color:#fff;text-shadow:0 1px 1px rgba(0,0,0,.35)}
        #sp-vivid .dl2-g88-band-big strong{color:hsl(var(--h) 90% 38%)}
        #sp-vivid .dl2-badge-yellow{--b:hsl(48 96% 50%);--bi:#3a2d00}
        #sp-vivid .dl2-badge-tangerine{--b:hsl(28 95% 52%);--bi:#fff}
        #sp-vivid .dl2-badge-pink{--b:hsl(330 80% 42%);--bi:#fff}
        #sp-vivid .dl2-badge-danger{--b:hsl(0 78% 48%);--bi:#fff}
    </style>
    <div id="sp-vivid">
    @php
        $columns = [
            ['key' => 'doc_no', 'label' => 'No. Surat'],
            ['key' => 'issued_on', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'employee', 'label' => 'Karyawan', 'type' => 'identity', 'code' => 'code', 'box' => false],
            ['key' => 'department', 'label' => 'Bagian'],
            ['key' => 'type', 'label' => 'Jenis', 'type' => 'badge', 'toneKey' => 'tone'],
            ['key' => 'until_on', 'label' => 'Berlaku s/d', 'type' => 'date'],
            ['key' => 'state', 'label' => 'Status'],
            ['key' => 'reason', 'label' => 'Alasan'],
        ];
        $head = 'margin:14px 4px 6px;font-weight:700;color:var(--dl2-ink-soft)';
    @endphp

    <div style="{{ $head }}">Bulan ini &middot; {{ $monthName }}</div>
    <x-dl2::grid id="disciplines-month" :rows="$current" actions="open" :open-url="url('disciplines/{id}')"
        :new-url="auth()->user()->canWrite('HRD') ? route('disciplines.create') : null"
        search="Cari nomor surat, karyawan, alasan..."
        :cards="['style' => 'band', 'start' => true, 'title' => 'employee', 'sub' => 'doc_no', 'icon' => 'icon', 'hue' => 'hue', 'big' => 'type', 'meta' => ['department', 'state']]"
        :columns="$columns" />

    <div style="{{ $head }}">Bulan sebelumnya</div>
    <x-dl2::grid id="disciplines-history" :rows="$history" actions="open" :open-url="url('disciplines/{id}')" :new-record="null"
        search="Cari nomor surat, karyawan, alasan..."
        :phone-cards="['title' => 'employee', 'sub' => 'doc_no', 'meta' => ['type', 'state', 'department']]"
        :columns="$columns"
        :filters="['month' => 'Bulan', 'type' => 'Jenis', 'state' => 'Status', 'department' => 'Bagian']"
        :stats="[['label' => 'Semua penerbitan'], ['label' => 'Berlaku sekarang', 'where' => ['now' => true], 'tone' => 'warn']]" />
    </div>
@endsection
