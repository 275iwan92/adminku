{{-- REMARK
 Tujuan       : Halaman cetak satu Penerimaan Pembelian (PV) terkonfirmasi.
 Sumber hulu  : PurchaseReceiptController@print (doc = App\Support\PurchaseReceiptPrint).
 Input        : doc, pv.
 Output       : x-dl::action-bar (Kembali + Print / PDF), x-dl::print-doc.
 Dipakai oleh : route pv.print; tombol Cetak di purchase-receipts/form.blade.php.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penerimaan Pembelian'])
@section('body')
    <x-dl::box>
        <x-dl::action-bar>
            <x-dl::button variant="secondary" icon="back" label="Back" :href="route('pv.show', $pv)" />
            <x-dl::doc-share target="#print-doc" type="print-purchase-receipt" :doc-id="$pv->id" :number="$pv->doc_no" :title="$doc->fileTitle()" :email="false"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl::action-bar>
        <x-dl::print-doc :doc="$doc" id="print-doc" />
    </x-dl::box>
@endsection
