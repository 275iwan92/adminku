{{-- REMARK
 Tujuan       : Halaman cetak satu Pesanan Pembelian (PO), hanya setelah Disetujui: A5 landscape sampai 5 baris, A4 portrait di atasnya, tanda tangan Dibuat / Disetujui / Pemasok.
 Sumber hulu  : PurchaseOrderController@print (doc = App\Support\PurchaseOrderPrint).
 Input        : doc, po.
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-doc.
 Dipakai oleh : route po.print; tombol Cetak di purchase-orders/form.blade.php.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Pesanan Pembelian'])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('po.show', $po)" />
            <x-dl2::doc-share target="#print-doc" type="print-purchase-order" :doc-id="$po->id" :number="$po->doc_no" :title="$doc->fileTitle()" :email="false"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl2::action-bar>
        <x-pdoc :doc="$doc" id="print-doc" />
    </x-dl2::box>
@endsection
