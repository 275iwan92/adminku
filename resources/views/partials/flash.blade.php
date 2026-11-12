{{-- REMARK
 Tujuan       : Pesan hasil aksi di atas layar: sukses (status), peringatan Reorder Point (reorder), dan galat validasi.
 Sumber hulu  : session status / reorder (redirect()->with), $errors.
 Input        : tanpa prop.
 Output       : x-dl2::alert (DevLib 2, komponen daun; tanpa pesan = tidak dirender).
 Dipakai oleh : semua form layar (items, suppliers, dokumen stok).
 Status       : Aktif.
--}}
@if (session('status'))
    <x-dl2::alert variant="success">{{ session('status') }}</x-dl2::alert>
@endif
@if (session('reorder'))
    <x-dl2::alert variant="warning">Titik pesan ulang tercapai: {{ session('reorder') }}</x-dl2::alert>
@endif
@if ($errors->any())
    <x-dl2::alert variant="danger">{{ $errors->first() }}</x-dl2::alert>
@endif
