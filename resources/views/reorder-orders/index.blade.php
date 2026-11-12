{{-- REMARK
 Tujuan       : Riwayat Pemesanan (modul ORD): semua catatan Catat Pemesanan dengan semua status (Dipesan, Diterima sebagian, Selesai, Ditutup manual) sebagai Grid Transactional polos: tanggal pesan, referensi, barang, supplier, jumlah dipesan / diterima / sisa (satuan stok),
                status berlencana (warna sama dengan Beranda), penerimaan pertama, lead time, pencatat, dan penutupan. Tanpa nilai uang. Pesanan lama tanpa jumlah tampil dengan jumlah "-".
 Sumber hulu  : ReorderOrderController@index (rows; filter rentang tanggal pesan lewat GET).
 Input        : rows, from, to.
 Output       : filter tanggal (GET), x-dl2::grid dengan kotak status (Semua, Terbuka, Selesai, Ditutup manual), saringan Supplier, cari, kolom, ekspor; klik 2x / Open membuka detail.
 Dipakai oleh : route reorder-orders.index (menu Transaksi > Riwayat Pemesanan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Order History'])
@section('body')
    @include('partials.flash')
    <x-dl2::action-form method="get" :action="route('reorder-orders.index')" class="ak-report-filter">
        <x-dl2::form.row :cols="4">
            <x-dl2::field.date name="from" label="From date" :value="$from" />
            <x-dl2::field.date name="to" label="To date" :value="$to" />
        </x-dl2::form.row>
        <x-dl2::action-bar>
            <x-dl2::button type="submit" icon="fa-filter" label="Apply" size="sm" />
            @if ($from || $to)
                <x-dl2::button variant="secondary" size="sm" label="Reset filter" :href="route('reorder-orders.index')" />
            @endif
        </x-dl2::action-bar>
    </x-dl2::action-form>
    <x-dl2::grid id="reorder-orders" actions="worklist" :rows="$rows" :open-url="url('reorder-orders/{id}')" search="Search item, supplier, reference..."
        :phone-cards="['title' => 'product', 'sub' => 'code', 'badge' => 'status', 'tones' => ['Dipesan' => 'success', 'Diterima sebagian' => 'warn', 'Selesai' => 'info', 'Ditutup manual' => 'neutral'], 'meta' => ['ordered_at', 'supplier', 'qty', 'received', 'remaining']]"
        :columns="[
            ['key' => 'ordered_at', 'label' => 'Order Date', 'type' => 'date'],
            ['key' => 'ref_no', 'label' => 'Order Reference No.'],
            ['key' => 'product', 'label' => 'Item', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'supplier', 'label' => 'Supplier'],
            ['key' => 'qty', 'label' => 'Ordered Qty'],
            ['key' => 'received', 'label' => 'Received'],
            ['key' => 'remaining', 'label' => 'Remaining'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['Dipesan' => 'success', 'Diterima sebagian' => 'warn', 'Selesai' => 'info', 'Ditutup manual' => 'neutral']],
            ['key' => 'first_received', 'label' => 'First Received', 'type' => 'date'],
            ['key' => 'lead', 'label' => 'Lead Time (days)'],
            ['key' => 'creator', 'label' => 'Recorded by'],
            ['key' => 'closed', 'label' => 'Closed'],
        ]"
        :stats="[['label' => 'All'], ['label' => 'Open', 'where' => ['status' => ['Dipesan', 'Diterima sebagian']], 'tone' => 'warn'], ['label' => 'Done', 'where' => ['status' => 'Selesai'], 'tone' => 'success'], ['label' => 'Closed manually', 'where' => ['status' => 'Ditutup manual']]]"
        :filters="['supplier' => 'Supplier']" />
@endsection
