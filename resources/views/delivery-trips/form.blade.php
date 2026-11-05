{{-- REMARK
 Tujuan       : Form Pengiriman (trip): kepala (nomor, tanggal, kendaraan, sopir, asisten, jam berangkat, menit pulang, catatan) + planner alamat (delivery-trips/planner). Siklus tombol DevLib: Draft (Save, Confirm = Tugaskan, Delete) > Ditugaskan (tampil Confirmed, terkunci; Cancel hanya Supervisor) | Cancelled.
 Sumber hulu  : DeliveryTripController (trip, stops, docs, vehicles, log).
 Input        : trip, stops, docs, vehicles, log.
 Output       : POST trips.store (baru = draf) / PUT trips.update (save, confirm, cancel, delete); stops_json dari planner.
 Dipakai oleh : routes trips.create, trips.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pengiriman'])
@section('body')
    @php
        $isNew = ! $trip->exists;
        $me = auth()->user();
        $now = $isNew ? 'new' : ($trip->status === 'assigned' ? 'confirmed' : $trip->status);
        $locked = ! $isNew && $trip->status !== 'draft';
        $v = fn (string $k) => old($k, $trip->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
        $hide = array_merge(['hold', 'email', 'new', 'undo', 'duplicate', 'print'], $me->isSupervisor() ? [] : ['cancel']);
    @endphp
    @include('partials.flash')
    @error('stops_json')
        <x-dl::alert tone="danger" :message="raw($message)" />
    @enderror
    @if ($trip->status === 'assigned' && $trip->token)
        <x-dl::alert tone="info" :message="'Tautan untuk sopir (kirim ke HP sopir, tanpa login): '.route('driver.show', $trip->token)" />
    @endif
    @if ($now === 'cancelled')
        <x-dl::alert tone="warn" :message="raw('Trip dibatalkan'.($trip->reason ? '. Alasan: '.$trip->reason : '').'. Surat jalannya bebas dipakai lagi.')" />
    @endif
    <x-dl::form :title="'Pengiriman · '.($isNew ? 'Baru' : raw($trip->number()))" :subtitle="$isNew ? null : raw($trip->driver)" panel="top" kind="document" :status="$now"
        :back="route('trips.index')" :new-url="route('trips.create')" :log="$log"
        :action="$isNew ? route('trips.store') : route('trips.update', $trip)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :pipeline="true" :hide="$hide"
        :created-by="$trip->creator?->name" :created-at="$trip->created_at?->format('Y-m-d H:i:s')" :updated-at="$trip->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Document No." :value="raw($trip->doc_no ?? '(otomatis)')" />
            <x-dl::field.date name="trip_date" label="Tanggal trip" :value="($d = $v('trip_date')) ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d') : null" required :error="$e('trip_date')" />
            <x-dl::field.select name="vehicle_id" label="Kendaraan" :options="['' => 'Pilih kendaraan...'] + $vehicles" :value="$v('vehicle_id')" required :error="$e('vehicle_id')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="driver" label="Sopir" :value="$v('driver')" required maxlength="100" :error="$e('driver')" />
            <x-dl::field.text name="assistant" label="Asisten (kosong = sopir sendiri)" :value="$v('assistant')" maxlength="100" :error="$e('assistant')" />
            <x-dl::field.text name="depart_time" label="Jam berangkat (JJ:MM)" :value="$v('depart_time')" required maxlength="5" :error="$e('depart_time')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="return_min" label="Menit pulang ke kantor" :value="$v('return_min')" maxlength="3" :error="$e('return_min')" />
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
        <x-slot:detail>
            @include('delivery-trips.planner', ['locked' => $locked])
        </x-slot:detail>
    </x-dl::form>
@endsection
