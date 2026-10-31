{{-- REMARK
 Tujuan       : Jendela "Faktor satuan belum ada" di form Penerimaan. Muncul otomatis setelah Simpan / Confirm ditolak karena satuan baris berbeda dari satuan stok barang dan barang itu belum punya faktor sendiri
                (satuan masuk) maupun pasangan konversi global untuk satuan tersebut.
                Tiap barang + satuan diisi sekali (1 {satuan} = ... {satuan stok}); disimpan sebagai satuan masuk milik BARANG itu (product_units), bukan konversi global; lalu pengguna menekan Simpan / Confirm lagi.
 Sumber hulu  : flash `missing_conversions` dari StockDocuments::assertConversions (item_id, from, to, items).
 Input        : tanpa prop (membaca session).
 Output       : x-dl::modal (dipindah ke <body> di luar <form> dokumen) + fetch POST docs.conversion.
 Dipakai oleh : docs/form.blade.php (Penerimaan, dokumen belum terkunci).
 Status       : Aktif.
--}}
@php $missing = session('missing_conversions', []); @endphp
@if ($missing)
    <x-dl::modal id="conv-missing" title="Faktor satuan belum ada" icon="fa-scale-balanced" size="sm" :open="true">
        <p class="dl-note">Satuan di baris berbeda dari satuan stok barang dan belum ada faktornya. Isi sekali; berlaku untuk barang ini saja, dan dipakai untuk penerimaan berikutnya.</p>
        @foreach ($missing as $m)
            <div class="sstock-conv" data-conv-product="{{ $m['item_id'] }}" data-conv-unit="{{ $m['from'] }}">
                <x-dl::field.text name="conv_factor_{{ $loop->index }}" :label="'1 '.$m['from'].' = … '.$m['to']" placeholder="Contoh: 12" required :hint="'Barang: '.$m['items']" />
            </div>
        @endforeach
        <p class="sstock-quickadd__err" data-conv-error hidden></p>
        <x-slot:footer><x-dl::button type="button" icon="save" label="Simpan Faktor" data-conv-save /></x-slot:footer>
    </x-dl::modal>
    <script>
        (() => {
            const layer = document.querySelector('[data-dl-modal-id="conv-missing"]');
            document.body.append(layer);   // di luar <form> dokumen: isiannya tidak ikut divalidasi / dikirim bersama dokumen
            const err = layer.querySelector('[data-conv-error]');
            layer.querySelector('[data-conv-save]').addEventListener('click', async () => {
                err.hidden = true;
                const rows = [...layer.querySelectorAll('[data-conv-unit]')];
                const bodies = rows.map(r => ({item_id: r.dataset.convProduct, unit: r.dataset.convUnit, factor: parseFloat(r.querySelector('input').value.replace(',', '.'))}));
                if (bodies.some(b => !(b.factor > 0))) { err.textContent = 'Isi faktor dengan angka lebih dari 0.'; err.hidden = false; return; }
                for (const b of bodies) {
                    const res = await fetch(@json(route('docs.conversion', request()->route('type'))), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())}, body: JSON.stringify(b)});
                    if (!res.ok) { const d = await res.json(); err.textContent = d.errors ? Object.values(d.errors)[0][0] : (d.message || 'Gagal menyimpan.'); err.hidden = false; return; }
                }
                layer.classList.remove('is-on');
                Devlib.toast('Faktor tersimpan. Tekan Simpan atau Confirm lagi.', 'success');
            });
        })();
    </script>
@endif
