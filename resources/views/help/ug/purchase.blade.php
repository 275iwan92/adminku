{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9b Purchase: Permintaan Pembelian (PP): satu PP banyak barang, siklus Draf > Confirm > Disetujui / Ditolak > Dibatalkan, nomor otomatis, satuan dan estimasi harga satuan terisi sendiri.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : PurchaseRequestController / pr.*, PurchaseRequest, PurchaseRequestRequest, Modules (kode PUP), User::canAccess / canWrite, Menu::layers.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya modul PUP). Tidak ada diagram.
 Status       : Aktif.
--}}
@if ($anyPur)
<h2 id="purchase">9b. Purchase</h2>
<p>Menu Purchase mengurus pembelian dari permintaan sampai barang masuk stok: <b>Permintaan Pembelian (PP)</b>, <b>Keputusan Pesanan</b>, <b>Pesanan Pembelian (PO)</b>, dan <b>Penerimaan Pembelian (PV)</b>.</p>
<ul class="g-list">
    <li><b>Hak Purchase datang dari Grup, bukan dari peran.</b> Menu Purchase hanya tampil bila Grup Anda diberi modul <b>Permintaan Pembelian</b>, <b>Keputusan Pesanan</b>, atau <b>Pesanan Pembelian (PO)</b>, atau <b>Penerimaan Pembelian (PV)</b> (RO untuk membaca, RW untuk menyimpan). Administrator melihat semuanya.</li>
    <li>Alurnya: PP disetujui, lalu Keputusan Pesanan memilih pemasok dari riwayat beli, lalu PO di-Confirm, disetujui, dan dicetak, lalu PV mencatat barang yang datang dan otomatis menambah stok.</li>
</ul>

@if ($can('PUP'))
<h3 id="pur-pup">Permintaan Pembelian (PP)</h3>
<h4>Untuk apa</h4>
<p>Meminta barang dibelikan. Satu PP boleh berisi banyak barang. PP dipakai sebagai dasar persetujuan dan sebagai bahan memilih pemasok: PP yang <b>Disetujui</b> baru boleh diproses menjadi pesanan.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Permintaan Pembelian</b>. Menyetujui atau menolak hanya untuk peran Supervisor ke atas, dan bukan pembuat PP itu sendiri.</p>
<h4>Langkah: membuat dan mengajukan PP</h4>
<ol class="g-steps">
    <li>Buka <b>Purchase</b> lalu <b>Permintaan Pembelian</b>, tekan <span class="g-btn">New</span>.</li>
    <li>Isi <b>Tanggal</b>, <b>Bagian peminta</b>, dan bila perlu <b>Dibutuhkan tgl</b> serta <b>Catatan</b>. Pemohon adalah Anda sendiri.</li>
    <li>Tiap baris: pilih <b>Item</b> dari master, atau ketik nama di <b>Barang baru (ketik)</b> untuk barang yang belum ada di master (isi juga <b>Unit</b>). Isi <b>Qty</b>.</li>
    <li><b>Unit</b> kosong diisi satuan barang. <b>Estimasi harga satuan</b> kosong diisi harga beli terakhir barang itu bila pernah dibeli; boleh diubah. <b>Subtotal</b> = Qty x Estimasi harga satuan.</li>
    <li>Tekan <span class="g-btn">Save</span> untuk menyimpan sebagai draf (belum bernomor). Tekan <span class="g-btn">Confirm</span>: nomor <b>PP-YYMM-0001</b> lahir, PP terkunci, dan masuk antrean persetujuan Supervisor.</li>
</ol>
<h4>Langkah: menyetujui</h4>
<ol class="g-steps">
    <li>PP berstatus <b>Menunggu Persetujuan</b>. Supervisor (bukan pembuatnya) membukanya lalu menekan <span class="g-btn">Approve</span> atau <span class="g-btn">Reject</span> (alasan wajib).</li>
    <li>PP yang ditolak bisa disalin menjadi draf baru dengan <span class="g-btn">Duplicate</span>.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Status: <b>Draf</b>, <b>Menunggu Persetujuan</b>, <b>Disetujui</b>, <b>Ditolak</b>, <b>Dibatalkan</b>. Dipesan sebagian dan Selesai akan terisi otomatis oleh PO.</li>
    <li><b>Cancel</b> (alasan wajib) bisa untuk PP yang diajukan atau disetujui oleh pembuat atau Supervisor, selama belum ada barang yang dipesan. <b>Delete</b> hanya untuk draf.</li>
    <li>Kolom <b>Dipesan</b> di baris menunjukkan jumlah yang sudah dipesan.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Bagian peminta belum dipilih, ada baris tanpa barang atau Qty 0, atau Dibutuhkan tgl sebelum Tanggal.</li>
    <li><b>Tombol Approve tidak muncul.</b> Anda pembuat PP itu, atau peran Anda bukan Supervisor.</li>
</ul>
@endif

@if ($can('PUK'))
<h3 id="pur-puk">Keputusan Pesanan</h3>
<h4>Untuk apa</h4>
<p>Memutuskan barang dipesan ke pemasok mana. Layar ini bekerja untuk <b>satu barang</b> dan tidak menyimpan dokumen: ia menunjukkan siapa saja pemasok yang pernah menjual barang itu, kapan terakhir, berapa harganya, dan berapa kali dibeli, lalu membuat draf PO.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Keputusan Pesanan</b>. Tombol <span class="g-btn">Buat PO</span> juga butuh hak tulis modul <b>Pesanan Pembelian (PO)</b>.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Pilih barangnya. Ada dua cara: <b>Dari master</b> (pilih atau ketik di <b>Item</b>, atau ketik nama di <b>Barang baru (ketik)</b> dengan <b>Unit</b>) atau <b>Dari PP</b> (daftar baris PP yang Disetujui dan belum terpesan penuh; tekan <span class="g-btn">Pilih</span> mengisi barang dan sisa Qty). Isi <b>Qty</b>.</li>
    <li>Tekan <span class="g-btn">Proses</span>. Tiap pemasok tampil dengan <b>Beli terakhir</b>, <b>Harga terakhir</b> (hijau = paling murah), dan <b>Berapa kali</b>. Tekan <b>Riwayat</b> di satu baris untuk melihat tiap pembelian: tanggal, nomor dokumen, jumlah, harga satuan.</li>
    <li>Pilih satu pemasok (atau pilih di <b>Pemasok lain</b> untuk yang belum pernah dipakai), lalu tekan <span class="g-btn">Buat PO</span>. Barang masuk ke draf PO pemasok itu milik Anda (atau draf baru dibuat), dengan harga terakhir di pemasok itu.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Barang yang belum pernah dibeli hanya menampilkan info <b>Barang ini belum pernah dibeli</b>; pilih pemasok lewat <b>Pemasok lain</b>.</li>
    <li>Riwayat diambil dari Penerimaan Barang yang sudah dikonfirmasi dan tidak dibatalkan, dengan harga per satuan stok barang.</li>
    <li>Barang yang berasal dari PP tetap terhubung ke PP-nya: kolom Dipesan di PP bertambah, dan status PP menjadi Dipesan sebagian lalu Selesai bila semua barisnya terpesan penuh.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Proses ditolak.</b> Barang belum dipilih atau diketik, Qty kosong atau 0, atau Unit belum diisi untuk barang yang diketik.</li>
    <li><b>Buat PO ditolak.</b> Pemasok belum dipilih, atau Anda belum punya hak tulis modul Pesanan Pembelian (PO).</li>
    <li><b>Baris PP tidak ada di tab Dari PP.</b> PP-nya belum Disetujui, atau semua barisnya sudah terpesan penuh.</li>
</ul>
@endif

@if ($can('PUO'))
<h3 id="pur-puo">Pesanan Pembelian (PO)</h3>
<h4>Untuk apa</h4>
<p>Surat pesanan resmi ke satu pemasok, dengan banyak barang. PO di-Confirm, disetujui Supervisor, lalu dicetak.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Pesanan Pembelian (PO)</b>. Menyetujui atau menolak hanya untuk peran Supervisor ke atas, dan bukan pembuat PO itu sendiri.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>PO biasanya dibuat dari <b>Keputusan Pesanan</b>. Untuk PO manual, buka <b>Purchase</b> lalu <b>Pesanan Pembelian (PO)</b> dan tekan <span class="g-btn">New</span>.</li>
    <li>Tab <b>Data</b>: isi <b>Pemasok</b> (<b>Syarat Bayar</b> terisi dari pemasok), <b>Kirim tgl</b>, <b>Kirim ke</b>, <b>PPN (%)</b> bila ada (0 = tanpa PPN), dan <b>Catatan</b>. Periksa baris barang dan <b>Harga satuan</b>; kolom <b>Dari PP</b> menunjukkan PP asalnya. Tab <b>Riwayat</b> mencatat siapa mengajukan dan menyetujui.</li>
    <li>Tekan <span class="g-btn">Save</span> untuk draf, lalu <span class="g-btn">Confirm</span>: nomor PO lahir dan PO terkunci. Sebelum Confirm, <span class="g-btn">Delete</span> menghapus draf.</li>
    <li>Supervisor lain menekan <span class="g-btn">Approve</span> atau <span class="g-btn">Reject</span> (alasan wajib). Setelah <b>Disetujui</b>, tombol <span class="g-btn">Print</span> muncul untuk mencetak PO.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Nomor PO-YYMM-0001 lahir saat Confirm (draf belum bernomor). Status: Draf, Menunggu Persetujuan, Disetujui, Ditolak, Dibatalkan; Diterima sebagian dan Selesai akan terisi otomatis oleh Penerimaan Pembelian.</li>
    <li><b>Cancel</b> (alasan wajib) bisa selama belum ada penerimaan. PO yang ditolak atau dibatalkan bisa disalin menjadi draf baru dengan <span class="g-btn">Duplicate</span>.</li>
    <li>Menyimpan ulang draf mempertahankan link ke PP selama barang barisnya tidak diganti.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>Save atau Confirm ditolak.</b> Pemasok belum dipilih, ada baris tanpa barang, Qty 0, atau Harga satuan kosong.</li>
    <li><b>Tombol Print tidak ada.</b> PO belum Disetujui.</li>
</ul>
@endif

@if ($can('PUV'))
<h3 id="pur-puv">Penerimaan Pembelian (PV)</h3>
<h4>Untuk apa</h4>
<p>Mencatat barang yang datang dari satu PO. Saat <b>Konfirmasi</b>, dokumen <b>Penerimaan Barang (RCV)</b> di Stok dibuat dan dikonfirmasi otomatis, sehingga stok bertambah tanpa input ulang. Jumlah diterima dan status PO ikut diperbarui.</p>
<h4>Siapa yang bisa</h4>
<p>Pengguna yang Grup-nya punya modul <b>Penerimaan Pembelian (PV)</b> (RW untuk membuat dan mengonfirmasi). Hanya peran Supervisor ke atas yang bisa membatalkan PV yang sudah terkonfirmasi.</p>
<h4>Langkah</h4>
<ol class="g-steps">
    <li>Buka PO yang <b>Disetujui</b> lalu tekan <span class="g-btn">Terima barang</span>, atau buka <b>Purchase</b> lalu <b>Penerimaan Pembelian (PV)</b>, tekan <span class="g-btn">New</span>, dan pilih <b>Pesanan Pembelian (PO)</b>. Pemasok dan barang PO terisi, dengan <b>Terima sekarang</b> = sisa PO.</li>
    <li>Isi <b>Tanggal terima</b>, <b>No surat jalan pemasok</b>, dan <b>Penerima barang</b>. Ubah <b>Terima sekarang</b> bila yang datang kurang (kosong atau 0 = barang itu belum datang). Untuk barang yang melacak kedaluwarsa isi <b>Kedaluwarsa</b>.</li>
    <li>Tekan <span class="g-btn">Save</span> untuk draf, atau <span class="g-btn">Confirm</span> untuk menerima: nomor PV lahir (draf belum bernomor), nomor RCV tampil di PV, dan stok barang bertambah.</li>
    <li>Kiriman berikutnya dibuat sebagai PV baru dari PO yang sama sampai semua barang penuh.</li>
</ol>
<h4>Yang perlu diperhatikan</h4>
<ul class="g-list">
    <li>Jumlah diterima tidak boleh melebihi <b>Sisa</b> PO. PO menjadi <b>Diterima sebagian</b>, lalu <b>Selesai</b> bila semua barang penuh.</li>
    <li>Pemasok, harga satuan, dan surat jalan (jadi No. Referensi) diambil dari PO dan PV. Satuan yang berbeda dari satuan stok memakai konversi Stok; bila faktornya belum ada, Konfirmasi ditolak dengan petunjuk.</li>
    <li>Setelah Confirm PV terkunci. Supervisor dapat menekan <span class="g-btn">Cancel</span> (alasan wajib): RCV-nya dibatalkan, stok dikembalikan, dan jumlah diterima PO kembali. Pembatalan ditolak bila stok sudah tidak cukup untuk ditarik kembali.</li>
    <li>Draf boleh dihapus dengan <span class="g-btn">Delete</span>.</li>
</ul>
<h4>Masalah umum</h4>
<ul class="g-list">
    <li><b>PO tidak ada di pilihan.</b> PO belum Disetujui atau sudah Selesai.</li>
    <li><b>Confirm ditolak.</b> Jumlah melebihi sisa, tanggal kedaluwarsa kosong atau sudah lewat, atau barang di PO masih teks bebas (belum ada di master Barang: tambahkan dulu di Data Master).</li>
</ul>
@endif
@endif
