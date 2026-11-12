{{-- REMARK
 Tujuan       : Bilah saring laporan (GET): periode dari-sampai (dua tanggal ringkas dalam satu sel), kolom tambahan lewat slot (sel sama lebar, dua kolom), tombol Tampilkan bujur sangkar di kanan. Hasil ada di query string sehingga laporan bisa di-refresh / dibagikan.
 Input        : from, to (Carbon); slot = field tambahan.
 Output       : x-dl2::action-form method GET.
 Dipakai oleh : semua view reports.*.
 Status       : Aktif.
--}}
@props(['from', 'to', 'apply' => 'Show'])
<x-dl2::action-form method="get" :action="url()->current()" class="ak-report-filter">
    <x-dl2::form.row :cols="4">
        <x-dl2::form.row class="ak-report-filter__dates">
            <x-dl2::field.date name="from" label="From date" :value="$from->format('Y-m-d')" />
            <x-dl2::field.date name="to" label="To date" :value="$to->format('Y-m-d')" />
        </x-dl2::form.row>
        {{ $slot }}
    </x-dl2::form.row>
    <x-dl2::action-bar>
        <x-dl2::button type="submit" icon="fa-eye" :label="$apply" size="sm" />
    </x-dl2::action-bar>
</x-dl2::action-form>
