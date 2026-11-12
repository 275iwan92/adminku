{{-- REMARK
 Tujuan       : Halaman cetak satu Permintaan Pembelian (PP) setelah Disetujui.
 Sumber hulu  : PurchaseRequestController@print (doc = App\Support\PurchaseRequestPrint).
 Input        : doc, pr.
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-doc.
 Dipakai oleh : route pr.print; tombol Cetak di purchase-requests/form.blade.php.
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Permintaan Pembelian'])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('pr.show', $pr)" />
            <x-dl2::doc-share target="#print-doc" type="print-purchase-request" :doc-id="$pr->id" :number="$pr->doc_no" :title="$doc->fileTitle()" :email="false"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl2::action-bar>
        <x-pdoc :doc="$doc" id="print-doc" />
    </x-dl2::box>
@endsection
