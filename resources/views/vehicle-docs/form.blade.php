{{-- REMARK
 Tujuan       : Form Perpanjang Dokumen Kendaraan (GA, modul GVD; Single Form kind=master status baru): kendaraan, jenis dokumen (KIR dinonaktifkan untuk kendaraan tidak wajib KIR), tanggal bayar / uji (bawaan hari ini),
                Berlaku sampai (terisi otomatis = tanggal + interval jenis dokumen, bisa diubah), no. dokumen, biaya, lampiran foto / PDF, catatan.
 Sumber hulu  : VehicleDocController@create (vehicles, selected, kir, docs, months, back).
 Input        : vehicles (id => label), selected, kir (id => wajib KIR), docs (jenis => label), months (jenis => bulan), back.
 Output       : POST vehicle-docs.store (multipart).
 Dipakai oleh : route vehicle-docs.create (menu GA > Perpanjang Dokumen Kendaraan; tombol di Detail kendaraan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Perpanjang Dokumen Kendaraan'])
@section('body')
    @php
        $e = fn (string $k) => $errors->first($k) ?: null;
        $type = is_string(old('doc_type')) && isset($docs[old('doc_type')]) ? old('doc_type') : 'pajak';
        $paid = is_string(old('paid_at')) ? old('paid_at') : today()->format('Y-m-d');
        $until = old('valid_until', rescue(fn () => \App\Services\VehicleCare::validUntil($paid, $type)->format('Y-m-d'), today()->addYear()->format('Y-m-d'), false));
        $vehicleId = is_scalar(old('vehicle_id')) ? old('vehicle_id') : $selected;
    @endphp
    @include('partials.flash')
    <x-dl::form title="Perpanjang Dokumen Kendaraan" subtitle="Pajak tahunan, STNK dan plat 5 tahunan, KIR, atau asuransi. Masa berlaku kendaraan memakai berlaku-sampai terbesar per jenis dokumen" panel="top" kind="master" status="new"
        :back="$back" :action="route('vehicle-docs.store')" enctype="multipart/form-data">
        <x-dl::form.row :cols="3">
            <x-dl::field.select name="vehicle_id" label="Kendaraan" :options="$vehicles" :value="$vehicleId" required :error="$e('vehicle_id')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.radio name="doc_type" label="Jenis Dokumen" :options="$docs" :value="$type" required :error="$e('doc_type')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.date name="paid_at" label="Tanggal Bayar / Uji" :value="$paid" required :error="$e('paid_at')" />
            <x-dl::field.date name="valid_until" label="Berlaku Sampai" :value="$until" required :error="$e('valid_until')" hint="Terisi otomatis dari tanggal bayar + masa berlaku jenis dokumen (pajak 12 bulan, STNK dan plat 60 bulan, KIR 6 bulan, asuransi 12 bulan). Boleh diubah." />
            <x-dl::field.decimal name="cost" label="Biaya (IDR)" :decimals="2" :value="old('cost', 0)" required :error="$e('cost')" />
        </x-dl::form.row>
        <x-dl::form.row :cols="3">
            <x-dl::field.text name="doc_no" label="No. Dokumen" :value="old('doc_no')" maxlength="60" :error="$e('doc_no')" hint="Mis. no. STNK, no. kir, atau no. polis." />
            <x-dl::field.text name="note" label="Catatan" :value="old('note')" maxlength="255" :error="$e('note')" />
        </x-dl::form.row>
        <x-dl::form.row>
            <x-dl::field.upload name="attachment" label="Lampiran" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" :error="$e('attachment')" hint="Foto atau PDF bukti pembayaran / dokumen (dianjurkan, tidak wajib). JPG, PNG, atau PDF, paling besar 5 MB." />
        </x-dl::form.row>
    </x-dl::form>
    <script>
        // Berlaku sampai mengikuti tanggal bayar + masa berlaku jenis dokumen selama belum diubah tangan; KIR dinonaktifkan untuk kendaraan yang tidak wajib KIR.
        (() => {
            const kir = @json($kir), months = @json($months), manualFromServer = @json(session()->hasOldInput());
            const form = document.querySelector('form[action="{{ route('vehicle-docs.store') }}"]');
            const field = n => form.querySelector(`[name="${n}"]`);
            const radio = v => form.querySelector(`input[name="doc_type"][value="${v}"]`);
            let manual = manualFromServer;
            const addMonths = (iso, n) => {
                const [y, m, d] = iso.split('-').map(Number), t = new Date(y, m - 1 + n, 1);
                t.setDate(Math.min(d, new Date(t.getFullYear(), t.getMonth() + 1, 0).getDate()));
                return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
            };
            const syncKir = () => {
                const need = kir[field('vehicle_id').value] !== false, r = radio('kir');
                if (!r) return;
                r.disabled = !need;
                r.closest('label').title = need ? '' : 'Kendaraan ini tidak wajib KIR';
                if (!need && r.checked) radio('pajak').checked = true;
            };
            const sync = () => {
                const paid = field('paid_at').value, t = (form.querySelector('input[name="doc_type"]:checked') || {}).value;
                if (!manual && paid && months[t]) window.Devlib.h.setField(form, 'valid_until', addMonths(paid, months[t]));
            };
            form.addEventListener('change', e => {
                const n = e.target.name;
                if (n === 'valid_until') { manual = true; return; }
                if (n === 'vehicle_id') syncKir();
                if (['vehicle_id', 'paid_at', 'doc_type'].includes(n)) sync();
            });
            syncKir();
        })();
    </script>
@endsection
