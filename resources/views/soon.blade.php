{{-- REMARK
 Tujuan       : Layar sementara untuk menu modul Jays yang belum dibuat (GA, HR: Employee dan Recruitment, Purchase).
 Sumber hulu  : route soon (parameter name).
 Input        : name.
 Output       : x-dl::empty-state.
 Dipakai oleh : Menu::layers (modul GA, HR, Purchase).
 Status       : Sementara.
--}}
@php($label = ['ga' => 'GA', 'employee' => 'Employee', 'recruitment' => 'Recruitment', 'purchase' => 'Purchase'][$name])
@extends('layout', ['title' => $label])
@section('body')
    <x-dl::empty-state :title="$label" message="Segera hadir." icon="clock" />
@endsection
