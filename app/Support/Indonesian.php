<?php

namespace App\Support;

use Devlib\Lang;

/* REMARK
 * Tujuan       : Kamus Indonesia aplikasi: layar ditulis English (kebiasaan DevLib), kamus ini menerjemahkan semua label komponen x-dl::* (label, judul, kolom, hint, tombol, status) ke bahasa Indonesia.
 *                Teks yang dirangkai di PHP (pesan, menu, jenis dokumen) ditulis langsung Indonesia di tempatnya.
 * Sumber hulu  : Devlib\Lang::translateUsing; config devlib.translate_fixed menyalakan terjemahan istilah tombol / status DevLib yang biasanya dipaksa English.
 * Output       : pemetaan English => Indonesia; teks yang tidak ada di kamus tampil apa adanya.
 * Dipakai oleh : AppServiceProvider::boot.
 * Status       : Aktif.
 */
final class Indonesian
{
    public static function register(): void
    {
        Lang::extend(['pr_printed_from' => ['Dicetak dari AdminKU', 'Printed from AdminKU']]);
        Lang::translateUsing(fn (string $en, string $lang) => $lang === 'id' ? (self::WORDS[$en] ?? null) : null);
    }

    private const WORDS = [
        // Tombol, status, istilah tetap DevLib
        'New' => 'Baru', 'Save' => 'Simpan', 'Undo' => 'Urungkan', 'Delete' => 'Hapus', 'Confirm' => 'Konfirmasi', 'Cancel' => 'Batalkan', 'Duplicate' => 'Duplikat',
        'Back to Draft' => 'Kembali ke Draf', 'Back' => 'Kembali', 'Deactivate' => 'Nonaktifkan', 'Activate' => 'Aktifkan', 'Approve' => 'Setujui', 'Reject' => 'Tolak',
        'Hold' => 'Tahan', 'Print' => 'Cetak', 'Email' => 'Email', 'Close' => 'Tutup',
        'Draft' => 'Draf', 'Confirmed' => 'Terkonfirmasi', 'Cancelled' => 'Dibatalkan', 'Closed' => 'Ditutup', 'Active' => 'Aktif', 'Inactive' => 'Nonaktif',
        'Approved' => 'Disetujui', 'Rejected' => 'Ditolak', 'Waiting Approval 1' => 'Menunggu Persetujuan 1',
        'Approval 1' => 'Persetujuan 1', 'Hold L1' => 'Ditahan T1',
        '+ New Record' => '+ Data Baru', 'Filters' => 'Saring', 'Reset filter' => 'Reset saringan', 'Export' => 'Ekspor', 'Created by' => 'Dibuat oleh', 'Created at' => 'Dibuat pada',
        'Last updated by' => 'Terakhir diubah oleh', 'Last updated at' => 'Terakhir diubah pada', 'History' => 'Riwayat',

        // Judul halaman
        'Home' => 'Beranda', 'Sign in' => 'Masuk', 'Items' => 'Barang', 'Item' => 'Barang', 'Suppliers' => 'Supplier', 'Supplier' => 'Supplier', 'Units' => 'Satuan', 'Unit' => 'Satuan',
        'Stock Card' => 'Kartu Stok', 'Summary Stock' => 'Ringkasan Stok', 'Receiving Report' => 'Laporan Penerimaan', 'Issuance Report' => 'Laporan Pengeluaran',
        'Opname & Adjustment Report' => 'Laporan Opname & Penyesuaian', 'AdminKU' => 'AdminKU',
        'Supplier · ' => 'Supplier · ',

        // Beranda
        'Active items' => 'Barang aktif', 'At / below reorder point' => 'Di / bawah titik pesan ulang', 'Receiving today' => 'Penerimaan hari ini', 'Issuance today' => 'Pengeluaran hari ini',
        'Stock value' => 'Nilai stok', 'Nilai Stok' => 'Nilai Stok', 'Documents waiting approval' => 'Dokumen menunggu persetujuan', 'Open Receiving' => 'Buka Penerimaan', 'Current Stock' => 'Stok saat ini', 'Order Status' => 'Status Pesanan', 'Record Order' => 'Catat Pemesanan', 'Close Order' => 'Tutup Pesanan', 'Order Date' => 'Tanggal Pesan', 'Order Reference No.' => 'No. Referensi Pemesanan',
        'Code' => 'Kode', 'Category' => 'Kategori', 'Location' => 'Lokasi', 'Stock' => 'Stok', 'Reorder Point' => 'Titik Pesan Ulang', 'Reorder Qty' => 'Jml Pesan Ulang', 'Lead Time' => 'Lead Time',

        // Grid master
        'All items' => 'Semua barang', 'Reorder' => 'Pesan ulang', 'Search code, name, location...' => 'Cari kode, nama, lokasi...', 'Level' => 'Status stok',
        'Lead Time (days)' => 'Lead Time (hari)', 'Unit Price' => 'Harga Satuan', 'Stock Value' => 'Nilai Stok',
        'All suppliers' => 'Semua supplier', 'Search code, name, contact, city...' => 'Cari kode, nama, kontak, kota...', 'Company Phone' => 'Telepon Kantor', 'Contact' => 'Kontak',
        'Contact Mobile' => 'HP Kontak', 'City, Country' => 'Kota, Negara', 'Street Address' => 'Alamat Jalan',
        'All units' => 'Semua satuan', 'Search code or name...' => 'Cari kode atau nama...', 'Type' => 'Jenis', 'Conversions' => 'Konversi', 'Items using it' => 'Barang pemakai',

        // Form Barang
        'Stock on hand' => 'Stok saat ini',
        'Reorder and Price' => 'Pesan Ulang dan Harga', 'Item Name' => 'Nama Barang', 'Item Code' => 'Kode Barang', 'Rack / Storage Location' => 'Lokasi Rak / Penyimpanan',
        'Unique. Cannot be changed after saving.' => 'Unik. Tidak bisa diubah setelah disimpan.', 'Unit Price (IDR)' => 'Harga Satuan (Rp)',
        'Suggested quantity to order.' => 'Jumlah pesanan yang disarankan.', 'Days from placing the order until the supplier delivers.' => 'Hari sejak pesanan dibuat sampai supplier mengirim.',
        'Reference price. Stock valuation uses the moving average of purchase prices.' => 'Harga acuan. Valuasi stok memakai rata-rata bergerak harga pembelian.',

        // Form Supplier
        'Supplier Code' => 'Kode Supplier', 'Supplier Name' => 'Nama Supplier', 'Company Phone (PSTN)' => 'Telepon Kantor (PSTN)', 'Landline, with area code.' => 'Telepon kabel, dengan kode area.',
        'Contact Name' => 'Nama Kontak', 'Contact Mobile (smartphone)' => 'HP Kontak (smartphone)', 'Also used for WhatsApp.' => 'Juga dipakai untuk WhatsApp.',
        'District, City / Regency, Province, Country' => 'Kecamatan, Kota / Kabupaten, Provinsi, Negara', 'Search district / city / postcode...' => 'Cari kecamatan / kota / kode pos...',
        'One choice gives district, city / regency, province, country, and the postal code.' => 'Satu pilihan mengisi kecamatan, kota / kabupaten, provinsi, negara, dan kode pos.',
        'Region' => 'Wilayah', 'Postcode' => 'Kode Pos',

        // Master Kategori dan Lokasi Penyimpanan, kedaluwarsa
        'Categories' => 'Kategori', 'Storage Locations' => 'Lokasi Penyimpanan', 'Storage Location' => 'Lokasi Penyimpanan',
        'Code Prefix' => 'Awalan Kode', 'Category Name' => 'Nama Kategori', 'Exactly 3 letters or digits. Cannot be changed after saving.' => 'Tepat 3 huruf atau angka. Tidak bisa diubah setelah disimpan.',
        'Location Code' => 'Kode Lokasi', 'Location Name' => 'Nama Lokasi', 'Unique, for example A1-01. Cannot be changed after saving.' => 'Unik, misalnya A1-01. Tidak bisa diubah setelah disimpan.',
        'All locations' => 'Semua lokasi', 'All categories' => 'Semua kategori', 'Expiry Date' => 'Tanggal Kedaluwarsa', 'Expiry' => 'Kedaluwarsa',

        // Kedaluwarsa
        'Near expiry / expired' => 'Hampir kedaluwarsa / kedaluwarsa', 'Expiry Report' => 'Laporan Kedaluwarsa',
        'Expires within (days)' => 'Kedaluwarsa dalam (hari)', 'Days Left' => 'Sisa Hari', 'Items with stock that are expired or about to expire' => 'Barang yang masih ada stoknya dan sudah / hampir kedaluwarsa',

        // Barang dua tingkat: produk + batch
        'Batches' => 'Batch', 'Batch Code' => 'Kode Batch', 'Nearest Expiry' => 'Kedaluwarsa Terdekat', 'Available Stock' => 'Stok Tersedia',
        'Item has an expiry date' => 'Barang berkedaluwarsa',
        'Generated automatically: category prefix and running number. The expiry date is never part of the item code; each expiry batch has its own batch code.' => 'Dibuat otomatis: awalan kategori dan nomor urut. Tanggal kedaluwarsa tidak menjadi bagian kode barang; tiap batch kedaluwarsa punya kode batch sendiri.',
        'Tick for items that expire. The expiry date is then entered on every Receiving / Opening Balance line and each date becomes its own batch. Cannot be changed after saving.' => 'Centang untuk barang yang kedaluwarsa. Tanggalnya diisi di setiap baris Penerimaan / Saldo Awal dan tiap tanggal menjadi satu batch. Tidak bisa diubah setelah disimpan.',
        'Alert shows when the available stock (all batches that are not expired) is at or below this quantity. 0 = no alert.' => 'Peringatan muncul bila stok tersedia (semua batch yang belum kedaluwarsa) sama dengan atau di bawah jumlah ini. 0 = tanpa peringatan.',

        // Form Satuan
        'Unit Code' => 'Kode Satuan', 'Unit Name' => 'Nama Satuan', 'Short, lowercase (pcs, box, kg). Cannot be changed after saving.' => 'Singkat, huruf kecil (pcs, box, kg). Tidak bisa diubah setelah disimpan.',
        'Sets the colour of the unit in lists (for example weight units share one colour).' => 'Menentukan warna satuan di daftar (misalnya satuan berat satu warna).',
        '+ Add Conversion' => '+ Tambah Konversi', 'Factor' => 'Faktor',

        // Dokumen stok
        'Document No.' => 'No. Dokumen', 'Date & Time' => 'Tanggal & Jam', 'Entered by' => 'Diinput oleh', 'Balance As Of (period start)' => 'Saldo per Tanggal (awal periode)',
        'Only for items without any stock movement yet.' => 'Hanya untuk barang yang belum punya pergerakan stok.', 'Reference No. (delivery note / PO / invoice)' => 'No. Referensi (surat jalan / PO / faktur)',
        'Recipient' => 'Penerima', 'Forgot password?' => 'Lupa password?', 'Forgot password' => 'Lupa password', 'Enter your username or e-mail. We will send a link to set a new password.' => 'Isi username atau e-mail Anda. Kami kirim tautan untuk mengatur password baru.', 'Send reset link' => 'Kirim tautan reset', 'Back to sign in' => 'Kembali ke halaman masuk', 'Reset password' => 'Atur ulang password', 'Choose a new password (at least 8 characters).' => 'Pilih password baru (minimal 8 karakter).', 'New password' => 'Password baru', 'Confirm new password' => 'Konfirmasi password baru', 'Save new password' => 'Simpan password baru', 'Groups' => 'Grup', 'Group' => 'Grup', 'Group Code' => 'Kode Grup', 'Group Name' => 'Nama Grup', 'All groups' => 'Semua grup', 'Members' => 'Anggota', 'Modules' => 'Modul', 'Module' => 'Modul', 'Menu' => 'Menu', 'Minimum Role' => 'Peran Minimal', 'Groups With Access' => 'Grup yang Punya Akses', 'All modules' => 'Semua modul', 'Search code, module...' => 'Cari kode, modul...', 'Decides which modules this user can open. Empty = the default modules of the role.' => 'Menentukan modul yang boleh dibuka pengguna ini. Kosong = modul bawaan peran.', 'Users' => 'Pengguna', 'Full Name' => 'Nama Lengkap', 'Username' => 'Username', 'Role' => 'Peran', 'Password' => 'Password', 'All users' => 'Semua pengguna', 'Search name, username, email...' => 'Cari nama, username, email...', 'Lowercase. Cannot be changed after saving.' => 'Huruf kecil. Tidak bisa diubah setelah disimpan.', 'At least 8 characters.' => 'Minimal 8 karakter.', 'Leave empty to keep the current password.' => 'Kosongkan bila password tidak diganti.', 'Data Entry: receiving and issuance. Supervisor: all stock functions. Administrator: Supervisor + Security menu.' => 'Data Entry: penerimaan dan pengeluaran. Supervisor: semua fungsi stok. Administrator: Supervisor + menu Keamanan.', 'Language' => 'Bahasa', 'Add Recipient' => 'Tambah Penerima', 'Add Department' => 'Tambah Departemen', 'Recipient Name' => 'Nama Penerima', 'Department Name' => 'Nama Departemen', 'Department Code' => 'Kode Departemen', 'Default Department' => 'Departemen Bawaan', 'Recipients' => 'Penerima', 'Departments' => 'Departemen', 'Issuance Documents' => 'Dokumen Pengeluaran', 'All departments' => 'Semua departemen', 'All recipients' => 'Semua penerima', 'Search code, name...' => 'Cari kode, nama...', 'Search name, department...' => 'Cari nama, departemen...', 'Used in Issuance when no department is chosen.' => 'Dipakai di Pengeluaran bila departemen tidak dipilih.', "Empty = the recipient's default department." => 'Kosong = departemen bawaan penerima.', 'Department' => 'Departemen', 'Purpose (maintenance / ATK ...)' => 'Keperluan (maintenance / ATK ...)',
        'Reason for adjustment / opname' => 'Alasan penyesuaian / opname', 'Notes' => 'Catatan', '+ Add Item' => '+ Tambah Barang',
        'Qty' => 'Jml', 'Purchase Price / Unit' => 'Harga Beli / Satuan', 'Subtotal' => 'Subtotal', '= In stock unit' => '= Dalam satuan stok', 'Line Note' => 'Catatan Baris',
        'Available' => 'Tersedia', 'Opening Qty' => 'Jml Awal', 'Unit Value' => 'Nilai Satuan', 'Valuation' => 'Valuasi', 'System Qty' => 'Jml Sistem',
        'Counted Qty (physical)' => 'Jml Hitung (fisik)', 'Difference' => 'Selisih',
        'Document' => 'Dokumen', 'Reference No.' => 'No. Referensi', 'Reason' => 'Alasan', 'Value' => 'Nilai', 'Status' => 'Status', 'All' => 'Semua', 'Waiting approval' => 'Menunggu persetujuan',
        'Search document, party, reference...' => 'Cari dokumen, pihak, referensi...',

        // Laporan
        'From date' => 'Dari tanggal', 'To date' => 'Sampai tanggal', 'Apply' => 'Terapkan', 'All categories' => 'Semua kategori', 'Filter' => 'Saring',
        'Only items at / below reorder point' => 'Hanya barang di / bawah titik pesan ulang', 'Include inactive items' => 'Termasuk barang nonaktif',
        'Recipient / Department' => 'Penerima / Departemen', 'Choose an item' => 'Pilih barang', 'Select an item and a period, then press Apply to see its stock card.' => 'Pilih barang dan periode, lalu tekan Terapkan untuk melihat kartu stoknya.',
        'Stock movement history per item' => 'Riwayat pergerakan stok per barang', 'Stock position and movement per period' => 'Posisi dan pergerakan stok per periode',
        'Goods received from suppliers' => 'Barang diterima dari supplier', 'Goods issued for maintenance / ATK' => 'Barang keluar untuk maintenance / ATK', 'Physical count vs system stock' => 'Hitung fisik dibanding stok sistem',
        'Period' => 'Periode', 'Scope' => 'Cakupan',
        'Opening' => 'Awal', 'In' => 'Masuk', 'Out' => 'Keluar', 'Adjustment' => 'Penyesuaian', 'Balance' => 'Saldo', 'Closing' => 'Akhir', 'Unit Cost' => 'Biaya Satuan', 'Balance Value' => 'Nilai Saldo',
        'Reference / Note' => 'Referensi / Catatan', 'User' => 'User', 'Lead Time (d)' => 'Lead Time (hari)', 'Avg Cost' => 'Biaya Rata-rata',
        'Qty (stock unit)' => 'Jml (satuan stok)', 'Received as' => 'Diterima sebagai', 'Purchase Price' => 'Harga Beli', 'Amount' => 'Jumlah', 'Cost' => 'Biaya', 'Counted Qty' => 'Jml Hitung',
        'Value Effect' => 'Dampak Nilai', 'Counted by' => 'Dihitung oleh', 'Approved / Rejected by' => 'Disetujui / Ditolak oleh',
        'Per Month' => 'Per Bulan', 'Per Category' => 'Per Kategori', 'Per Status' => 'Per Status', 'Per Supplier' => 'Per Supplier', 'Per Department' => 'Per Departemen',
        'Per Recipient' => 'Per Penerima', 'Per Document' => 'Per Dokumen',
        'Prepared by' => 'Dibuat oleh', 'Warehouse' => 'Gudang', 'Checked by' => 'Diperiksa oleh', 'Received by' => 'Diterima oleh', 'Approved by' => 'Disetujui oleh', 'Supervisor' => 'Supervisor',

        // Form Grup
        'Access per module' => 'Akses per modul',

        // Catatan form
        'RO opens the screen but blocks saving or changing anything. A user also needs a high enough role: Supervisor-only and Administrator-only modules stay closed to lower roles even when granted. Administrators always have full access.' => 'RO membuka layar tetapi menolak menyimpan atau mengubah apa pun. Pengguna juga harus berperan cukup tinggi: modul khusus Supervisor atau Administrator tetap tertutup bagi peran di bawahnya walau diberikan. Administrator selalu punya akses penuh.',

        // Tambah cepat dari dropdown (x-dl::field.select add)
        'Add Supplier' => 'Tambah Supplier', 'Add Recipient' => 'Tambah Penerima', 'Add Department' => 'Tambah Departemen', 'Add Category' => 'Tambah Kategori', 'Add Location' => 'Tambah Lokasi', 'Add Unit' => 'Tambah Satuan',
        'Recipient Name' => 'Nama Penerima', 'Department Name' => 'Nama Departemen',
        'The supplier code is created automatically. Address and region can be completed later in Suppliers.' => 'Kode supplier dibuat otomatis. Alamat dan wilayah bisa dilengkapi nanti di menu Suppliers.',
        'The department code is created automatically.' => 'Kode departemen dibuat otomatis.',
        'Short, lowercase (pcs, box, kg). Cannot be changed after saving.' => 'Singkat, huruf kecil (pcs, box, kg). Tidak bisa diubah setelah disimpan.',

        // Stok Opname (daftar, mulai, kepala layar)
        'Start Count' => 'Mulai Opname', 'Search count, scope...' => 'Cari opname, cakupan...', 'Count Date' => 'Tanggal Opname', 'Filled' => 'Terisi', 'Started by' => 'Dimulai oleh',
        'Counting' => 'Sedang dihitung', 'Review' => 'Tinjau', 'Done' => 'Selesai', 'Difference Threshold (%)' => 'Ambang Selisih (%)', 'Difference Threshold' => 'Ambang Selisih',
        'Category (for scope per Category)' => 'Kategori (untuk cakupan per Kategori)', 'Location (for scope per Location)' => 'Lokasi (untuk cakupan per Lokasi)',
        'Blind count' => 'Hitung buta', 'Lock transactions during count' => 'Kunci transaksi selama opname', 'Unlock' => 'Buka Kunci', 'Cancel Count' => 'Batalkan Opname', 'Transaction Lock' => 'Kunci Transaksi',

        // Stok Opname (lembar input hasil hitung)
        'Not filled' => 'Belum diisi', 'System Stock' => 'Stok Sistem', 'Count Result' => 'Hasil Hitung', 'Counter' => 'Penghitung', 'Not counted' => 'Tidak dihitung', 'Note' => 'Catatan',
        'Add Item' => 'Tambah Barang', 'Finish Count' => 'Selesai Hitung', 'Save Backup' => 'Simpan Cadangan',

        // Stok Opname (tinjau dan persetujuan)
        'Refresh Moved Lines' => 'Perbarui Baris Bergeser', 'Counted Batches' => 'Batch Dihitung', 'Rows with Difference' => 'Baris Berselisih', 'Surplus Value' => 'Nilai Lebih', 'Shortage Value' => 'Nilai Kurang',
        'Net Value' => 'Nilai Bersih', 'Approve Count' => 'Setujui Opname', 'Return to Counting' => 'Kembalikan ke Penghitungan', 'Differences only' => 'Hanya selisih', 'Large differences' => 'Selisih besar',
        'Physical' => 'Fisik', 'Percent' => 'Persen', 'Large' => 'Besar', 'State' => 'Keadaan', 'Recount' => 'Hitung Ulang',

        // Stok Opname (cetak)
        'Print Count Sheet' => 'Cetak Lembar Hitung', 'Print Minutes' => 'Cetak Berita Acara', 'Preview Minutes' => 'Pratinjau Berita Acara',

        // Stok Opname (jadwal dan pengingat)
        'Open Stok Opname' => 'Buka Stok Opname', 'Opname Frequency' => 'Frekuensi Opname', 'Weekday (weekly)' => 'Hari (mingguan)', 'Day of Month (monthly)' => 'Tanggal (bulanan)', 'Save Schedule' => 'Simpan Jadwal', 'Lock' => 'Kunci',
        'Entered By' => 'Diinput oleh',

        // Riwayat Pemesanan
        'Order History' => 'Riwayat Pemesanan', 'Ordered Qty' => 'Jumlah Dipesan', 'Received' => 'Diterima', 'Remaining' => 'Sisa', 'First Received' => 'Pertama Diterima', 'Recorded by' => 'Dicatat oleh',
        'Open' => 'Terbuka', 'Closed manually' => 'Ditutup manual', 'Search item, supplier, reference...' => 'Cari barang, supplier, referensi...',

        // Perusahaan (kop cetakan)
        'Company' => 'Perusahaan', 'Company Name' => 'Nama Perusahaan', 'Weather City' => 'Kota untuk cuaca', 'Latitude' => 'Lintang', 'Longitude' => 'Bujur', 'Legal Entity Name' => 'Nama PT / CV', 'Tax ID (NPWP)' => 'NPWP', 'Phone' => 'Telepon', 'Company Address' => 'Alamat', 'Print Header Style' => 'Tampilan kop',

        // Login / umum
        'Supervisor Gudang' => 'Supervisor Gudang',

        // Tampilan HP (x-dl::phone-shell, x-dl::device-pick)
        'Phone view' => 'Tampilan HP', 'Desktop view' => 'Tampilan Desktop', 'Display' => 'Tampilan', 'Apply' => 'Terapkan', 'Sign out' => 'Keluar',
    ];
}
