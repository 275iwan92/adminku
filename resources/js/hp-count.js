// Hitung Stok Opname tampilan HP: satu barang per kartu. Isian = kolom teks standar (keyboard HP, pen, pemindai Bluetooth/USB yang bertingkah seperti keyboard + Enter).
// Simpan per barang lewat endpoint yang sama dengan layar desktop (stock-counts.lines); isian yang belum terkirim tertahan (antrean + localStorage) dan dicoba lagi sampai berhasil.
import { canon, validQty } from './hp-number';

const root = document.querySelector('[data-hp-count]');
if (root) {
    const $ = (s) => root.querySelector(s);
    const rows = JSON.parse($('[data-hp-rows]').textContent);
    const { saveUrl, me, key } = root.dataset;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const ui = { qty: $('[data-qty]'), skip: $('[data-skip]'), note: $('[data-note]'), by: $('[data-by]'), more: $('[data-more]'), search: $('[data-search]'), hits: $('[data-hits]'), state: $('[data-save]') };
    const text = (sel, v) => { $(sel).textContent = v; };
    const lsKey = `hpc.${key}`;
    const nf = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 3 });
    let i = 0, busy = false, retry = null, saved = '';

    const show = (v) => (v === '' || v === null || v === undefined ? '' : String(v).replace('.', ','));
    const norm = canon;   // angka Indonesia (sama dengan DevLib numStr): ketikan -> kanonik "1234.5" yang dikirim ke server; tampilan (show) tetap koma
    const snap = (r) => ({ qty: show(r.counted_qty), skipped: !!r.skipped, note: r.note || '', by: r.counted_by || '' });
    const sig = (o) => [norm(o.qty), o.skipped, o.note.trim(), o.by.trim()].join('|');
    const dirty = (r) => sig(r.d) !== sig(r.s);
    const complete = (r) => norm(r.d.qty) !== '' || (r.d.skipped && r.d.note.trim() !== '');
    const adopt = (r, s) => { Object.assign(r, s, { s: snap(s), d: snap(s), conflict: null, bad: null }); };

    rows.forEach((r) => { r.s = snap(r); r.d = snap(r); });

    // Isian yang belum terkirim disimpan di perangkat: tab ditutup atau koneksi putus tidak menghilangkannya.
    const persist = () => {
        const o = Object.fromEntries(rows.filter(dirty).map((r) => [r.id, r.d]));
        try { Object.keys(o).length ? localStorage.setItem(lsKey, JSON.stringify(o)) : localStorage.removeItem(lsKey); } catch { /* penyimpanan dimatikan: antrean di memori saja */ }
    };
    try {
        Object.entries(JSON.parse(localStorage.getItem(lsKey) || '{}')).forEach(([id, d]) => {
            const r = rows.find((x) => String(x.id) === id);
            if (r) r.d = { ...r.s, ...d };
        });
    } catch { /* abaikan */ }

    const pending = () => rows.filter(dirty).length;
    const status = (kind, msg) => { ui.state.dataset.kind = kind; ui.state.querySelector('span').textContent = msg; ui.state.querySelector('button').hidden = kind !== 'fail'; };
    const idle = () => (pending() ? status('dirty', `${pending()} isian belum tersimpan`) : status('ok', saved ? `Tersimpan ${saved}` : 'Siap'));

    function progress() {
        const done = rows.filter(complete).length;
        text('[data-pos]', `Barang ${i + 1} / ${rows.length}`);
        text('[data-done]', `${done} terisi`);
        $('[data-meter]').style.width = `${rows.length ? (done / rows.length) * 100 : 0}%`;
        $('[data-next]').textContent = i === rows.length - 1 ? 'Ringkasan' : 'Barang berikutnya';
        $('[data-prev]').disabled = i === 0;
    }

    function stateLabel(r) {
        const st = $('[data-state]');
        st.textContent = complete(r) ? (r.d.skipped ? 'Tidak dihitung' : 'Sudah dihitung') : (r.d.skipped ? 'Tidak dihitung: isi catatan' : 'Belum dihitung');
        st.dataset.on = complete(r) ? '1' : '0';
    }

    function paint() {
        const r = rows[i];
        text('[data-name]', r.name);
        text('[data-meta]', [r.code, r.batch !== '—' ? `Exp ${r.batch}` : null, r.location ? `Rak ${r.location}` : null].filter(Boolean).join(' · '));
        text('[data-unit]', r.unit);
        const sys = $('[data-system]');
        sys.hidden = r.system_qty === undefined;   // hitung buta: server tidak mengirim stok sistem
        if (!sys.hidden) sys.textContent = `Stok sistem: ${nf.format(r.system_qty)} ${r.unit}`;
        ui.qty.value = r.d.qty; ui.skip.checked = r.d.skipped; ui.note.value = r.d.note; ui.by.value = r.d.by;
        ui.qty.disabled = r.d.skipped;
        ui.more.open = r.d.skipped || r.d.note !== '';
        const other = r.by_id && String(r.by_id) !== me && (r.s.qty !== '' || r.s.skipped);
        const warn = $('[data-warn]');
        warn.hidden = !other;
        if (other) warn.textContent = `Sudah diisi ${r.by} pukul ${r.at}: ${r.s.skipped ? 'tidak dihitung' : `${r.s.qty} ${r.unit}`}. Mengubahnya akan menimpa isian itu.`;
        const err = $('[data-err]');
        err.hidden = !r.bad; err.textContent = r.bad || '';
        const c = $('[data-conflict]');
        c.hidden = !r.conflict;
        if (r.conflict) c.querySelector('[data-conflict-msg]').textContent = `${r.conflict.msg.replace(/\s*Isi alasan.*$/, '')} Isi alasan lalu Timpa, atau Buang isian saya.`;
        stateLabel(r);
        progress();
    }

    async function send(r, reason) {
        const row = { id: r.id, rev: r.rev, counted_qty: norm(r.d.qty), counted_by: r.d.by, skipped: r.d.skipped, note: r.d.note };
        const sent = sig(r.d);
        const res = await fetch(saveUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify({ changed: [row], compact: 1, ...(reason ? { reason } : {}) }),
        });
        if (res.status === 409) { const j = await res.json(); r.conflict = { msg: j.error, row: j.row }; return 'conflict'; }
        if (res.status === 422) { r.bad = Object.values((await res.json()).errors || {})[0]?.[0] || 'Isian ditolak server.'; return 'bad'; }
        if (!res.ok) throw new Error(res.status === 419 ? 'Sesi habis: muat ulang halaman (isian tersimpan di perangkat ini).' : `Server menjawab ${res.status}`);
        const s = (await res.json()).rows.find((x) => x.id === r.id);
        const stillMine = sig(r.d) === sent;
        Object.assign(r, { rev: s.rev, by_id: s.by_id, by: s.by, at: s.at, filled: s.filled, s: snap(s) });
        if (stillMine) r.d = snap(s);   // tampilkan hasil normalisasi server (mis. penghitung yang diisi otomatis)
        return 'ok';
    }

    // Kirim satu per satu, urut; berhenti di kegagalan jaringan (dicoba lagi otomatis); konflik / penolakan menunggu pengguna.
    async function flush(reason) {
        if (busy) return;
        busy = true; clearTimeout(retry);
        try {
            for (let r = next(); r; r = next()) {
                status('saving', 'Menyimpan…');
                if (await send(r, reason) === 'ok') {
                    saved = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    reason = null;
                }
                persist();
                if (rows[i] === r) paint();
            }
            idle();
        } catch (e) {
            status('fail', `Gagal menyimpan: ${pending()} isian tertahan (aman di perangkat). ${e instanceof TypeError ? 'Koneksi terputus, dicoba lagi otomatis.' : e.message}`);
            retry = setTimeout(() => flush(), 8000);
        } finally { busy = false; progress(); if (!$('[data-summary]').hidden) summary(true); }
    }
    const next = () => rows.find((x) => dirty(x) && !x.conflict && !x.bad && validQty(x.d.qty));

    function go(n) {
        commit();
        if (n < 0) return;
        if (n >= rows.length) { summary(true); return; }
        i = n; summary(false); paint();
        ui.qty.focus({ preventScroll: true });
        ui.qty.select?.();
    }

    function commit() { persist(); idle(); if (pending()) flush(); }

    function edit() {
        const r = rows[i], skipNow = ui.skip.checked;
        if (skipNow && !r.d.skipped) ui.qty.value = '';
        Object.assign(r.d, { qty: ui.qty.value, skipped: skipNow, note: ui.note.value, by: ui.by.value });
        r.bad = validQty(r.d.qty) ? null : 'Isi angka 0 atau lebih, paling banyak 3 desimal; desimal pakai koma (kosong = belum dihitung).';
        ui.qty.disabled = skipNow;
        $('[data-err]').hidden = !r.bad; $('[data-err]').textContent = r.bad || '';
        if (skipNow) ui.more.open = true;
        stateLabel(r); persist(); idle(); progress();
    }

    function summary(on) {
        $('[data-single]').hidden = on; $('[data-summary]').hidden = !on;
        if (!on) return;
        const open = rows.map((r, n) => [r, n]).filter(([r]) => !complete(r));
        text('[data-sum-line]', `${rows.length - open.length} dari ${rows.length} barang terisi.`);
        $('[data-sum-open]').innerHTML = '';
        open.slice(0, 60).forEach(([r, n]) => {
            const b = document.createElement('button');
            b.type = 'button'; b.className = 'sstock-hpc__open'; b.textContent = `${r.code} · ${r.name}`;
            b.addEventListener('click', () => go(n));
            $('[data-sum-open]').append(b);
        });
        text('[data-sum-more]', open.length > 60 ? `… dan ${open.length - 60} barang lain belum terisi.` : '');
        $('[data-finish]').hidden = open.length > 0 || pending() > 0;
        $('[data-not-yet]').hidden = open.length === 0;
        progress();
    }

    // Cari: kode persis (satu hasil) langsung melompat; selain itu daftar hasil. Pemindai barcode mengetik kode + Enter di kolom ini.
    const find = (q) => {
        q = q.trim().toLowerCase();
        return q === '' ? [] : rows.map((r, n) => [r, n]).filter(([r]) => `${r.code} ${r.name} ${r.location}`.toLowerCase().includes(q));
    };
    function hits() {
        const q = ui.search.value.trim(), list = find(q).slice(0, 6);
        ui.hits.innerHTML = '';
        ui.hits.hidden = q === '';
        if (q !== '' && !list.length) { ui.hits.textContent = 'Tidak ketemu.'; return; }
        list.forEach(([r, n]) => {
            const b = document.createElement('button');
            b.type = 'button'; b.textContent = `${r.code} · ${r.name}${r.batch !== '—' ? ` · Exp ${r.batch}` : ''}`;
            b.addEventListener('click', () => jump(n));
            ui.hits.append(b);
        });
    }
    function jump(n) { ui.search.value = ''; ui.hits.hidden = true; go(n); }

    ui.search.addEventListener('input', hits);
    ui.search.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const q = ui.search.value.trim().toLowerCase(), all = find(q), exact = all.filter(([r]) => r.code.toLowerCase() === q);
        const pick = exact.length === 1 ? exact[0] : (all.length === 1 ? all[0] : null);
        if (pick) jump(pick[1]); else hits();
    });
    ui.qty.addEventListener('input', edit);
    ui.qty.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); edit(); if (!rows[i].bad) go(i + 1); } });
    ui.qty.addEventListener('blur', commit);
    [ui.note, ui.by].forEach((el) => {
        el.addEventListener('input', edit);
        el.addEventListener('blur', commit);
        el.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ui.qty.focus(); } });
    });
    ui.skip.addEventListener('change', () => { edit(); commit(); });
    $('[data-next]').addEventListener('click', () => go(i + 1));
    $('[data-prev]').addEventListener('click', () => go(i - 1));
    $('[data-open-summary]').addEventListener('click', () => { commit(); summary(true); });
    $('[data-back-count]').addEventListener('click', () => go(i));
    $('[data-retry]').addEventListener('click', () => flush());
    $('[data-overwrite]').addEventListener('click', () => {
        const why = $('[data-reason]').value.trim();
        if (why.length < 3) { $('[data-reason]').focus(); return; }
        rows[i].conflict = null; paint(); flush(why);
    });
    $('[data-discard]').addEventListener('click', () => {
        const r = rows[i];
        if (r.conflict?.row) adopt(r, r.conflict.row);
        persist(); paint(); idle();
    });
    window.addEventListener('online', () => flush());
    document.addEventListener('visibilitychange', () => { if (document.hidden) commit(); });   // layar HP dikunci / pindah aplikasi: kirim yang tertahan
    window.addEventListener('beforeunload', (e) => { if (pending()) { e.preventDefault(); e.returnValue = ''; } });

    paint(); idle();
    if (pending()) flush();
}
