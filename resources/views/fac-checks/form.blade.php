{{-- REMARK
 Tujuan       : Form Opname Aset: langkah 1 pilih cakupan (orang atau ruang) lalu Tampilkan; langkah 2 daftar aset yang seharusnya ada, centang yang ditemukan secara fisik, isi tanggal dan catatan, Simpan. Yang tidak dicentang menjadi temuan.
 Sumber hulu  : FacCheckController@create (emp, place, assets, employees, places).
 Input        : emp, place, assets, employees, places.
 Output       : GET (pilih cakupan) dan POST fac-checks.store.
 Dipakai oleh : route fac-checks.create.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Opname Aset'])
@section('body')
    @php use App\Services\FacCustody; @endphp
    @include('partials.flash')
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-checks.index')" />
    </x-dl::action-bar>
    <x-dl::action-form method="get" :action="route('fac-checks.create')">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="employee" label="Per orang (yang dipegang / dibawa)" :options="['' => '(tidak)'] + $employees" :value="$emp ?: ''" />
            <x-dl::field.select name="place" label="Per ruang (yang seharusnya di kantor)" :options="['' => '(tidak)'] + $places" :value="$emp ? '' : ($place ?: '')" />
        </x-dl::form.row>
        <x-dl::action-bar><x-dl::button type="submit" icon="fa-eye" label="Tampilkan aset" size="sm" /></x-dl::action-bar>
    </x-dl::action-form>
    @if ($emp || $place)
        @if ($assets->isEmpty())
            <p class="dl-note">Tidak ada aset yang tercatat untuk cakupan ini.</p>
        @else
            <form method="post" action="{{ route('fac-checks.store') }}" class="fac-check">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $emp }}"><input type="hidden" name="place_id" value="{{ $emp ? '' : $place }}">
                <style>
                    .fac-check table { width: 100%; border-collapse: collapse; margin: 10px 0 }
                    .fac-check th, .fac-check td { padding: 8px 10px; border-bottom: 1px solid var(--dl-border-soft); text-align: left }
                    .fac-check tr.is-miss td { background: var(--dl-danger-soft) }
                    .fac-check input[type=date], .fac-check input[type=text] { padding: 6px 10px; border: 1px solid var(--dl-border); border-radius: 8px; background: var(--dl-surface); color: var(--dl-ink); font: inherit }
                </style>
                <p><label>Tanggal <input type="date" name="checked_at" value="{{ old('checked_at', today()->toDateString()) }}" required></label>
                    <label>Catatan <input type="text" name="note" maxlength="255" value="{{ old('note') }}"></label></p>
                @error('checked_at')<p class="dl-note">{{ $message }}</p>@enderror
                <table>
                    <thead><tr><th>Ditemukan</th><th>Kode</th><th>Aset</th><th>Kategori</th><th>Lokasi / posisi</th></tr></thead>
                    <tbody>
                        @foreach ($assets as $a)
                            <tr>
                                <td><input type="hidden" name="asset[]" value="{{ $a->id }}"><input type="checkbox" name="found[]" value="{{ $a->id }}" checked aria-label="Ditemukan {{ $a->asset_code }}"></td>
                                <td>{{ $a->asset_code }}</td><td>{{ $a->name }}</td><td>{{ $a->category->name }}</td><td>{{ $a->place?->path() ?? '-' }} · {{ FacCustody::POSITIONS[$a->position] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="dl-note">Hilangkan centang pada aset yang tidak ada saat dicek. Yang tidak ditemukan tampil di Perlu tindakan sampai pemeriksaan berikutnya menemukannya.</p>
                <x-dl::button type="submit" icon="fa-check" label="Simpan opname" />
            </form>
        @endif
    @endif
@endsection
