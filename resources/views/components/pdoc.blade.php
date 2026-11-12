{{-- REMARK
 Tujuan       : Cetakan dokumen transaksi dari objek Devlib2\PrintDoc\Document (kop, blok field, tabel, total, tanda tangan, lampiran), A5 landscape sampai 5 baris dan A4 portrait di atasnya. Pengganti x-dl::print-doc v1 (DevLib 2 belum punya renderer PHP untuk Document).
 Sumber hulu  : Devlib2\PrintDoc\Document (turunan: StockDocumentPrint, PurchaseOrderPrint, dst.), CSS resources/css/pdoc.css.
 Input        : doc.
 Output       : lembar .ak-pdoc__sheet (satu per halaman) + lampiran.
 Dipakai oleh : docs/print, purchase-orders/print, purchase-receipts/print, purchase-requests/print.
 Status       : Aktif.
--}}
@props(['doc'])
@php
    $t = fn (?string $s) => \Devlib2\PrintDoc\Document::t($s);
    $d = $doc->data;
    $co = $doc->company();
    $party = $d['party'] ?? [];
    $cols = $doc->columns();
    $paper = $doc->paper();
    $totals = $doc->totals();
    $sign = $doc->signatures();
    $styled = $doc->styled();
    $stamp = '<span class="ak-pdoc__stamp is-'.$doc->statusTone().'">'.e($doc->statusLabel()).'</span>';
    $field = fn (?array $def) => $def
        ? '<div class="ak-pdoc__f" data-pd-k="'.e($def['key']).'"><span>'.e($t($def['label'])).'</span><i>:</i><b>'.$doc->value($def).'</b></div>'
        : '<div class="ak-pdoc__f"></div>';
@endphp
<div {{ $attributes->class('ak-pdoc') }} data-ak-pdoc data-paper="{{ $paper }}">
    @foreach ($doc->sheets() as $sheet)
        @include('components.pdoc.sheet', ['cont' => $styled && $sheet['page'] > 1])
    @endforeach
    @if ($styled)<template data-pd-cont>@include('components.pdoc.sheet', ['sheet' => ['rows' => [], 'from' => 0, 'last' => false, 'page' => 2, 'pages' => 2], 'cont' => true])</template>@endif
    @php($aps = $doc->appendices())
    @foreach ($aps as $ap)
        @include('components.pdoc.appendix', ['ap' => $ap, 'n' => $loop->iteration, 'total' => count($aps)])
    @endforeach
</div>
