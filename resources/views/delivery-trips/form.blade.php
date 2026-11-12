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
        <x-dl2::alert tone="danger" :message="raw($message)" />
    @enderror
    @if ($trip->status === 'assigned' && $trip->token)
        <x-dl2::alert tone="info" :message="'Tautan untuk sopir (kirim ke HP sopir, tanpa login): '.route('driver.show', $trip->token)" />
    @endif
    @if ($now === 'cancelled')
        <x-dl2::alert tone="warn" :message="raw('Trip dibatalkan'.($trip->reason ? '. Alasan: '.$trip->reason : '').'. Surat jalannya bebas dipakai lagi.')" />
    @endif
    @if ($trip->status === 'assigned')
        @vite('resources/js/trip-map.js')
        <div class="dl2-card" id="driver-live" data-url="{{ route('trips.position', $trip) }}" style="margin-bottom:12px">
            <div class="sp-mapbar"><strong>Posisi sopir</strong><span class="sp-s" id="driver-live-note">Belum ada posisi (sopir belum membagikan lokasi dari HP).</span></div>
            <div class="sp-map" id="driver-live-map"></div>
        </div>
        <script>
            window.akReady(() => {
                const box = document.getElementById('driver-live'), note = document.getElementById('driver-live-note');
                const live = window.TripMap.live(document.getElementById('driver-live-map'), @js(config('sstock.office_geo')));
                const poll = async () => {
                    try {
                        const p = await (await fetch(box.dataset.url, { headers: { Accept: 'application/json' } })).json();
                        if (p.lat !== null) { live.set(p.lat, p.lng); note.textContent = 'Terakhir terlihat ' + p.at + ' (' + p.ago + ')'; }
                    } catch (e) { /* jaringan putus: coba lagi di putaran berikut */ }
                };
                poll(); setInterval(poll, 30000);
            });
        </script>
    @endif
    <x-dl2::form :title="'Pengiriman · '.($isNew ? 'Baru' : raw($trip->number()))" :subtitle="$isNew ? null : raw($trip->driver)" panel="top" kind="document" :status="$now"
        :back="route('trips.index')" :new-url="route('trips.create')" :log="$log"
        :action="$isNew ? route('trips.store') : route('trips.update', $trip)" :method="$isNew ? 'post' : 'put'"
        :pipeline="true" :hide="$hide"
        :created-by="$trip->creator?->name" :created-at="$trip->created_at?->format('Y-m-d H:i:s')" :updated-at="$trip->updated_at?->format('Y-m-d H:i:s')">
        <x-dl2::form.row :cols="3">
            <x-dl2::field.display label="Document No." :value="raw($trip->doc_no ?? '(otomatis)')" />
            <x-dl2::field.date name="trip_date" label="Tanggal trip" :value="($d = $v('trip_date')) ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d') : null" required :error="$e('trip_date')" />
            <x-dl2::field.select name="vehicle_id" label="Kendaraan" :options="['' => 'Pilih kendaraan...'] + $vehicles" :value="$v('vehicle_id')" required :error="$e('vehicle_id')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="driver" label="Sopir" :value="$v('driver')" required maxlength="100" :error="$e('driver')" />
            <x-dl2::field.text name="assistant" label="Asisten (kosong = sopir sendiri)" :value="$v('assistant')" maxlength="100" :error="$e('assistant')" />
            <x-dl2::field.text name="depart_time" label="Jam berangkat (JJ:MM)" :value="$v('depart_time')" required maxlength="5" :error="$e('depart_time')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="return_min" label="Menit pulang ke kantor" :value="$v('return_min')" maxlength="3" :error="$e('return_min')" />
            <x-dl2::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl2::form.row>
        
            @include('delivery-trips.planner', ['locked' => $locked])
        
    </x-dl2::form>
@endsection
