{{-- REMARK
 Tujuan       : Panduan Pengguna bagian 9b Purchase: Permintaan Pembelian (PP): satu PP banyak barang, siklus Draf > Diajukan > Disetujui / Ditolak > Dibatalkan, nomor otomatis, satuan dan estimasi harga satuan terisi sendiri.
                Pola tetap tiap modul: Untuk apa, Siapa yang bisa, Langkah, Yang perlu diperhatikan, Masalah umum. Istilah sama persis dengan label di layar.
 Sumber hulu  : PurchaseRequestController / pr.*, PurchaseRequest, PurchaseRequestRequest, Modules (kode PUP), User::canAccess / canWrite, Menu::layers.
 Dipakai oleh : help/user-guide.blade.php (hanya bila pengguna punya modul PUP). Tidak ada diagram.
 Status       : Aktif.
--}}
@if ($anyPur)
<h2 id="purchase">9b. Purchase</h2>
<p>Menu Purchase mengurus pembelian: <b>Permintaan Pembelian (PP)</b>, lalu Keputusan Pesanan, Pesanan Pembelian (PO), dan Penerimaan Pembelian (PV). Yang sudah tersedia sekarang: PP. Layar lainnya tampil "Segera hadir" untuk Administrator.</p>
<ul class="g-list">
    <li><b>Hak Purchase datang dari Grup, bukan dari peran.</b> Menu Purchase hanya tampil bila Grup Anda diberi modul <b>Permintaan Pembelian</b> (RO untuk membaca, RW untuk menyimpan). Administrator melihat semuanya.</li>
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
    <li>Tekan <span class="g-btn">Save</span> untuk menyimpan sebagai draf (nomor <b>PP-YYMM-0001</b>). Tekan <span class="g-btn">Confirm</span> untuk mengajukan; setelah itu PP terkunci.</li>
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
@endif
