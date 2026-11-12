{{-- REMARK
 Tujuan       : Layar sementara untuk menu modul Jays yang belum dibuat (sekarang hanya GA: Perawatan Gedung).
 Sumber hulu  : route soon (parameter name).
 Input        : name.
 Output       : x-dl2::empty-state.
 Dipakai oleh : Menu::layers (Perawatan Gedung).
 Status       : Sementara.
--}}
@php($label = ['gedung' => 'Perawatan Gedung'][$name])
@extends('layout', ['title' => $label])
@section('body')
    <x-dl2::empty-state :title="$label" message="Segera hadir." icon="clock" />
@endsection
