{{-- REMARK
 Tujuan       : Kartu Aset (hanya baca): data aset (kategori, lokasi, tanggal beli, nilai, garansi, kondisi), jadwal berkala aktif, ringkasan biaya (total, 12 bulan, tenaga, material) dan seluruh riwayat perawatan (tiket) dengan statusnya.
 Sumber hulu  : FacCareController@show.
 Input        : asset, orders, plans, over, total, labor, material, year.
 Output       : kotak ringkasan + x-dl2::grid riwayat; tombol Lapor Kerusakan bagi yang berhak menulis tiket.
 Dipakai oleh : route fac-care.show (tombol Kartu Aset di form Aset).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Kartu Aset'])
@section('body')
    @php
        use App\Services\FacCare;
        use App\Services\FacCustody;
        $moves = $asset->handovers()->with('employee', 'department')->orderByDesc('doc_date')->orderByDesc('id')->get()->map(fn ($h) => [
            'id' => $h->id, 'no' => $h->number(), 'type' => \App\Models\FacHandover::TYPES[$h->type], 'holder' => $h->holderText(), 'date' => $h->doc_date->format('Y-m-d'), 'back' => $h->expected_return?->format('Y-m-d'), 'status' => FacCustody::STATUS[$h->status],
        ])->all();
        $u = auth()->user();
        $rp = fn ($v) => 'Rp '.number_format((float) $v, 0, ',', '.');
        $rows = $orders->map(fn ($o) => [
            'id' => $o->id, 'no' => $o->number(), 'title' => $o->title, 'date' => ($o->finished_at ?? $o->reported_at)->format('Y-m-d'), 'status' => FacCare::STATUS[$o->status], 'priority' => FacCare::PRIORITIES[$o->priority],
            'handler' => $o->handler === 'vendor' ? ($o->vendor?->name ?? 'Vendor') : ($o->technician ?: 'Internal'), 'total' => $o->total(),
        ])->all();
    @endphp
    @include('partials.flash')
    <x-dl2::action-bar align="start">
        <x-dl2::button class="sstock-btn-slate" variant="secondary" icon="fa-arrow-left" label="Kembali" :href="route('fac-care.index')" />
        @if ($u->canWrite('FAT') && $asset->is_active)
            <x-dl2::button class="sstock-btn-coral" variant="secondary" icon="fa-triangle-exclamation" label="Lapor Kerusakan" :href="route('fac-orders.create', ['asset' => $asset->id])" />
        @endif
        @if ($u->canAccess('FAA'))
            <x-dl2::button class="sstock-btn-teal" variant="secondary" icon="fa-pen" label="Ubah Aset" :href="route('fac-assets.show', $asset)" />
        @endif
    </x-dl2::action-bar>
    <h2 style="margin:6px 0 2px">{{ $asset->asset_code }} · {{ $asset->name }}</h2>
    <p class="ak-note">{{ $asset->category->name }} · {{ $asset->place?->path() ?? 'Lokasi belum ditentukan' }}@if ($asset->pic_name) · PJ {{ $asset->pic_name }}@endif @unless ($asset->is_active) · <b>Nonaktif</b>@endunless</p>
    <x-dl2::kpi-row>
        <x-dl2::stat-box label="Kondisi" :value="FacCare::CONDITIONS[$asset->cond]" :tone="['baik' => 'success', 'perlu' => 'warn', 'rusak' => 'danger', 'dihapus' => 'neutral'][$asset->cond]" />
        <x-dl2::stat-box label="Total biaya perawatan" :value="$rp($total)" tone="accent" :sub="'Tenaga '.$rp($labor).' · material '.$rp($material)" />
        <x-dl2::stat-box label="12 bulan terakhir" :value="$rp($year)" tone="accent" />
        <x-dl2::stat-box label="Garansi" :value="$asset->warranty_until ? $asset->warranty_until->format('d-m-Y') : '-'" :tone="$asset->warranty_until ? ($asset->inWarranty() ? 'success' : 'warn') : 'neutral'" :sub="$asset->warranty_until ? ($asset->inWarranty() ? 'Masih berlaku' : 'Sudah habis') : 'Tidak dicatat'" />
    </x-dl2::kpi-row>
    <p class="ak-note"><b>Posisi:</b> {{ FacCustody::POSITIONS[$asset->position] }}@if ($asset->holderText()) · pemegang <b>{{ $asset->holderText() }}</b>@endif @if ($asset->due_back) · wajib kembali {{ $asset->due_back->format('d-m-Y') }}@if ($asset->overdue()) <b>(terlambat)</b>@endif @endif @if ($asset->monitored) · dimonitor GA @endif</p>
    <p class="ak-note">Dibeli {{ $asset->bought_at?->format('d-m-Y') ?? '-' }} · nilai {{ $rp($asset->price) }}.
        @foreach ($plans as $p) Jadwal {{ $p->title }} ({{ FacCare::PERIODS[$p->period] }}): berikutnya {{ $over[$p->id]['due']->format('d-m-Y') }}, {{ $over[$p->id]['label'] }}.@endforeach
    </p>
    @if ($moves)
        <h3 style="margin:14px 0 6px">Riwayat pemegang</h3>
        <x-dl2::grid id="fac-card-holders" :rows="$moves" actions="open" :new-record="null" :open-url="$u->canAccess('FAH') ? url('fac-handovers/{id}') : null"
            :phone-cards="['title' => 'type', 'sub' => 'holder', 'badge' => 'status', 'tones' => FacCustody::STATUS_TONES, 'meta' => ['date']]"
            :columns="[
                ['key' => 'no', 'label' => 'No. ST', 'type' => 'identity', 'avatar' => false],
                ['key' => 'type', 'label' => 'Jenis'],
                ['key' => 'holder', 'label' => 'Pemegang'],
                ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
                ['key' => 'back', 'label' => 'Kembali s.d.', 'type' => 'date'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => FacCustody::STATUS_TONES],
            ]" />
        <h3 style="margin:14px 0 6px">Riwayat perawatan</h3>
    @endif
    <x-dl2::grid id="fac-card" :rows="$rows" actions="open" :new-record="null" :open-url="$u->canAccess('FAT') ? url('fac-orders/{id}') : null" search="Cari riwayat perawatan..."
        :phone-cards="['title' => 'title', 'sub' => 'no', 'badge' => 'status', 'tones' => FacCare::STATUS_TONES, 'meta' => ['date', 'total']]"
        :columns="[
            ['key' => 'no', 'label' => 'No. WO', 'type' => 'identity', 'avatar' => false],
            ['key' => 'title', 'label' => 'Pekerjaan'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'date'],
            ['key' => 'priority', 'label' => 'Prioritas', 'type' => 'badge', 'tones' => FacCare::PRIORITY_TONES],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => FacCare::STATUS_TONES],
            ['key' => 'handler', 'label' => 'Dikerjakan oleh'],
            ['key' => 'total', 'label' => 'Biaya', 'type' => 'money'],
        ]" />
@endsection
