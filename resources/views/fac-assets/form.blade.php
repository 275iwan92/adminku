{{-- REMARK
 Tujuan       : Form master Aset (Single Form kind=master, multipart): kode otomatis AST-nnnn (tidak berubah), nama, kategori, gedung/ruang, tanggal beli, nilai, garansi s.d., kondisi, penanggung jawab, foto opsional, catatan.
                Tombol ke Kartu Aset (riwayat perawatan dan total biaya) dan Lapor Kerusakan (tiket baru dari aset ini).
 Sumber hulu  : FacAssetController (asset, categories, places).
 Input        : asset, categories, places.
 Output       : POST fac-assets.store (baru) / PUT fac-assets.update (ubah, Aktifkan, Nonaktifkan).
 Dipakai oleh : routes fac-assets.create, fac-assets.show.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Aset'])
@section('body')
    @php
        use App\Services\FacCare;
        use App\Services\FacCustody;
        $isNew = ! $asset->exists;
        $u = auth()->user();
        $v = fn (string $k) => old($k, $asset->{$k});
        $d = fn (string $k) => ($x = old($k, $asset->{$k})) ? \Illuminate\Support\Carbon::parse($x)->format('Y-m-d') : null;
        $e = fn (string $k) => $errors->first($k) ?: null;
    @endphp
    @include('partials.flash')
    @if (! $isNew)
        <x-dl::action-bar align="start">
            @if ($u->canAccess('FAM'))
                <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-clipboard-list" label="Kartu Aset" :href="route('fac-care.show', $asset)" />
            @endif
            @if ($u->canWrite('FAH') && $asset->is_active)
                @if ($asset->position === 'office')
                    <x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-hand-holding" label="Pinjamkan" :href="route('fac-handovers.create', ['asset' => $asset->id, 'type' => 'loan'])" />
                    <x-dl::button class="sstock-btn-amber" variant="secondary" icon="fa-house-user" label="Bawa Pulang" :href="route('fac-handovers.create', ['asset' => $asset->id, 'type' => 'home'])" />
                @else
                    <x-dl::button class="sstock-btn-teal" variant="secondary" icon="fa-rotate-left" label="Kembalikan" :href="route('fac-handovers.create', ['asset' => $asset->id, 'type' => 'return'])" />
                @endif
                @if ($asset->position !== 'lost')
                    <x-dl::button class="sstock-btn-coral" variant="secondary" icon="fa-circle-question" label="Lapor Hilang" :href="route('fac-handovers.create', ['asset' => $asset->id, 'type' => 'lost'])" />
                @endif
            @endif
            @if ($u->canWrite('FAT') && $asset->is_active)
                <x-dl::button class="sstock-btn-coral" variant="secondary" icon="fa-triangle-exclamation" label="Lapor Kerusakan" :href="route('fac-orders.create', ['asset' => $asset->id])" />
            @endif
        </x-dl::action-bar>
    @endif
    @if (! $isNew && $asset->position !== 'office')
        <x-dl::alert :tone="$asset->overdue() || $asset->position === 'lost' ? 'danger' : 'warn'" :message="raw(FacCustody::POSITIONS[$asset->position].($asset->holderText() ? ' · pemegang '.$asset->holderText() : '').($asset->due_back ? ' · wajib kembali '.$asset->due_back->format('d-m-Y').($asset->overdue() ? ' (TERLAMBAT '.$asset->due_back->diffInDays(today()).' hari)' : '') : ''))" />
    @endif
    <x-dl::form :title="$isNew ? 'Aset · Baru' : 'Aset · '.raw($asset->asset_code)" :subtitle="$isNew ? null : raw($asset->name)" panel="top" kind="master"
        :status="$isNew ? 'new' : ($asset->is_active ? 'active' : 'inactive')" :back="route('fac-assets.index')" :new-url="route('fac-assets.create')" enctype="multipart/form-data"
        :action="$isNew ? route('fac-assets.store') : route('fac-assets.update', $asset)" :hidden="$isNew ? [] : ['_method' => 'PUT']"
        :created-at="$asset->created_at?->format('Y-m-d H:i:s')" :updated-at="$asset->updated_at?->format('Y-m-d H:i:s')">
        <x-dl::form.row :cols="3">
            <x-dl::field.display label="Kode Aset" :value="raw($asset->asset_code ?? '(otomatis)')" />
            <x-dl::field.text name="name" label="Nama Aset" :value="$v('name')" required maxlength="150" :error="$e('name')" />
            <x-dl::field.select name="category_id" label="Kategori" :options="$categories" :value="$v('category_id')" required :error="$e('category_id')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="place_id" label="Gedung / ruang" :options="['' => '(belum ditentukan)'] + $places" :value="$v('place_id')" :error="$e('place_id')" />
            <x-dl::field.select name="cond" label="Kondisi" :options="FacCare::CONDITIONS" :value="$v('cond')" required :error="$e('cond')" />
            <x-dl::field.text name="pic_name" label="Penanggung jawab" :value="$v('pic_name')" maxlength="100" :error="$e('pic_name')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="bought_at" label="Tanggal beli" :value="$d('bought_at')" :error="$e('bought_at')" />
            <x-dl::field.decimal name="price" label="Nilai (Rp)" :decimals="2" :value="$v('price')" :error="$e('price')" />
            <x-dl::field.date name="warranty_until" label="Garansi s.d." :value="$d('warranty_until')" :error="$e('warranty_until')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.toggle name="monitored" label="Perlu dimonitor" on-label="Dimonitor GA" off-label="Biasa" :value="(bool) old('monitored', $asset->monitored)" hint="Untuk barang kecil tidak habis pakai (kalkulator, stapler, laptop): dipantau pemegang dan pengembaliannya." />
            @if ($isNew)
                <x-dl::field.number name="copies" label="Jumlah unit" :value="old('copies', 1)" :error="$e('copies')" hint="Isi lebih dari 1 untuk membuat beberapa aset sejenis sekaligus (masing-masing berkode sendiri)." />
            @endif
        </x-dl::form.row>
        <x-dl::form.row :cols="2">
            <x-dl::field.upload name="photo" label="Foto (opsional)" accept=".jpg,.jpeg,.png,image/jpeg,image/png" :preview="true" :value="$asset->photo_path ? ['url' => route('fac-assets.photo', $asset), 'name' => $asset->photo_name] : null" :error="$e('photo')" />
            <x-dl::field.text name="note" label="Catatan" :value="$v('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
        @if ($asset->photo_path)
            <x-dl::form.row>
                <x-dl::field.toggle name="remove_photo" label="Hapus Foto" on-label="Hapus saat disimpan" off-label="Pertahankan" :value="(bool) old('remove_photo')" />
            </x-dl::form.row>
        @endif
    </x-dl::form>
@endsection
