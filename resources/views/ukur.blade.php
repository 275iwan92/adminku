{{-- REMARK
 Tujuan       : Halaman ukur layar publik (GET /ukur): lebar x tinggi CSS, DPR, orientasi, tinggi nyata, area aman, view yang aktif. Dibuka dari HP untuk mengukur layar sungguhan, hasilnya disalin ke chat.
 Sumber hulu  : DevLib x-dl2::screen-info (murni sisi klien); tanpa data server, tanpa login.
 Input        : tidak ada.
 Dipakai oleh : rute ukur (routes/web.php).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Screen info'])
@section('body')
    <x-dl2::screen-info />
@endsection
