<?php

/* REMARK
 * Tujuan       : Pesan validasi Laravel dalam bahasa Indonesia (semua aturan yang dipakai aplikasi) dan nama isian yang ramah. :Attribute = nama isian dengan huruf besar di awal kalimat.
 *                Tanggal batas "today" tidak dibaca apa adanya ke pengguna: pesan tanggal memakai kata "hari ini".
 * Dipakai oleh : semua Form Request dan $request->validate (APP_LOCALE=id).
 * Status       : Aktif.
 */
return [
    'after_or_equal' => ':Attribute tidak boleh sebelum :date.',
    'array' => ':Attribute harus berupa daftar.',
    'before_or_equal' => ':Attribute tidak boleh setelah hari ini.',
    'between' => [
        'array' => ':Attribute harus berisi :min sampai :max item.',
        'file' => ':Attribute harus berukuran :min sampai :max kilobita.',
        'numeric' => ':Attribute harus antara :min dan :max.',
        'string' => ':Attribute harus :min sampai :max karakter.',
    ],
    'boolean' => ':Attribute harus berupa ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak sama.',
    'date' => ':Attribute bukan tanggal yang valid.',
    'dimensions' => 'Ukuran gambar :attribute tidak sesuai.',
    'distinct' => ':Attribute berisi nilai ganda.',
    'email' => ':Attribute harus berupa alamat e-mail yang valid.',
    'exists' => ':Attribute yang dipilih tidak valid.',
    'file' => ':Attribute harus berupa berkas.',
    'gt' => [
        'numeric' => ':Attribute harus lebih dari :value.',
        'string' => ':Attribute harus lebih dari :value karakter.',
    ],
    'in' => ':Attribute yang dipilih tidak valid.',
    'integer' => ':Attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':Attribute tidak boleh lebih dari :max item.',
        'file' => ':Attribute tidak boleh lebih dari :max kilobita.',
        'numeric' => ':Attribute tidak boleh lebih dari :max.',
        'string' => ':Attribute tidak boleh lebih dari :max karakter.',
    ],
    'mimetypes' => 'Jenis berkas :attribute tidak didukung.',
    'min' => [
        'array' => ':Attribute minimal :min item.',
        'file' => ':Attribute minimal :min kilobita.',
        'numeric' => ':Attribute minimal :min.',
        'string' => ':Attribute minimal :min karakter.',
    ],
    'not_in' => ':Attribute yang dipilih tidak valid.',
    'numeric' => ':Attribute harus berupa angka.',
    'qty_scale' => ':Attribute paling banyak 3 angka desimal (mis. 1,125); tidak dibulatkan otomatis.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':Attribute wajib diisi.',
    'required_if' => ':Attribute wajib diisi bila :other adalah :value.',
    'required_with' => ':Attribute wajib diisi bila :values diisi.',
    'required_without' => ':Attribute wajib diisi bila :values tidak diisi.',
    'string' => ':Attribute harus berupa teks.',
    'unique' => ':Attribute sudah dipakai.',

    'attributes' => [
        'login' => 'username / e-mail', 'password' => 'password', 'password_confirmation' => 'konfirmasi password',
        'code' => 'kode', 'name' => 'nama', 'category_id' => 'kategori', 'unit' => 'satuan', 'location' => 'lokasi', 'expiry_date' => 'tanggal kedaluwarsa',
        'unit_price' => 'harga satuan', 'reorder_point' => 'titik pesan ulang', 'reorder_qty' => 'jumlah pesan ulang', 'lead_time_days' => 'lead time',
        'phone' => 'telepon kantor', 'contact_name' => 'nama kontak', 'contact_mobile' => 'HP kontak', 'address' => 'alamat jalan', 'area' => 'wilayah',
        'supplier_id' => 'supplier', 'recipient_id' => 'penerima', 'department_id' => 'departemen', 'group_id' => 'grup', 'role' => 'peran', 'username' => 'username', 'email' => 'e-mail', 'ref_no' => 'no. referensi', 'recipient' => 'penerima', 'department' => 'departemen', 'reason' => 'alasan', 'note' => 'catatan',
        'doc_at' => 'tanggal', 'kind' => 'jenis', 'conversions' => 'konversi', 'lines' => 'baris barang',
        'ordered_at' => 'tanggal pesan', 'item_id' => 'barang', 'qty' => 'jumlah dipesan', 'from' => 'tanggal awal', 'to' => 'tanggal akhir', 'factor' => 'faktor', 'tracks_expiry' => 'tanda kedaluwarsa',
        'lines.*.batch_id' => 'barang', 'lines.*.item_id' => 'barang', 'lines.*.qty' => 'jumlah', 'lines.*.unit' => 'satuan', 'lines.*.unit_cost' => 'harga', 'lines.*.counted_qty' => 'jumlah hitung', 'lines.*.note' => 'catatan baris',
        'lines.*.expiry_date' => 'tanggal kedaluwarsa',
        'conversions.*.to_unit' => 'satuan tujuan', 'conversions.*.factor' => 'faktor',
    ],
];
