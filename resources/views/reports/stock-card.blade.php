{{-- REMARK
 Tujuan       : Kartu Stok per item: histori pergerakan (saldo awal, masuk, keluar, penyesuaian, saldo berjalan) dalam rentang tanggal, lengkap tanggal, jam, dokumen, user.
 Sumber hulu  : ReportController@stockCard (rm_stockcard). Kolom biaya & nilai saldo hanya untuk Supervisor.
 Input        : sup, from, to, item, rows, opening, items (pilihan item).
 Output       : filter + x-dl::report (bisa dicetak).
 Dipakai oleh : route reports.stock-card (menu Reports > Stock Card).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Stock Card'])
@section('body')
    <x-report-filter :from="$from" :to="$to" apply="Process">
        <x-dl::field.select name="batch_id" label="Item" :options="$items" :value="$item?->id" required />
    </x-report-filter>
    @if (! $item)
        <x-dl::empty-state title="Choose an item" message="Select an item and a period, then press Process to see its stock card." icon="file" />
    @else
        <x-dl::report phone-list title="Stock Card" subtitle="Stock movement history per item" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
            :criteria="array_filter([
                'Item Code' => raw($item->code), 'Item Name' => raw($item->name), 'Unit' => raw($item->unit), 'Location' => raw($item->location ?? '—'),
                'Period' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to)),
                'Valuation' => $sup ? 'Rata-rata bergerak' : null,
            ])"
            :opening="$opening" group-by="month" :groups="['month' => 'Per Month']"
            :summary="[
                ['label' => 'Saldo Awal', 'value' => $opening],
                ['label' => 'Total Masuk', 'sum' => 'in', 'signed' => true, 'plus' => true],
                ['label' => 'Total Keluar', 'sum' => 'out'],
                ['label' => 'Penyesuaian', 'sum' => 'adj', 'signed' => true],
                ['label' => 'Saldo Akhir', 'last' => 'end'],
            ]"
            :columns="array_values(array_filter([
                ['key' => 'date', 'label' => 'Date & Time'],
                ['key' => 'doc', 'label' => 'Document No.', 'sub' => 'src'],
                ['key' => 'type', 'label' => 'Type', 'type' => 'badge', 'tones' => ['AWAL' => 'success', 'MASUK' => 'info', 'KELUAR' => 'danger', 'PENY' => 'warn']],
                ['key' => 'ref', 'label' => 'Reference / Note'],
                ['key' => 'user', 'label' => 'User'],
                ['key' => 'open', 'label' => 'Opening', 'type' => 'number'],
                ['key' => 'in', 'label' => 'In', 'type' => 'number', 'sum' => true, 'signed' => true],
                ['key' => 'out', 'label' => 'Out', 'type' => 'number', 'sum' => true],
                ['key' => 'adj', 'label' => 'Adjustment', 'type' => 'number', 'sum' => true, 'signed' => true],
                ['key' => 'end', 'label' => 'Balance', 'type' => 'number', 'running' => 'in - out + adj', 'prev' => 'open', 'bold' => true],
                $sup ? ['key' => 'cost', 'label' => 'Unit Cost', 'type' => 'money'] : null,
                $sup ? ['key' => 'bal_value', 'label' => 'Balance Value', 'type' => 'money'] : null,
            ]))"
            :rows="$rows" :signatures="[['Prepared by', 'Warehouse'], ['Checked by', 'Supervisor']]" source="rm_stockcard" />
    @endif
@endsection
