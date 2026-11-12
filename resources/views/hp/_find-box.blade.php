{{-- REMARK
 Tujuan       : Kolom cari Cari Barang tampilan HP: satu isian teks besar (16 px, autofokus, tanpa kamera) yang menerima ketikan atau hasil pemindai barcode Bluetooth/USB (keyboard + Enter = kirim formulir GET).
 Input        : q (isi awal; kosong sesudah pindai ke barang).
 Output       : formulir GET ke hp.find; resources/js/hp-find.js memilih isi kolom saat fokus supaya pindai berikutnya menimpa, bukan menyambung.
 Dipakai oleh : hp.find, hp.find-show.
 Status       : Aktif.
--}}
<form method="get" action="{{ route('hp.find') }}" class="sstock-hpc__find" data-hp-find>
    <input type="text" name="q" value="{{ $q ?? '' }}" class="sstock-hpc__search sstock-hpc__search--big" placeholder="Ketik atau pindai kode / nama / lokasi" enterkeyhint="search" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" autofocus aria-label="Cari barang">
    <x-dl2::button type="submit" size="lg" block icon="fa-magnifying-glass" label="Search" />
</form>
