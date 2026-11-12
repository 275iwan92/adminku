{{-- REMARK
 Tujuan       : Baris Penerimaan menawarkan satuan menurut barangnya: satuan stok + satuan masuk milik barang itu (grup "Satuan lain" untuk satuan tanpa faktor / hanya punya faktor global),
                dan kolom "= In stock unit" menghitung langsung hasil konversi (mis. 5 dus x 12 = 60 pcs) sebelum Confirm. Perhitungan resmi tetap di server (StockDocuments::toStockUnit); ini hanya tampilan.
                Dokumen terkunci menampilkan qty tersimpan (stock_qty dari server), bukan hitung ulang dengan faktor sekarang.
 Sumber hulu  : baris form (packs = JSON ItemUnits::options, stock_unit, unit, qty, stock_qty), daftar satuan aktif ($units).
 Input        : units (kode => label).
 Output       : pengganti `cell` (kolom unit, stock_qty) dan `sync` milik komponen form-lines (x-dl2::lines `lines`) di halaman ini; tidak mengubah DevLib 2.
 Dipakai oleh : docs/form.blade.php (Penerimaan).
 Status       : Aktif.
--}}
<script>
    window.akReady(() => {
        const ALL = @json($units);
        const esc = Devlib2.h.esc, fmt = (n, d) => new Intl.NumberFormat('id', { maximumFractionDigits: d }).format(n);
        const info = r => { try { return JSON.parse(r.packs || ''); } catch (e) { return null; } };
        const label = k => ALL[k] ?? k;
        const factor = (r, p) => (r.unit === p.stock ? 1 : (p.own[r.unit] ?? p.global[r.unit] ?? null));
        const unitCell = (lines, c, r, i) => {
            const p = info(r), opt = (k, text) => `<option value="${esc(k)}" ${k === r.unit ? 'selected' : ''}>${esc(text)}</option>`;
            const known = new Set([p.stock, ...Object.keys(p.own)]);
            let html = '<option value=""></option>' + opt(p.stock, label(p.stock)) + Object.entries(p.own).map(([k, f]) => opt(k, `${label(k)} (1 = ${fmt(+f, 6)} ${p.stock})`)).join('');
            html += '<optgroup label="Satuan lain">' + Object.keys(ALL).filter(k => !known.has(k)).map(k => opt(k, p.global[k] ? `${label(k)} (1 = ${fmt(+p.global[k], 6)} ${p.stock})` : `${label(k)} (faktor belum ada)`)).join('') + '</optgroup>';
            return `<td data-label="${esc(c.label)}"><select class="dl2-select dl2-select-sm" data-k="unit" aria-label="${esc(c.label)} ${i + 1}" required>${html}</select></td>`;
        };
        const converted = (lines, r) => {
            if (lines.ro) return r.stock_unit && r.unit !== r.stock_unit ? `= ${fmt(+r.stock_qty, 3)} ${r.stock_unit}` : '';
            const p = info(r);
            if (!p || !r.unit || r.unit === p.stock) return '';
            const f = factor(r, p), q = +String(r.qty).replace(',', '.');
            return f == null ? 'Faktor belum ada' : q > 0 ? `= ${fmt(q * +f, 3)} ${p.stock}` : '';
        };
        const host = document.querySelector('[data-dl2="form-lines"]');
        const lines = host && Devlib2.classes['form-lines'].getInstance(host);
        if (!lines) { return; }
        const cell = lines.cell, sync = lines.sync;
        lines.cell = function (c, r, i) {
            if (c.key === 'stock_qty') return `<td class="dl2-fl-ro dl2-fl-num" data-label="${esc(c.label)}" data-k="stock_qty">${esc(converted(lines, r))}</td>`;
            if (c.key === 'unit' && !lines.ro && lines.state.get(r) !== 'saved' && info(r)) return unitCell(lines, c, r, i);
            return cell.call(this, c, r, i);
        };
        lines.sync = function () {
            sync.call(this);
            lines.rows.forEach((r, i) => { const td = lines.el.querySelector(`tr[data-i="${i}"] td[data-k="stock_qty"]`); if (td) { td.textContent = converted(lines, r); } });
        };
        lines.paint();
    });
</script>