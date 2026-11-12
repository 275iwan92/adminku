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
    <x-dl2::form :title="$isNew ? 'Pemasok · Baru' : 'Pemasok · '.raw($supplier->code)" :subtitle="$isNew ? null : raw($supplier->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($supplier->is_active ? 'active' : 'inactive')" :back="route('suppliers.index')" :new-url="route('suppliers.create')"
        :action="$isNew ? route('suppliers.store') : route('suppliers.update', $supplier)" :method="$isNew ? 'post' : 'put'"
        :tabs="['data' => 'Data'] + ($isNew ? [] : ['history' => 'Riwayat beli'])"
        :hide="$supplier->is_system ? ['deactivate', 'activate'] : []" :created-at="$supplier->created_at?->format('Y-m-d H:i:s')" :updated-at="$supplier->updated_at?->format('Y-m-d H:i:s')">
        <x-slot:data>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="code" label="Supplier Code" :value="$v('code')" code :case-toggle="false" required :readonly="! $isNew" maxlength="20" :error="$e('code')" hint="Unique. Cannot be changed after saving." />
            <x-dl2::field.text name="name" label="Supplier Name" :value="$v('name')" required :readonly="(bool) $supplier->is_system" maxlength="150" :error="$e('name')" />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.phone name="phone" label="Company Phone (PSTN)" :value="$v('phone')" :error="$e('phone')" hint="Landline, with area code." />
            <x-dl2::field.text name="contact_name" label="Contact Name" :value="$v('contact_name')" maxlength="100" :error="$e('contact_name')" />
            <x-dl2::field.phone name="contact_mobile" label="Contact Mobile (smartphone)" :value="$v('contact_mobile')" :error="$e('contact_mobile')" hint="Also used for WhatsApp." />
        </x-dl2::form.row>
        <x-dl2::form.row :cols="3">
            <x-dl2::field.text name="email" label="Email" :value="$v('email')" maxlength="150" :error="$e('email')" hint="Tampil di cetakan PO." />
            <x-dl2::field.number name="payment_term_days" label="Payment Term (days)" :min="0" :max="365" :value="$v('payment_term_days')" :readonly="(bool) $supplier->is_system" :error="$e('payment_term_days')" hint="0 = tunai. Nilai awal PO." />
            <x-dl2::field.text name="note" label="Note" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.text name="address" label="Street Address" :value="$v('address')" maxlength="255" :error="$e('address')" />
        </x-dl2::form.row>
        <x-dl2::form.row>
            <x-dl2::field.lookup name="area" lookup="geo_area" label="District, City / Regency, Province, Country" placeholder="Search district / city / postcode..." :value="old('area', $supplier->areaKey())" :required="App\Support\GeoArea::available()" :error="$e('area')" hint="One choice gives district, city / regency, province, country, and the postal code." />
        </x-dl2::form.row>
        </x-slot:data>
        @unless ($isNew)
            <x-slot:history>
                <table class="dl2-table" style="width:100%">
                    <thead><tr><th>Barang</th><th>Beli terakhir</th><th style="text-align:right">Harga terakhir</th><th style="text-align:right">Berapa kali</th></tr></thead>
                    <tbody>
                    @forelse ($history as $h)
                        <tr><td>{{ $h->code }} {{ $h->name }}</td><td>{{ \Illuminate\Support\Carbon::parse($h->last_at)->format('d-m-Y') }}</td><td style="text-align:right">{{ number_format($h->last_price, 0, ',', '.') }} / {{ $h->unit }}</td><td style="text-align:right">{{ $h->times }}x</td></tr>
                    @empty
                        <tr><td colspan="4">Belum ada barang yang diterima dari pemasok ini.</td></tr>
                    @endforelse
                    </tbody>
                </table>
                <p class="ak-note">Dari Penerimaan Barang terkonfirmasi yang tidak dibatalkan; harga dalam satuan stok barang.</p>
            </x-slot:history>
        @endunless
    </x-dl2::form>
@endsection
