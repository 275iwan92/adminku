{{-- REMARK
 Tujuan       : Kartu Saldo per kas: mutasi buku besar kas bergulir (Uang Masuk, Uang Keluar, Penyesuaian, pembatalan) dengan saldo berjalan; filter kas dan periode; cetak / ekspor.
 Sumber hulu  : CashCardController@index (k_cash_ledger).
 Input        : from, to, rows, account, accounts, open, in, out, close.
 Output       : filter (periode + kas) + x-dl::report.
 Dipakai oleh : route cash-card.index (Keuangan > Kartu Saldo Kas).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kartu Saldo Kas'])
@section('body')
    <x-report-filter :from="$from" :to="$to">
        <x-dl::field.select name="cash_account_id" label="Kas" :options="$accounts" :value="$account?->id" />
    </x-report-filter>
    <x-dl::report phone-list title="Kartu Saldo Kas" subtitle="Mutasi buku besar kas dengan saldo berjalan" :company="\App\Support\Company::report()" :printed-by="raw(auth()->user()->name)" paper="a4l"
        :criteria="['Kas' => raw($account?->name ?? '-'), 'Periode' => raw(Devlib\Locale::d($from).' — '.Devlib\Locale::d($to))]"
        :summary="[
            ['label' => 'Saldo Awal', 'value' => $open],
            ['label' => 'Total Masuk', 'value' => $in],
            ['label' => 'Total Keluar', 'value' => $out],
            ['label' => 'Saldo Akhir', 'value' => $close],
        ]"
        :columns="[
            ['key' => 'date', 'label' => 'Tanggal'],
            ['key' => 'type', 'label' => 'Jenis'],
            ['key' => 'doc', 'label' => 'No. Dokumen'],
            ['key' => 'uraian', 'label' => 'Uraian'],
            ['key' => 'masuk', 'label' => 'Masuk', 'type' => 'money', 'sum' => true],
            ['key' => 'keluar', 'label' => 'Keluar', 'type' => 'money', 'sum' => true],
            ['key' => 'saldo', 'label' => 'Saldo Berjalan', 'type' => 'money', 'bold' => true],
        ]"
        :rows="$rows" :signatures="[['Dibuat oleh', 'Admin / Bendahara'], ['Diperiksa oleh', 'Supervisor']]" source="k_cash_ledger" />
@endsection
