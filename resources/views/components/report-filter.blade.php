{{-- REMARK
 Tujuan       : Bilah saring laporan (GET): periode dari-sampai, kolom tambahan lewat slot, tombol Show. Hasil ada di query string sehingga laporan bisa di-refresh / dibagikan.
 Input        : from, to (Carbon); slot = field tambahan.
 Output       : x-dl::action-form method GET.
 Dipakai oleh : semua view reports.*.
 Status       : Aktif.
--}}
@props(['from', 'to', 'apply' => 'Show'])
<x-dl::action-form method="get" :action="url()->current()" class="dl-report-filter">
    <x-dl::form.row :cols="4">
        <x-dl::field.date name="from" label="From date" :value="$from->format('Y-m-d')" />
        <x-dl::field.date name="to" label="To date" :value="$to->format('Y-m-d')" />
        {{ $slot }}
    </x-dl::form.row>
    <x-dl::action-bar>
        <x-dl::button type="submit" icon="fa-eye" :label="$apply" size="sm" />
    </x-dl::action-bar>
</x-dl::action-form>
