{{-- REMARK
 Tujuan       : Layar sementara untuk menu modul Jays yang belum dibuat (GA: Gedung dan AC; HR; Purchase: PR, PO, PV).
 Sumber hulu  : route soon (parameter name).
 Input        : name.
 Output       : x-dl::empty-state.
 Dipakai oleh : Menu::layers (modul GA, HR, Purchase).
 Status       : Sementara.
--}}
@php($label = ['gedung' => 'Perawatan Gedung', 'ac' => 'Perawatan AC', 'employee' => 'Employee', 'recruitment' => 'Recruitment', 'pr' => 'Permohonan Pembelian (PR)', 'po' => 'Purchase Order (PO)', 'pv' => 'Penerimaan Pembelian (PV)'][$name])
@extends('layout', ['title' => $label])
@section('body')
    <x-dl::empty-state :title="$label" message="Segera hadir." icon="clock" />
@endsection
