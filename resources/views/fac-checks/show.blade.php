{{-- REMARK
 Tujuan       : Hasil satu Opname Aset (hanya baca): cakupan, tanggal, dan tiap aset Ditemukan / Tidak ditemukan.
 Sumber hulu  : FacCheckController@show (c, lines, who).
 Input        : c, lines, who.
 Output       : tabel hasil; aset tidak ditemukan ditandai dan ditautkan ke Serah Terima.
 Dipakai oleh : route fac-checks.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Opname Aset'])
@section('body')
    @php $u = auth()->user(); @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-checks.index')" />
    </x-dl::action-bar>
    <h2 style="margin:6px 0 2px">Opname Aset · {{ substr($c->checked_at, 0, 10) }}</h2>
    <p class="dl-note">{{ $c->employee_id ? 'Orang' : 'Ruang' }}: {{ $who }}@if ($c->note) · {{ $c->note }}@endif</p>
    <x-dl::grid id="fac-check-lines" :rows="$lines->map(fn ($l) => ['id' => $l->asset_id, 'code' => $l->asset_code, 'name' => $l->name, 'result' => $l->found ? 'Ditemukan' : 'Tidak ditemukan'])->all()" actions="none" :new-record="null" search="Cari aset..."
        :columns="[
            ['key' => 'code', 'label' => 'Kode', 'type' => 'identity', 'avatar' => false],
            ['key' => 'name', 'label' => 'Aset'],
            ['key' => 'result', 'label' => 'Hasil', 'type' => 'badge', 'tones' => ['Ditemukan' => 'success', 'Tidak ditemukan' => 'danger']],
        ]"
        :phone-cards="['title' => 'name', 'sub' => 'code', 'badge' => 'result', 'tones' => ['Ditemukan' => 'success', 'Tidak ditemukan' => 'danger']]" />
    @if ($u->canWrite('FAH') && $lines->contains(fn ($l) => ! $l->found))
        <p class="dl-note">Tindak lanjut aset yang tidak ditemukan: buat Serah Terima jenis <i>Pengembalian</i> atau <i>Laporan hilang</i> dari Master Aset.</p>
    @endif
@endsection
