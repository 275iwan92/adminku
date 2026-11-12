{{-- Lembar lampiran x-pdoc (Document::appendices): blok facts / table / list / text, atau view milik aplikasi. --}}
<section class="ak-pdoc__sheet is-a4 ak-pdoc__appx" data-pd-sheet data-pd-type="{{ $doc->type }}" style="--pd-info-rows:{{ $doc->infoRows }};--pd-rh:{{ $doc->rowHeight }}mm;--pd-tail:{{ $doc->tailHeight }}mm">
    @if ($wm = $doc->watermark())<div class="ak-pdoc__wm {{ mb_strlen($wm) > 9 ? 'is-long' : '' }}" aria-hidden="true">{{ $wm }}</div>@endif
    @include('components.pdoc.kop', ['title' => $t($ap['title']), 'subtitle' => $doc->data['number'] ?? null, 'compact' => true])
    <div class="ak-pdoc__appx-body">
        @foreach ($ap['blocks'] ?? [] as $b)
            <div class="ak-pdoc__blk">
                @if (! empty($b['title']))<h5>{{ $t($b['title']) }}</h5>@endif
                @switch($b['kind'])
                    @case('facts')
                        @foreach ($b['rows'] as $r)<div class="ak-pdoc__kv"><span>{{ $t($r[0]) }}</span><b>{{ $r[1] }}</b></div>@endforeach
                        @break
                    @case('table')
                        <table class="ak-pdoc__tbl">
                            <thead><tr>@foreach ($b['head'] as $i => $h)<th @class(['is-num' => in_array($i, $b['num'] ?? [], true)])>{{ $t($h) }}</th>@endforeach</tr></thead>
                            <tbody>@foreach ($b['rows'] as $r)<tr>@foreach ($r as $i => $v)<td @class(['is-num' => in_array($i, $b['num'] ?? [], true)])>{{ $v }}</td>@endforeach</tr>@endforeach</tbody>
                        </table>
                        @break
                    @case('list')
                        <ol class="ak-pdoc__ol">@foreach ($b['items'] as $it)<li>{{ $t($it) }}</li>@endforeach</ol>
                        @break
                    @case('text')
                        <p class="ak-pdoc__txt">{{ $b['text'] }}</p>
                        @break
                    @case('sign')
                        @if (! empty($b['intro']))<p class="ak-pdoc__txt">{{ $t($b['intro']) }}</p>@endif
                        @foreach ($b['checks'] ?? [] as $c)<p class="ak-pdoc__chk">&#9744; {{ $t($c) }}</p>@endforeach
                        <div class="ak-pdoc__sign" style="grid-template-columns:repeat({{ count($b['slots']) }},1fr)">
                            @foreach ($b['slots'] as $sl)<div><span>{{ $t($sl[0]) }}</span><small>{{ $t($sl[1] ?? '') }}</small><i></i><b>(@if (! empty($sl[2]))<u class="is-name">{{ $sl[2] }}</u>@else<u></u>@endif)</b></div>@endforeach
                        </div>
                        @break
                @endswitch
                @if (! empty($b['note']))<p class="ak-pdoc__cap">{{ $t($b['note']) }}</p>@endif
            </div>
        @endforeach
        @if (! empty($ap['view']))@include($ap['view'], $ap['data'] ?? [])@endif
    </div>
    <footer class="ak-pdoc__foot"><span>{{ $doc->printedLine() }}</span><span>{{ $t('Appendix') }} {{ $n }}/{{ $total }}</span></footer>
</section>
