{{-- REMARK
 Tujuan       : Kartu modul Beranda global (desktop dan HP): judul modul, tiga angka (berwarna koral bila perlu perhatian), dan tautan ke layar pembuka modul. Kartu hanya untuk modul yang boleh dilihat pengguna; tanpa satu pun kartu = keterangan singkat.
                Desktop: kartu membuka tab kerja Layer Shell (data-open-tab); HP: tautan ke tampilan HP modul.
 Sumber hulu  : App\Support\HomeCards::page (cards).
 Input        : cards (code, title, icon, tone, link, stats[value, label, alert, title]), phone (true = tautan HP).
 Output       : kartu dengan kelas sstock-mod* (CSS di resources/css/app.css).
 Dipakai oleh : home (desktop), hp.home.
 Status       : Aktif.
--}}
@if ($cards)
    <div class="sstock-mods">
        @foreach ($cards as $c)
            @php $link = $phone ? ['a', 'href="'.e(\App\Support\HpShell::url($c['code'])).'"'] : ['section', 'data-open-tab="'.e($c['code']).'" role="link" tabindex="0"']; @endphp
            <{{ $link[0] }} class="sstock-mod sstock-mod--{{ $c['tone'] }} {{ $phone ? '' : 'sstock-link' }}" {!! $link[1] !!} aria-label="{{ $c['title'] }}: {{ $c['link'] }}">
                <h3 class="sstock-mod__title"><i class="{{ $c['icon'] }}" aria-hidden="true"></i> {{ $c['title'] }}</h3>
                <ul class="sstock-mod__stats">
                    @foreach ($c['stats'] as $s)
                        <li><b class="{{ ($s['alert'] ?? false) ? 'is-alert' : '' }}" @isset($s['title']) title="{{ $s['title'] }}" @endisset>{{ $s['value'] }}</b><span>{{ $s['label'] }}</span></li>
                    @endforeach
                </ul>
                <span class="sstock-mod__go">{{ $c['link'] }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
            </{{ $link[0] }}>
        @endforeach
    </div>
@else
    <p class="sstock-mods-empty">Belum ada ringkasan untuk hak akses Anda. {{ $phone ? 'Buka menu di pojok kanan atas untuk memulai.' : 'Pilih menu di sebelah kiri untuk memulai.' }}</p>
@endif
