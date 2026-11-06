{{-- REMARK
 Tujuan       : Tombol Kembali dari grid master yang dibuka lewat hub Setup (Kendaraan atau AC) ke hub-nya; tampil hanya bila pengguna berhak atas hub itu (selain itu grid berdiri sendiri di menu samping).
 Input        : $hub ('fleet' | 'ac').
 Output       : x-dl::action-bar dengan tombol Kembali ke fleet.setup atau ac-setup.index.
 Dipakai oleh : grid vehicles, vehicle-types, ac-units, ac-areas, ac-rooms, ac-vendors.
 Status       : Aktif.
--}}
@php($hubs = ['fleet' => ['GVM', 'fleet.setup'], 'ac' => ['GAM', 'ac-setup.index'], 'fac' => ['FAM', 'fac-setup.index']])
@if (auth()->user()->canAccess($hubs[$hub][0]))
    <x-dl::action-bar align="start">
        <x-dl::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route($hubs[$hub][1])" />
    </x-dl::action-bar>
@endif
