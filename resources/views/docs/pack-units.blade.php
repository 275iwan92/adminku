{{-- REMARK
 Tujuan       : Baris Penerimaan menawarkan satuan menurut barangnya: satuan stok + satuan masuk milik barang itu (grup "Satuan lain" untuk satuan tanpa faktor / hanya punya faktor global),
                dan kolom "= In stock unit" menghitung langsung hasil konversi (mis. 5 dus x 12 = 60 pcs) sebelum Confirm. Perhitungan resmi tetap di server (StockDocuments::toStockUnit); ini hanya tampilan.
                Dokumen terkunci menampilkan qty tersimpan (stock_qty dari server), bukan hitung ulang dengan faktor sekarang.
 Sumber hulu  : baris form (packs = JSON ItemUnits::options, stock_unit, unit, qty, stock_qty), daftar satuan aktif ($units).
 Input        : units (kode => label).
 Output       : pengganti cellRaw (kolom unit, stock_qty) dan refresh milik komponen x-dl::lines `lines` di halaman ini; tidak mengubah DevLib.
 Dipakai oleh : docs/form.blade.php (Penerimaan).
 Status       : Aktif.
--}}
<script>
    (() => {
        const ALL = @json($units);
        let esc, fmt;
        const info = r => { try { return JSON.parse(r.packs || ''); } catch (e) { return null; } };
        const label = k => ALL[k] ?? k;
        const factor = (r, p) => (r.unit === p.stock ? 1 : (p.own[r.unit] ?? p.global[r.unit] ?? null));
        const unitCell = (lines, r) => {
            const p = info(r), opt = (k, text) => `<option value="${esc(k)}" ${k === r.unit ? 'selected' : ''}>${esc(text)}</option>`;
            let html = '<option value=""></option>';
            if (p) {
                const known = new Set([p.stock, ...Object.keys(p.own)]);
                html += opt(p.stock, label(p.stock)) + Object.entries(p.own).map(([k, f]) => opt(k, `${label(k)} (1 = ${fmt.num(+f, 6)} ${p.stock})`)).join('');
                html += '<optgroup label="Satuan lain">' + Object.keys(ALL).filter(k => !known.has(k)).map(k => opt(k, p.global[k] ? `${label(k)} (1 = ${fmt.num(+p.global[k], 6)} ${p.stock})` : `${label(k)} (faktor belum ada)`)).join('') + '</optgroup>';
            }
            return `<td data-req><select class="dl-lines__in" data-k="unit">${html}</select></td>`;
        };
        const converted = (lines, r) => {
            if (lines.ro) return r.stock_unit && r.unit !== r.stock_unit ? `= ${fmt.num(+r.stock_qty, 3)} ${r.stock_unit}` : '';
            const p = info(r);
            if (!p || !r.unit || r.unit === p.stock) return '';
            const f = factor(r, p), q = +r.qty;
            return f == null ? 'Faktor belum ada' : q > 0 ? `= ${fmt.num(q * +f, 3)} ${p.stock}` : '';
        };
        let tries = 0;
        const attach = () => {
            const lines = window.Devlib && document.querySelector('[data-dl-lines][data-name="lines"]')?.dlComponent;   // skrip ini di atas form dan devlib.js dimuat defer: tunggu sampai komponen terpasang
            if (!lines) { if (tries++ < 100) setTimeout(attach, 50); return; }
            ({esc, fmt} = Devlib.h);
            const cellRaw = lines.cellRaw, refresh = lines.refresh;
            lines.cellRaw = (c, r, i) => {
                if (c.key === 'stock_qty') return `<td class="num dl-lines__ro" data-k="stock_qty">${esc(converted(lines, r))}</td>`;
                if (c.key === 'unit' && !lines.ro && !lines.saved(r)) return unitCell(lines, r);
                return cellRaw(c, r, i);
            };
            lines.refresh = () => {
                refresh();
                lines.rows.forEach((r, i) => { const td = lines.el.querySelector(`tr[data-i="${i}"] td[data-k="stock_qty"]`); if (td) td.textContent = converted(lines, r); });
            };
            lines.body();
        };
        attach();
    })();
</script>
