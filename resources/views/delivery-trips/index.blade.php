{{-- REMARK
 Tujuan       : Daftar trip Pengiriman: nomor, tanggal, kendaraan, crew, jumlah alamat, status.
 Sumber hulu  : DeliveryTripController@index.
 Input        : rows.
 Output       : x-dl2::grid; klik 2x membuka planner trip.
 Dipakai oleh : route trips.index (menu Pengiriman > Perencanaan Pengiriman).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pengiriman'])
@section('body')
    @php $tones = ['Ditugaskan' => 'success', 'Draf' => 'neutral', 'Dibatalkan' => 'danger']; @endphp
    @include('partials.flash')
    <x-dl2::grid id="delivery-trips" :rows="$rows" actions="worklist" :open-url="url('trips/{id}')" :new-url="auth()->user()->canWrite('PGK') ? route('trips.create') : null"
        search="Cari nomor trip, plat, sopir..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'crew', 'badge' => 'state', 'tones' => $tones, 'meta' => ['trip_date', 'vehicle', 'stops']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'No. Trip', 'type' => 'identity', 'code' => 'crew', 'avatar' => false],
            ['key' => 'trip_date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'vehicle', 'label' => 'Kendaraan'],
            ['key' => 'stops', 'label' => 'Alamat', 'type' => 'number'],
            ['key' => 'state', 'label' => 'Status', 'type' => 'badge', 'tones' => $tones],
        ]"
        :filters="['state' => 'Status', 'vehicle' => 'Kendaraan']"
        :stats="[['label' => 'Semua trip']]" />
@endsection
