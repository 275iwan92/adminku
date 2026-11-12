{{-- REMARK
 Tujuan       : Laporan Stock Opname & Penyesuaian: stok sistem vs fisik, selisih, alasan, tanggal, jam, status persetujuan dan dampak nilai.
 Sumber hulu  : ReportController@adjustment (rm_stock_line tipe ADJ). Hanya Supervisor.
 Input        : sup, from, to, rows.
 Output       : filter (periode + status) + x-dl2::report.
 Dipakai oleh : route reports.adjustment (menu Reports > Opname & Adjustment Report).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Opname & Adjustment Report'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl2::field.select name="status" label="Status" :options="['' => 'Semua', 'draft' => 'Draf', 'approval' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak']" :value="request('status')" />
    </x-report-filter>
    <x-dl2::report phone-list title="Opname & Adjustment Report" subtitle="Physical count vs system stock" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Period' => raw(Devlib2\Locale::d($from).' — '.Devlib2\Locale::d($to)), 'Status' => raw(['draft' => 'Draf', 'approval' => 'Menunggu Persetujuan', 'approved' => 'Disetujui', 'rejected' => 'Ditolak'][request('status')] ?? 'Semua')]"
        group-by="status" :groups="['status' => 'Per Status', 'doc' => 'Per Document']"
        :summary="[['label' => 'Baris dihitung', 'value' => count($rows)], ['label' => 'Dampak nilai bersih', 'sum' => 'effect', 'signed' => true]]"
        :columns="[
            ['key' => 'date', 'label' => 'Date & Time'],
            ['key' => 'doc', 'label' => 'Document No.', 'sub' => 'reason'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Disetujui' => 'success', 'Menunggu Persetujuan' => 'warn', 'Draf' => 'neutral', 'Ditolak' => 'danger', 'Dibatalkan' => 'danger', 'Pembatalan Menunggu Persetujuan' => 'warn']],
            ['key' => 'recipient', 'label' => 'Recipient'],
            ['key' => 'dept', 'label' => 'Department'],
            ['key' => 'item', 'label' => 'Item', 'sub' => 'code'],
            ['key' => 'unit', 'label' => 'Unit'],
            ['key' => 'system', 'label' => 'System Qty', 'type' => 'number'],
            ['key' => 'counted', 'label' => 'Counted Qty', 'type' => 'number'],
            ['key' => 'qty', 'label' => 'Difference', 'type' => 'number', 'signed' => true],
            ['key' => 'effect', 'label' => 'Value Effect', 'type' => 'money', 'sum' => true, 'signed' => true],
            ['key' => 'user', 'label' => 'Counted by'],
            ['key' => 'approver', 'label' => 'Approved / Rejected by'],
        ]"
        :rows="$rows" :signatures="[['Counted by', 'Warehouse'], ['Approved by', 'Supervisor']]" source="rm_stock_line (ADJ)" />
@endsection
