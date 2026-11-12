{{-- REMARK
 Tujuan       : Daftar Stok Opname (penghitungan fisik rutin) sebagai Grid Transactional POLOS: nomor SO tebal di kolom pertama, hanya kolom Status yang berwarna. Mulai opname hanya untuk Supervisor.
 Sumber hulu  : StockCountController@index.
 Input        : rows, sup, schedule (App\Services\CountSchedule::state), cfg (jadwal tersimpan).
 Output       : bagian kecil Jadwal opname (Supervisor mengubah, semua melihat), x-dl2::grid; klik 2x / Open membuka layar opname, Start Count (Supervisor) membuka form mulai.
 Dipakai oleh : route stock-counts.index (menu Transaksi > Stok Opname).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Stok Opname'])
@section('body')
    @include('partials.flash')
    <x-dl2::alert :tone="['neutral' => 'info', 'warn' => 'warn', 'danger' => 'danger'][$schedule['tone']]" :message="raw('Jadwal opname: '.$schedule['schedule'].'. '.$schedule['headline'].' '.$schedule['detail'].'.')" />
    @if ($sup)
        <x-dl2::action-form :action="route('stock-counts.schedule')">
            <x-dl2::form.row :cols="4">
                <x-dl2::field.select name="frequency" label="Opname Frequency" :options="['monthly' => 'Bulanan', 'weekly' => 'Mingguan']" :value="old('frequency', $cfg['frequency'])" required :error="$errors->first('frequency') ?: null" />
                <x-dl2::field.select name="weekday" label="Weekday (weekly)" :options="\App\Services\CountSchedule::WEEKDAYS" :value="old('weekday', $cfg['weekday'])" />
                <x-dl2::field.select name="day_of_month" label="Day of Month (monthly)" :options="['last' => 'Akhir bulan'] + array_combine(range(1, 31), range(1, 31))" :value="old('day_of_month', $cfg['day_of_month'])" />
            </x-dl2::form.row>
            <x-dl2::action-bar align="start">
                <x-dl2::button type="submit" variant="secondary" size="sm" icon="save" label="Save Schedule" />
            </x-dl2::action-bar>
        </x-dl2::action-form>
    @endif
    <x-dl2::grid id="stock-counts" actions="worklist" :rows="$rows" :new-record="$sup ? 'Start Count' : null" :new-url="$sup ? route('stock-counts.create') : null" :open-url="url('stock-counts/{id}')" search="Search count, scope..."
        :phone-cards="['title' => 'doc_no', 'sub' => 'scope', 'badge' => 'status', 'tones' => ['Sedang dihitung' => 'warn', 'Tinjau' => 'info', 'Selesai' => 'success', 'Dibatalkan' => 'danger'], 'meta' => ['count_date', 'progress', 'adjustment', 'user']]"
        :columns="[
            ['key' => 'doc_no', 'label' => 'Document', 'type' => 'identity', 'code' => 'scope', 'avatar' => false],
            ['key' => 'count_date', 'label' => 'Count Date', 'type' => 'date'],
            ['key' => 'progress', 'label' => 'Filled'],
            ['key' => 'adjustment', 'label' => 'Adjustment'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Sedang dihitung' => 'warn', 'Tinjau' => 'info', 'Selesai' => 'success', 'Dibatalkan' => 'danger']],
            ['key' => 'user', 'label' => 'Started by'],
        ]"
        :stats="[['label' => 'All'], ['label' => 'Counting', 'where' => ['status' => 'Sedang dihitung'], 'tone' => 'warn'], ['label' => 'Review', 'where' => ['status' => 'Tinjau']], ['label' => 'Done', 'where' => ['status' => 'Selesai'], 'tone' => 'success']]" />
@endsection
