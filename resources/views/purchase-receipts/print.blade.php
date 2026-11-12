{{-- REMARK
 Tujuan       : Halaman cetak satu Penerimaan Pembelian (PV) terkonfirmasi.
 Sumber hulu  : PurchaseReceiptController@print (doc = App\Support\PurchaseReceiptPrint).
 Input        : doc, pv.
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-doc.
 Dipakai oleh : route pv.print; tombol Cetak di purchase-receipts/form.blade.php.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Penerimaan Pembelian'])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('pv.show', $pv)" />
            <x-dl2::doc-share target="#print-doc" type="print-purchase-receipt" :doc-id="$pv->id" :number="$pv->doc_no" :title="$doc->fileTitle()" :email="false"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl2::action-bar>
        <x-pdoc :doc="$doc" id="print-doc" />
    </x-dl2::box>
@endsection
