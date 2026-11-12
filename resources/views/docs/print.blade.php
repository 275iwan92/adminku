{{-- REMARK
 Tujuan       : Halaman cetak satu dokumen stok (A5 landscape sampai 5 baris, A4 portrait di atasnya). Cetakan membawa MODE dokumen (DRAFT / CONFIRMED / CANCELLED ...) dan tanda air bila belum / tidak sah.
 Sumber hulu  : StockDocumentController@print (doc = App\Support\StockDocumentPrint).
 Input        : doc, t, document.
 Output       : x-dl2::action-bar (Kembali + Print / PDF), x-dl2::print-doc.
 Dipakai oleh : route docs.print; tombol Print di docs/form.blade.php.
 Status       : Aktif.
--}}
@extends('layout', ['title' => $t->label()])
@section('body')
    <x-dl2::box>
        <x-dl2::action-bar>
            <x-dl2::button variant="secondary" icon="back" label="Back" :href="route('docs.show', [$t->slug(), $document])" />
            <x-dl2::doc-share target="#print-doc" :type="'print-stock-'.$t->slug()" :doc-id="$document->id" :number="$document->doc_no" :title="$doc->fileTitle()" :email="false"
                :orientation="$doc->paper() === 'a5' ? 'landscape' : 'portrait'" />
        </x-dl2::action-bar>
        <x-pdoc :doc="$doc" id="print-doc" />
    </x-dl2::box>
@endsection
