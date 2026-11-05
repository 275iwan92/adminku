{{-- REMARK
 Tujuan       : Layar Keputusan Pesanan (satu barang): saklar Sumber (Dari master barang / Dari PP) menentukan isi dropdown Barang (lookup master atau baris PP yang menunggu dipesan; Qty otomatis 1 dari master, sisa Qty dari PP); tombol Proses menampilkan riwayat beli per pemasok
                (beli terakhir, harga terakhir, berapa kali, tombol Riwayat membuka rincian tanggal / nomor / qty / harga satuan di bawah barisnya), pilihan pemasok, dan Buat PO.
 Sumber hulu  : PurchaseDecisionController@index (result, outstanding, mode, line).
 Input        : result (null sebelum Proses), outstanding (baris PP), mode, line.
 Output       : GET po-decision.index (Proses); POST po-decision.make (Buat PO).
 Dipakai oleh : route po-decision.index (menu Purchase > Keputusan Pesanan).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Keputusan Pesanan'])
@section('body')
    @php
        $f = fn (string $k, $d = null) => old($k, request()->query($k, $d));
        $money = fn ($n) => \Devlib\Locale::number($n, 0);
        $qty = fn ($n) => \Devlib\Locale::number($n, floor((float) $n) == (float) $n ? 0 : 3);
    @endphp
    @include('partials.flash')
    <x-dl::box>
        <form method="get" action="{{ route('po-decision.index') }}" data-src-form>
            <input type="hidden" name="proses" value="1">
            @php $src = ($f('line') || $f('mode') === 'pp') ? 'pp' : 'master'; @endphp
            <x-dl::field.toggle-group name="mode" label="Sumber" :options="['master' => 'Dari master barang', 'pp' => 'Dari PP (permintaan pembelian)']" :value="$src" />
            <div data-src="master" @if ($src === 'pp') hidden @endif style="margin-top:10px">
                <x-dl::field.lookup name="item_id" lookup="item_pp" label="Barang" :value="$src === 'master' ? $f('item_id') : null" :error="$errors->first('item_id') ?: $errors->first('item_name')" />
            </div>
            <div data-src="pp" @if ($src !== 'pp') hidden @endif style="margin-top:10px">
                <x-dl::field.select name="line" label="Barang dari PP" :value="$f('line')"
                    :options="$outstanding->mapWithKeys(fn ($l) => [$l->id => $l->request->doc_no.' · '.$l->label().' · sisa '.$qty($l->qty - $l->ordered_qty).' '.$l->unit])->all()" />
                @if ($outstanding->isEmpty())<p class="dl-note">Tidak ada baris PP yang menunggu dipesan.</p>@endif
            </div>
            <div style="margin-top:10px"><x-dl::button class="sstock-btn-amber" type="submit" icon="fa-play" label="Proses" /></div>
        </form>
        <script>
            (() => {
                const f = document.querySelector('[data-src-form]'), m = f.querySelector('input[name=mode]');
                const sync = () => f.querySelectorAll('[data-src]').forEach(d => {
                    const on = d.dataset.src === m.value; d.hidden = !on;
                    d.querySelectorAll('input,select').forEach(i => { i.disabled = !on; });
                });
                f.querySelectorAll('[data-dl-segment] button').forEach(b => b.addEventListener('click', () => setTimeout(sync, 0)));
                sync();
            })();
        </script>
    </x-dl::box>

    @if ($result)
        <x-dl::box>
            <h3 style="margin:0 0 8px">{{ $result['name'] }} · {{ $qty($result['qty']) }} {{ $result['unit'] }}</h3>
            <form method="post" action="{{ route('po-decision.make') }}">
                @csrf
                <input type="hidden" name="item_id" value="{{ $result['item']?->id }}">
                <input type="hidden" name="item_name" value="{{ $result['item'] ? '' : $result['name'] }}">
                <input type="hidden" name="qty" value="{{ $result['qty'] }}">
                <input type="hidden" name="unit" value="{{ $result['unit'] }}">
                <input type="hidden" name="line" value="{{ $result['line'] }}">
                @if ($result['history']->isEmpty())
                    <x-dl::alert tone="info" message="Barang ini belum pernah dibeli. Pilih pemasok lewat Pemasok lain." />
                @else
                    <table class="dl-table" style="width:100%">
                        <thead><tr><th>Pemasok</th><th>Beli terakhir</th><th style="text-align:right">Harga terakhir</th><th style="text-align:right">Berapa kali</th><th>Riwayat</th><th>Pilih</th></tr></thead>
                        <tbody>
                        @php $cheapest = $result['history']->min('last_price'); @endphp
                        @foreach ($result['history'] as $h)
                            <tr>
                                <td>{{ $h->supplier }}</td>
                                <td>{{ \Illuminate\Support\Carbon::parse($h->last_at)->format('d-m-Y') }}</td>
                                <td style="text-align:right"><span @if ($h->last_price == $cheapest) style="background:#EAF3DE;color:#27500A;border-radius:4px;padding:0 4px" title="Paling murah" @endif>{{ $money($h->last_price) }}</span> / {{ $result['item']->unit }}</td>
                                <td style="text-align:right">{{ $h->times }}x</td>
                                <td><button type="button" class="sstock-btn-blue" style="padding:2px 10px;border-radius:8px;border:0;color:#fff;background:#185FA5;cursor:pointer" data-hist="h{{ $h->supplier_id }}" aria-expanded="false">Riwayat ▾</button></td>
                                <td><input type="radio" name="supplier_id" value="{{ $h->supplier_id }}" @checked($loop->first) aria-label="Pilih {{ $h->supplier }}"></td>
                            </tr>
                            <tr data-hist-row="h{{ $h->supplier_id }}" hidden>
                                <td colspan="6" style="background:#E6F1FB;color:#0C447C">
                                    <table style="width:100%">
                                        <thead><tr><th style="text-align:left">Tanggal</th><th style="text-align:left">No dokumen</th><th style="text-align:right">Jml</th><th style="text-align:right">Harga satuan</th><th style="text-align:right">Jumlah</th></tr></thead>
                                        <tbody>
                                        @foreach ($h->rows as $r)
                                            <tr><td>{{ \Illuminate\Support\Carbon::parse($r->moved_at)->format('d-m-Y') }}</td><td>{{ $r->doc_no }}</td><td style="text-align:right">{{ $qty($r->qty) }} {{ $result['item']->unit }}</td><td style="text-align:right">{{ $money($r->unit_cost) }}</td><td style="text-align:right">{{ $money($r->qty * $r->unit_cost) }}</td></tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <p class="dl-note">Harga hijau = paling murah. Harga per satuan stok barang.</p>
                @endif
                <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:10px">
                    <label style="min-width:240px">Pemasok lain
                        <select name="other_supplier" class="dl-input" data-other>
                            <option value="">—</option>
                            @foreach ($result['others'] as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <x-dl::button class="sstock-btn-green" type="submit" icon="fa-file-invoice" label="Buat PO" />
                </div>
                @error('supplier_id')<p class="dl-note" style="color:var(--dl-danger,#a32d2d)">{{ $message }}</p>@enderror
            </form>
        </x-dl::box>
        <script>
            document.querySelectorAll('[data-hist]').forEach(b => b.addEventListener('click', () => {
                const row = document.querySelector('[data-hist-row="' + b.dataset.hist + '"]');
                row.hidden = !row.hidden;
                b.setAttribute('aria-expanded', String(!row.hidden));
                b.textContent = row.hidden ? 'Riwayat ▾' : 'Sembunyikan ▴';
            }));
            document.querySelector('[data-other]')?.addEventListener('change', e => {
                if (!e.target.value) return;
                document.querySelectorAll('input[name=supplier_id][type=radio]').forEach(r => { r.checked = false; });
                let h = document.querySelector('input[name=supplier_id][type=hidden]');
                if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'supplier_id'; e.target.form.appendChild(h); }
                h.value = e.target.value;
            });
            document.querySelectorAll('input[name=supplier_id][type=radio]').forEach(r => r.addEventListener('change', () => {
                document.querySelector('input[name=supplier_id][type=hidden]')?.remove();
                const o = document.querySelector('[data-other]'); if (o) o.value = '';
            }));
        </script>
    @endif
@endsection
