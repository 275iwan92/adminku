{{-- Satu lembar x-pdoc. $cont = lembar lanjutan (gaya Color / B&W): judul bertanda (lanjutan) + ringkasan satu baris, tanpa blok field. --}}
<section @class(['ak-pdoc__sheet', 'is-'.$paper, 'is-sty-'.($d['style'] ?? '') => $styled]) data-pd-sheet data-pd-type="{{ $doc->type }}"
    style="--pd-info-rows:{{ $doc->infoRows }};--pd-rh:{{ $doc->rowHeight }}mm;--pd-tail:{{ $doc->tailHeight }}mm">
    @if ($wm = $doc->watermark())<div class="ak-pdoc__wm {{ mb_strlen($wm) > 9 ? 'is-long' : '' }}" aria-hidden="true">{{ $wm }}</div>@endif
    @include('components.pdoc.kop', ['title' => $t($doc->title), 'subtitle' => ($cont ?? false) ? $t('(continued)') : ($doc->subtitle ? $t($doc->subtitle) : null), 'compact' => $doc->titleCompact])

    @if ($cont ?? false)
    <div class="ak-pdoc__sum"><b>{{ $d['number'] ?? '' }}</b><span>{{ $party['name'] ?? '' }}</span>@if (! empty($d['customer_po_no']))<span>PO {{ $d['customer_po_no'] }}</span>@endif<span>{{ \Devlib2\Locale::d($d['date'] ?? null) ?? '—' }}</span>{!! $stamp !!}</div>
@else
<div class="ak-pdoc__info">
        <div>
            <div @class(['ak-pdoc__f', 'is-big', 'is-st' => $styled]) data-pd-k="number"><span>{{ $t($doc->numberLabel) }}</span><i>:</i><b>{{ $d['number'] ?? '' }}</b>@if ($styled){!! $stamp !!}@endif</div>
            @for ($i = 0; $i < $doc->infoRows - 1; $i++){!! $field($doc->refs[$i] ?? null) !!}@endfor
        </div>
        <div>
            <div class="ak-pdoc__f is-st" data-pd-k="date"><span>{{ $t('Date') }}</span><i>:</i><b>{{ \Devlib2\Locale::d($d['date'] ?? null) ?? '—' }}</b>@unless ($styled)<span class="ak-pdoc__stamp">{{ $doc->statusLabel() }}</span>@endunless</div>
            <div class="ak-pdoc__f" data-pd-k="party"><span>{{ $t($doc->partyLabel) }}</span><i>:</i><b><strong>{{ $party['name'] ?? '' }}</strong>@if (! empty($party['code'])) &middot; {{ $party['code'] }}@endif</b></div>
            @for ($i = 0; $i < $doc->infoRows - 3; $i++)<div class="ak-pdoc__f is-addr"><span></span><i></i><b>{{ $party['lines'][$i] ?? '' }}</b></div>@endfor
            {!! $field($doc->side) !!}
        </div>
    </div>

    <p class="ak-pdoc__nb">@if ($doc->nb !== '' && ! $doc->nbPlain)<b>NB:</b>&nbsp;@endif{{ $doc->nbRaw ? $doc->nb : $t($doc->nb) }}</p>
@endif

    <table class="ak-pdoc__items">
        <colgroup>
            <col style="width:7mm"><col>
            @if ($doc->qtyCols)<col style="width:21mm"><col style="width:15mm">@endif
            @foreach ($cols as $c)<col style="width:{{ $c['width'] }}mm">@endforeach
        </colgroup>
        <thead><tr>
            <th></th><th>{{ $t($doc->nameLabel) }}</th>
            @if ($doc->qtyCols)<th class="is-num">{{ $t('Qty') }}</th><th>{{ $t('Unit') }}</th>@endif
            @foreach ($cols as $c)<th class="{{ $doc->columnClass($c) }}">{{ $t($c['label']) }}</th>@endforeach
        </tr></thead>
        <tbody>
            @foreach ($sheet['rows'] as $i => $r)
                <tr @class([$r['cls'] ?? null])>
                    <td class="is-no">{{ $sheet['from'] + $i + 1 }}.</td>
                    @if ($r)
                        <td @class(['is-name', 'is-two' => ! empty($r['sub'])])><div class="ak-pdoc__n1">{{ $r['name'] ?? '' }}</div>@if (! empty($r['sub']))<div class="ak-pdoc__n2">{{ $r['sub'] }}</div>@endif @if (! empty($r['sub2']))<div class="ak-pdoc__n3">{{ $r['sub2'] }}</div>@endif</td>
                        @if ($doc->qtyCols)<td class="is-num is-qty">{{ $doc->qty($r['qty'] ?? null) }}</td><td>{{ $r['unit'] ?? '' }}</td>@endif
                        @foreach ($cols as $c)<td class="{{ $doc->columnClass($c) }}" data-pd-c="{{ $c['key'] }}">@if (! empty($c['wrap']))<div class="ak-pdoc__w">{!! $doc->value($c, $r, '') !!}</div>@else{!! $doc->value($c, $r, '') !!}@endif</td>@endforeach
                    @else
                        <td></td>@if ($doc->qtyCols)<td></td><td></td>@endif @foreach ($cols as $c)<td></td>@endforeach
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($sheet['last'])
        <div class="ak-pdoc__tail">
            <div class="ak-pdoc__notes">@foreach ($doc->notes() as $n)<p><b>{{ $n[0] }}:</b> {{ $n[1] }}</p>@endforeach</div>
            <div class="ak-pdoc__tot">@foreach ($totals['lines'] as $l)<div @class(['is-grand' => $l[2]])><span>{{ $l[0] }}</span><b>{{ $l[1] }}</b></div>@endforeach</div>
        </div>
        <div class="ak-pdoc__sign" style="grid-template-columns:repeat({{ count($sign) }},1fr)">
            @foreach ($sign as $s)<div><span>{{ $s[0] }}</span><small>{{ $s[1] }}</small><i></i><b>(@if ($s[2])<u class="is-name">{{ $s[2] }}</u>@else<u></u>@endif)</b>@if (! empty($s[3]))<em class="ak-pdoc__at">{{ $s[3] }}</em>@endif</div>@endforeach
        </div>
    @endif

    @if ($styled)
    <footer class="ak-pdoc__foot"><span>{{ $doc->printedLine() }}</span><span data-pd-foot="{{ ($d['number'] ?? '').' · '.$doc->statusLabel().' · '.$t('Page') }}">{{ ($d['number'] ?? '').' · '.$doc->statusLabel().' · '.$t('Page').' '.$sheet['page'].' / '.$sheet['pages'] }}</span></footer>
@else
    <footer class="ak-pdoc__foot"><span>{{ $doc->printedLine() }}</span><span>{{ $t('Sheet') }} {{ $sheet['page'] }}/{{ $sheet['pages'] }}</span></footer>
@endif
</section>
