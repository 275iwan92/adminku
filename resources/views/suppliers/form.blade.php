{{-- REMARK
 Tujuan       : Form master Supplier (Single Form kind=master): kode, nama, telepon kantor (PSTN), nama kontak + HP kontak, alamat jalan, lalu satu kotak cari wilayah (kelurahan / kecamatan / kota; tampil lengkap sampai negara + kode pos; wajib minimal sampai kota bila data wilayah sudah diimpor; bila tabel wilayah kosong, opsional).
 Sumber hulu  : SupplierController (supplier), Lookup geo_area (App\Support\GeoLookup). Kode pos disimpan server dari wilayah terpilih.
 Input        : supplier.
 Output       : POST suppliers.store (baru) / PUT suppliers.update (ubah, Activate, Deactivate).
 Dipakai oleh : routes suppliers.create, suppliers.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pemasok'])
@section('body')
    @php
        $isNew = ! $supplier->exists;
        $v = fn (string $k) => old($k, $supplier->{$k});
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    <x-dl::form :title="$isNew ? 'Pemasok · Baru' : 'Pemasok · '.raw($supplier->code)" :subtitle="$isNew ? null : raw($supplier->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($supplier->is_active ? 'active' : 'inactive')" :back="route('suppliers.index')" :new-url="route('suppliers.create')"
        :action="$isNew ? route('suppliers.store') : route('suppliers.update', $supplier)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :hide="$supplier->is_system ? ['deactivate', 'activate'] : []" :created-at="$supplier->created_at?->format('Y-m-d H:i:s')" :updated-at="$supplier->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="code" label="Supplier Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unique. Cannot be changed after saving." />
            <x-dl::field.text name="name" label="Supplier Name" :value="$v('name')" required :readonly="(bool) $supplier->is_system" maxlength="150" :error="$e('name')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.phone name="phone" label="Company Phone (PSTN)" :value="$v('phone')" :error="$e('phone')" hint="Landline, with area code." />
            <x-dl::field.text name="contact_name" label="Contact Name" :value="$v('contact_name')" maxlength="100" :error="$e('contact_name')" />
            <x-dl::field.phone name="contact_mobile" label="Contact Mobile (smartphone)" :value="$v('contact_mobile')" :error="$e('contact_mobile')" hint="Also used for WhatsApp." />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.text name="address" label="Street Address" :value="$v('address')" maxlength="255" :error="$e('address')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.lookup name="area" lookup="geo_area" label="District, City / Regency, Province, Country" placeholder="Search district / city / postcode..." :value="old('area', $supplier->areaKey())" :required="App\Support\GeoArea::available()" :error="$e('area')" hint="One choice gives district, city / regency, province, country, and the postal code." />
        </x-dl::form.row>
    </x-dl::form>
@endsection
