<?php

/* REMARK
 * Tujuan       : Pesan validasi Laravel dalam bahasa Indonesia (hanya aturan yang dipakai aplikasi) dan nama isian yang ramah.
 * Dipakai oleh : semua Form Request dan $request->validate (APP_LOCALE=id).
 * Status       : Aktif.
 */
return [
    'array' => ':attribute harus berupa daftar.',
    'before_or_equal' => ':attribute harus tanggal sebelum atau sama dengan :date.',
    'date' => ':attribute bukan tanggal yang valid.',
    'distinct' => ':attribute berisi nilai ganda.',
    'exists' => ':attribute yang dipilih tidak valid.',
    'gt' => [
        'numeric' => ':attribute harus lebih dari :value.',
        'string' => ':attribute harus lebih dari :value karakter.',
    ],
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa bilangan bulat.',
    'max' => [
        'array' => ':attribute tidak boleh lebih dari :max item.',
        'numeric' => ':attribute tidak boleh lebih dari :max.',
        'string' => ':attribute tidak boleh lebih dari :max karakter.',
    ],
    'min' => [
        'array' => ':attribute minimal :min item.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],
    'not_in' => ':attribute yang dipilih tidak valid.',
    'numeric' => ':attribute harus berupa angka.',
    'regex' => 'Format :attribute tidak valid.',
    'required' => ':attribute wajib diisi.',
    'string' => ':attribute harus berupa teks.',
    'unique' => ':attribute sudah dipakai.',

    'attributes' => [
        'login' => 'username / e-mail', 'password' => 'password',
        'code' => 'kode', 'name' => 'nama', 'category_id' => 'kategori', 'unit' => 'satuan', 'location' => 'lokasi', 'expiry_date' => 'tanggal kedaluwarsa',
        'unit_price' => 'harga satuan', 'reorder_point' => 'titik pesan ulang', 'reorder_qty' => 'jumlah pesan ulang', 'lead_time_days' => 'lead time',
        'phone' => 'telepon kantor', 'contact_name' => 'nama kontak', 'contact_mobile' => 'HP kontak', 'address' => 'alamat jalan', 'area' => 'wilayah',
        'supplier_id' => 'supplier', 'recipient_id' => 'penerima', 'department_id' => 'departemen', 'group_id' => 'grup', 'role' => 'peran', 'username' => 'username', 'email' => 'e-mail', 'ref_no' => 'no. referensi', 'recipient' => 'penerima', 'department' => 'departemen', 'reason' => 'alasan', 'note' => 'catatan',
        'doc_at' => 'tanggal', 'kind' => 'jenis', 'conversions' => 'konversi', 'lines' => 'baris barang',
        'lines.*.batch_id' => 'barang', 'lines.*.qty' => 'jumlah', 'lines.*.unit' => 'satuan', 'lines.*.unit_cost' => 'harga', 'lines.*.counted_qty' => 'jumlah hitung', 'lines.*.note' => 'catatan baris',
        'conversions.*.to_unit' => 'satuan tujuan', 'conversions.*.factor' => 'faktor',
    ],
];
