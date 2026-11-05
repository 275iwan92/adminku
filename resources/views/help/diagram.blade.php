{{-- REMARK
 Tujuan       : Menyisipkan satu diagram SVG HELP secara inline (supaya mewarisi token tema terang/gelap) dari resources/help/diagrams/{name}.svg, dengan keterangan di bawahnya.
                Berkas ada di resources/ (bukan public/), jadi diagram TG ikut terlindung hak akses Administrator.
 Input        : name (nama berkas tanpa .svg), caption, idp (opsional: awalan id unik untuk salinan kedua di halaman yang sama).
 Dipakai oleh : help/user-guide.blade.php (diagram ug-*), help/technical-guide.blade.php (diagram tg-*).
 Status       : Aktif.
--}}
<figure class="g-fig">
    @php
        $svg = file_get_contents(resource_path('help/diagrams/'.$name.'.svg'));
        // idp = awalan id unik bila diagram yang sama tampil lebih dari sekali di satu halaman (id SVG harus unik).
        if (! empty($idp)) {
            $svg = preg_replace(['/\bid="([^"]+)"/', '/url\(#([^)]+)\)/', '/(href)="#([^"]+)"/'], ['id="'.$idp.'-$1"', 'url(#'.$idp.'-$1)', '$1="#'.$idp.'-$2"'], $svg);
        }
    @endphp
    {!! $svg !!}
    <figcaption>{{ $caption }}</figcaption>
</figure>
