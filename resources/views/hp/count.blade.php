{{-- REMARK
 Tujuan       : Layar HITUNG Stok Opname tampilan HP: satu barang per kartu, kolom jumlah besar (teks standar, inputmode decimal) dengan satuan stok di sampingnya, kemajuan n/N, tombol Sebelumnya / Barang berikutnya menempel di bawah, Enter pindah ke barang berikutnya, kolom cari kode/nama (pemindai barcode = keyboard + Enter), opsi Tidak dihitung, peringatan lunak bila orang lain sudah mengisi, status simpan per barang.
 Sumber hulu  : StockCountController@phone (count, rows dari StockCounts::entryRows(trace)). Hitung buta: stok sistem tidak ada di rows sama sekali. Simpan lewat stock-counts.lines (aturan sama dengan layar desktop); logikanya di resources/js/hp-count.js.
 Input        : count, rows, me (id pengguna).
 Output       : kartu hitung, ringkasan akhir dengan tombol Selesai hitung (POST stock-counts.finish, hp=1).
 Dipakai oleh : route hp.count.
 Status       : Aktif.
--}}
@extends('hp.layout', ['back' => route('hp.counts'), 'active' => 'count', 'title' => 'Hitung '.$count->doc_no])
@section('content')
    @include('partials.flash')
    @if ($count->review_note)
        <x-dl2::alert tone="info" :message="raw('Dikembalikan Supervisor untuk dihitung ulang: '.$count->review_note)" />
    @endif
    @if ($rows === [])
        <x-dl2::alert tone="info" message="Lembar hitung ini tidak punya barang." />
    @else
        <div class="sstock-hpc" data-hp-count data-save-url="{{ route('stock-counts.lines', $count) }}" data-me="{{ $me }}" data-key="stock-count.{{ $count->id }}">
            <script type="application/json" data-hp-rows>{!! json_encode($rows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>

            <div class="sstock-hpc__bar">
                <div class="sstock-hpc__prog"><strong data-pos></strong><span data-done></span><button type="button" class="sstock-hpc__link" data-open-summary>Ringkasan</button></div>
                <div class="sstock-hpc__meter"><i data-meter></i></div>
                <p class="sstock-hpc__save" data-save data-kind="ok" role="status" aria-live="polite"><span>Siap</span> <button type="button" class="sstock-hpc__link" data-retry hidden>Coba lagi</button></p>
            </div>

            <div data-single>
                <div class="sstock-hpc__find">
                    <input type="search" class="sstock-hpc__search" data-search placeholder="Cari kode / nama, atau pindai barcode" enterkeyhint="go" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Cari barang">
                    <div class="sstock-hpc__hits" data-hits hidden></div>
                </div>

                <article class="ak-pcard sstock-hpc__card" data-card>
                    <h2 class="sstock-hpc__name" data-name></h2>
                    <p class="sstock-hpc__meta" data-meta></p>
                    <p class="sstock-hpc__sys" data-system hidden></p>
                    <p class="sstock-hpc__warn" data-warn hidden></p>

                    <label class="sstock-hpc__lbl" for="hpc-qty">Jumlah hitung</label>
                    <div class="sstock-hpc__qtyrow">
                        <input id="hpc-qty" class="sstock-hpc__qty" type="text" inputmode="decimal" enterkeyhint="next" autocomplete="off" placeholder="0" data-qty>
                        <span class="sstock-hpc__unit" data-unit></span>
                    </div>
                    <p class="sstock-hpc__state" data-state data-on="0"></p>
                    <p class="sstock-hpc__bad" data-err hidden role="alert"></p>

                    <div class="sstock-hpc__conflict" data-conflict hidden role="alert">
                        <p data-conflict-msg></p>
                        <input type="text" class="sstock-hpc__field" data-reason placeholder="Alasan menimpa (wajib)" aria-label="Alasan menimpa">
                        <div class="sstock-hpc__btns">
                            <button type="button" class="sstock-hpc__btn" data-discard>Buang isian saya</button>
                            <button type="button" class="sstock-hpc__btn sstock-hpc__btn--go" data-overwrite>Timpa</button>
                        </div>
                    </div>

                    <label class="sstock-hpc__skip"><input type="checkbox" data-skip> <span>Tidak dihitung (tidak ketemu / tidak bisa dihitung; catatan wajib)</span></label>
                    <details class="sstock-hpc__more" data-more>
                        <summary>Catatan dan penghitung</summary>
                        <label class="sstock-hpc__lbl" for="hpc-note">Catatan</label>
                        <input id="hpc-note" type="text" class="sstock-hpc__field" maxlength="255" autocomplete="off" data-note>
                        <label class="sstock-hpc__lbl" for="hpc-by">Penghitung</label>
                        <input id="hpc-by" type="text" class="sstock-hpc__field" maxlength="100" autocomplete="off" data-by placeholder="Kosong = nama yang terakhir dipakai">
                    </details>
                </article>

                <x-dl2::phone-bar columns="1fr 1.5fr">
                    <button type="button" class="sstock-hpc__btn" data-prev>‹ Sebelumnya</button>
                    <button type="button" class="sstock-hpc__btn sstock-hpc__btn--go" data-next>Barang berikutnya</button>
                </x-dl2::phone-bar>
            </div>

            <section class="ak-pcard" data-summary hidden>
                <h2 class="sstock-hpc__name">Ringkasan</h2>
                <p data-sum-line></p>
                <p class="sstock-hpc__meta" data-not-yet>Barang yang belum terisi (sentuh untuk membuka):</p>
                <div class="sstock-hpc__list" data-sum-open></div>
                <p class="sstock-hpc__meta" data-sum-more></p>
                <div data-finish hidden>
                    <p>Semua barang sudah terisi. Sesudah Selesai hitung, hasil tidak bisa diubah kecuali dikembalikan Supervisor.</p>
                    <x-dl2::action-form :action="route('stock-counts.finish', $count)">
                        <input type="hidden" name="hp" value="1">
                        <x-dl2::button type="submit" size="lg" block icon="fa-flag-checkered" label="Finish Count" data-dl2-confirm="Selesai hitung? Semua baris harus terisi (atau Tidak dihitung dengan catatan). Sesudah ini hasil tidak bisa diubah kecuali dikembalikan Supervisor." />
                    </x-dl2::action-form>
                </div>
                <button type="button" class="sstock-hpc__btn sstock-hpc__btn--block" data-back-count>‹ Kembali menghitung</button>
            </section>
        </div>
    @endif
@endsection
