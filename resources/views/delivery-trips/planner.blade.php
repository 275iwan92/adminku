{{-- REMARK
 Tujuan       : Planner alamat satu trip (kiri daftar berurutan dengan ETA, kanan detail alamat terpilih): tambah alamat di awal / akhir, seret untuk mengubah urutan, hapus dengan konfirmasi, barang diketik banyak baris atau diambil dari Surat Jalan (boleh banyak, pratinjau barang), waktu layanan otomatis dari berat.
 Sumber hulu  : DeliveryTripController (stops, docs). Kantor = titik berangkat dan kembali.
 Input        : stops [{kind, customer, address, travel_min, items [[nama, jumlah, satuan, kg]], docs [id]}], docs [{id, no, cu, it}], locked.
 Output       : isian tersembunyi stops_json (dibaca controller; tiap alamat membawa lat/lng), ETA hanya tampilan. Peta rute (resources/js/trip-map.js, Leaflet + tile OSM) hanya ilustrasi: klik peta menaruh titik alamat terpilih, penanda bisa digeser; jarak lewat TripMap.router (garis lurus x 1,3, dapat diganti).
 Rumus ETA    : layanan = (5 + 10 x kg / 100) menit, dikali 0,7 bila ada asisten; tiba = berangkat + tempuh + layanan alamat sebelumnya. Angka awal asumsi, perlu dikalibrasi dari pengiriman nyata.
 Dipakai oleh : delivery-trips/form.blade.php.
 Status       : Aktif.
--}}
<input type="hidden" name="stops_json" id="stops_json" value="">
<div id="plan" class="sp"></div>
@vite('resources/js/trip-map.js')
<script>
(() => {
    const DOCS = @js($docs), LOCKED = @js($locked), OFFICE = @js($office), UNITS = ['kg', 'karung', 'dus', 'pcs', 'sak'];
    const S = @js($stops).map(s => ({ kind: s.kind, customer: s.customer, address: s.address || '', lat: s.lat ?? null, lng: s.lng ?? null, travel_min: +s.travel_min || 15, items: (s.items || []).map(r => r.slice()), docs: (s.docs || []).slice() }));
    let sel = S.length ? 0 : -1, pickOpen = false, pickSel = [], addAt = null, confirmDel = -1, dragI = -1;
    const root = document.getElementById('plan'), out = document.getElementById('stops_json');
    const q = n => document.querySelector('[name=' + n + ']');
    const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const doc = id => DOCS.find(d => d.id === id);
    const itemsOf = s => s.docs.length ? s.docs.flatMap(id => (doc(id)?.it || []).map(r => r.slice())) : s.items;
    const kg = s => itemsOf(s).reduce((a, r) => a + (parseFloat(r[3]) || 0), 0);
    const crew = () => (q('assistant')?.value || '').trim() ? 0.7 : 1;
    const svc = s => Math.round((5 + kg(s) / 100 * 10) * crew());
    const hm = m => { m = Math.round(m); return String(Math.floor(m / 60) % 24).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); };
    const start = () => { const [h, m] = (q('depart_time')?.value || '08:00').split(':'); return (+h || 0) * 60 + (+m || 0); };
    const back = () => +q('return_min')?.value || 0;
    const times = () => { let c = start(); const o = S.map(s => { c += s.travel_min; const a = c; c += svc(s); return [a, c]; }); return { o, home: c + back() }; };
    const sync = () => { out.value = JSON.stringify(S); };
    const usedElsewhere = (id, i) => S.some((s, k) => k !== i && s.docs.includes(id));
    const badge = k => k === 'ant' ? '<span class="sp-b sp-ant">Antar</span>' : '<span class="sp-b sp-amb">Ambil</span>';
    const bar = pos => addAt === pos
        ? '<span class="sp-s">Jenis:</span><button type="button" class="sp-lk" data-add="ant" data-pos="' + pos + '">' + badge('ant') + '</button><button type="button" class="sp-lk" data-add="amb" data-pos="' + pos + '">' + badge('amb') + '</button><button type="button" class="sp-lk sp-mut" data-add="x">Batal</button>'
        : (LOCKED ? '' : '<button type="button" class="sp-lk sp-bold" data-open="' + pos + '">+ Tambah alamat</button><span class="sp-s">' + (pos === 'top' ? 'di awal · ' : 'di akhir · ') + S.length + ' alamat</span>');

    function listHtml() {
        const t = times();
        let h = '<div class="sp-row sp-home"><span class="sp-n">K</span><div class="sp-grow"><span class="sp-b sp-kan">Kantor</span> Berangkat</div><b>' + hm(start()) + '</b></div>';
        S.forEach((s, i) => {
            const n = itemsOf(s).length;
            h += '<div class="sp-row' + (i === sel ? ' sp-on' : '') + '" ' + (LOCKED ? '' : 'draggable="true" ') + 'data-i="' + i + '">'
                + (LOCKED ? '' : '<span class="sp-grip" title="Seret untuk mengubah urutan">⋮⋮</span>') + '<span class="sp-n">' + (i + 1) + '</span>'
                + '<div class="sp-grow">' + badge(s.kind) + ' <b>' + esc(s.customer || 'Alamat baru') + '</b><div class="sp-s sp-cut">' + esc(s.address || 'Alamat belum diisi') + '</div>'
                + '<div class="sp-s">' + n + ' barang, ' + Math.round(kg(s)) + ' kg' + (s.docs.length ? ' · ' + s.docs.length + ' SJ' : '') + ' · layanan ±' + svc(s) + ' mnt</div></div>'
                + '<div class="sp-r"><b>' + hm(t.o[i][0]) + '</b><div class="sp-s">selesai ' + hm(t.o[i][1]) + '</div></div>'
                + (LOCKED ? '' : '<button type="button" class="sp-del' + (confirmDel === i ? ' sp-cf' : '') + '" data-del="' + i + '" title="Hapus alamat" aria-label="Hapus alamat">' + (confirmDel === i ? 'Hapus?' : '🗑') + '</button>') + '</div>';
        });
        return h + '<div class="sp-row sp-home"><span class="sp-n">K</span><div class="sp-grow"><span class="sp-b sp-kan">Kantor</span> Kembali</div><b>' + hm(t.home) + '</b></div>';
    }

    function pickHtml(s) {
        let h = '<div class="sp-h"><b>Pilih surat jalan untuk ' + esc(s.customer || 'alamat ini') + '</b></div><div class="sp-s">Centang satu atau beberapa. Barang semua surat jalan terpilih digabung di alamat ini.</div>';
        if (!DOCS.length) h += '<p class="sp-s">Belum ada Pengeluaran Barang terkonfirmasi yang bebas.</p>';
        DOCS.forEach(d => {
            const off = usedElsewhere(d.id, sel), on = pickSel.includes(d.id);
            h += '<label class="sp-pc' + (on ? ' sp-pon' : '') + (off ? ' sp-poff' : '') + '"><input type="checkbox" data-pick="' + d.id + '"' + (on ? ' checked' : '') + (off ? ' disabled' : '') + '><div class="sp-grow"><b>' + esc(d.no) + '</b> <span class="sp-s">' + esc(d.cu)
                + (d.cu && d.cu === s.customer ? ' · pelanggan sama' : '') + (off ? ' · dipakai alamat lain' : '') + '</span><div class="sp-s">' + esc(d.it.map(r => r[0] + ' (' + r[1] + ' ' + r[2] + ')').join(', ')) + '</div></div></label>';
        });
        return h + '<div class="sp-act"><a class="sp-lk" href="' + @js(route('docs.create', 'issuance')) + '" target="_blank" rel="noopener">+ Buat surat jalan baru</a><span class="sp-push"></span><button type="button" class="sp-lk sp-mut" data-pc="1">Batal</button><button type="button" class="dl-btn" data-po="1">Pilih (' + pickSel.length + ')</button></div>';
    }

    function detailHtml() {
        const s = S[sel];
        if (!s) return '<p class="sp-s">Belum ada alamat. Klik Tambah alamat.</p>';
        if (pickOpen) return pickHtml(s);
        const ro = LOCKED ? ' disabled' : '', ant = s.kind === 'ant', hasDoc = s.docs.length > 0;
        let h = '<div class="sp-h">' + badge(s.kind) + ' <b>Alamat ' + (sel + 1) + '</b></div>'
            + '<label class="sp-l">Nama pelanggan</label><input class="dl-input" data-f="customer" value="' + esc(s.customer) + '" maxlength="100"' + ro + '>'
            + '<label class="sp-l">Alamat</label><input class="dl-input" data-f="address" value="' + esc(s.address) + '" maxlength="255"' + ro + '>'
            + '<div class="sp-two"><div><label class="sp-l">Jenis</label><select class="dl-input" data-f="kind"' + ro + '><option value="ant"' + (ant ? ' selected' : '') + '>Antar</option><option value="amb"' + (ant ? '' : ' selected') + '>Ambil</option></select></div>'
            + '<div><label class="sp-l">Menit tempuh dari titik sebelumnya</label><input class="dl-input" type="number" min="0" max="600" data-f="travel_min" value="' + s.travel_min + '"' + ro + '></div></div>'
            + '<div class="sp-sec"><b>Barang yang ' + (ant ? 'diantar' : 'diambil') + '</b>' + (ant && !LOCKED ? (hasDoc ? '<span><button type="button" class="sp-lk" data-pk="1">Ubah surat jalan</button> <button type="button" class="sp-lk sp-mut" data-un="1">Lepas semua</button></span>' : '<button type="button" class="sp-lk" data-pk="1">Ambil dari surat jalan</button>') : '') + '</div>';
        if (hasDoc) {
            h += s.docs.map(id => { const d = doc(id); return d ? '<div class="sp-sj"><div class="sp-sjh"><b>' + esc(d.no) + '</b><span class="sp-s">' + esc(d.cu) + '</span></div><table>' + d.it.map(r => '<tr><td>' + esc(r[0]) + '</td><td class="sp-r">' + esc(r[1]) + ' ' + esc(r[2]) + '</td></tr>').join('') + '</table></div>' : ''; }).join('');
        } else {
            h += '<div class="sp-items">' + s.items.map((r, j) => '<div class="sp-it"><input class="dl-input" data-j="' + j + '" data-k="0" value="' + esc(r[0]) + '" placeholder="Nama barang"' + ro + '><input class="dl-input" type="number" step="any" min="0" data-j="' + j + '" data-k="1" value="' + esc(r[1]) + '"' + ro + '>'
                + '<select class="dl-input" data-j="' + j + '" data-k="2"' + ro + '>' + UNITS.map(u => '<option' + (u === r[2] ? ' selected' : '') + '>' + u + '</option>').join('') + '</select><input class="dl-input" type="number" step="any" min="0" data-j="' + j + '" data-k="3" value="' + esc(r[3]) + '" placeholder="kg"' + ro + '>'
                + (LOCKED ? '<span></span>' : '<button type="button" class="sp-x" data-r="' + j + '" aria-label="Hapus barang">×</button>') + '</div>').join('') + '</div>'
                + (LOCKED ? '' : '<button type="button" class="sp-lk" data-ai="1">+ Tambah barang</button>');
        }
        return h + '<p class="sp-s">' + (hasPt(s) ? 'Titik peta sudah ada' + (LOCKED ? '' : ' (seret penanda untuk menggeser)') : (LOCKED ? 'Tanpa titik peta' : 'Klik peta untuk menaruh titik alamat ini')) + '</p><p class="sp-s" id="sp-tot"></p>';
    }

    function totals() { const s = S[sel], e = document.getElementById('sp-tot'); if (s && e) e.textContent = Math.round(kg(s)) + ' kg · layanan ±' + svc(s) + ' mnt' + (crew() < 1 ? ' (sopir + asisten)' : ' (sopir sendiri)'); }
    let map = null;
    const drawMap = fit => map?.draw(S, sel, fit);
    function paintList() { root.querySelector('[data-list]').innerHTML = listHtml(); root.querySelector('[data-top]').innerHTML = bar('top'); root.querySelector('[data-bot]').innerHTML = bar('bot'); sync(); drawMap(false); }
    function paintDetail() { root.querySelector('[data-det]').innerHTML = detailHtml(); totals(); sync(); }
    const all = () => { paintList(); paintDetail(); };
    const hasPt = s => s.lat != null && s.lng != null;
    // Menit tempuh tiap alamat dari titik sebelumnya (kantor untuk yang pertama), lewat router peta; alamat tanpa titik tidak diubah.
    function fillTravel() {
        let prev = OFFICE;
        S.forEach(s => { if (hasPt(s) && prev) s.travel_min = Math.max(1, window.TripMap.router.min(prev, s)); prev = hasPt(s) ? s : null; });
        all();
    }

    root.innerHTML = '<div class="sp-grid"><div class="sp-left"><div class="sp-bar" data-top></div><div data-list></div><div class="sp-bar" data-bot></div>'
        + '<div class="sp-mapbar"><span class="sp-s">Peta rute (ilustrasi urutan, bukan navigasi)</span><span class="sp-push"></span>' + (LOCKED ? '' : '<button type="button" class="sp-lk" data-tt="1">Isi menit tempuh dari peta</button>') + '</div><div class="sp-map" data-map></div></div><div class="sp-right" data-det></div></div>';
    all();
    const initMap = () => { map = window.TripMap.mount(root.querySelector('[data-map]'), OFFICE, LOCKED, {
        onPick: (lat, lng) => { if (sel < 0) return; Object.assign(S[sel], { lat, lng }); all(); },
        onMove: (i, lat, lng) => { Object.assign(S[i], { lat, lng }); paintList(); },
        onSelect: i => { sel = i; confirmDel = -1; pickOpen = false; all(); },
    }); drawMap(true); };
    window.TripMap ? initMap() : addEventListener('DOMContentLoaded', initMap);
    ['depart_time', 'assistant', 'return_min'].forEach(n => q(n)?.addEventListener('input', () => { paintList(); totals(); }));

    root.addEventListener('click', e => {
        const b = e.target.closest('button,a'); const row = e.target.closest('.sp-row[data-i]');
        if (b && b.dataset.open) { addAt = b.dataset.open; paintList(); return; }
        if (b && b.dataset.tt) { fillTravel(); return; }
        if (b && b.dataset.add) {
            if (b.dataset.add !== 'x') { const n = { kind: b.dataset.add, customer: '', address: '', lat: null, lng: null, travel_min: 15, items: [['', 1, 'pcs', '']], docs: [] }; if (b.dataset.pos === 'top') { S.unshift(n); sel = 0; } else { S.push(n); sel = S.length - 1; } pickOpen = false; addAt = null; all(); } else { addAt = null; paintList(); }
            return;
        }
        if (b && b.dataset.del !== undefined) { const i = +b.dataset.del; if (confirmDel === i) { S.splice(i, 1); sel = Math.min(sel, S.length - 1); confirmDel = -1; pickOpen = false; all(); } else { confirmDel = i; paintList(); } return; }
        if (b && b.dataset.pk) { pickSel = S[sel].docs.slice(); pickOpen = true; paintDetail(); return; }
        if (b && b.dataset.pc) { pickOpen = false; paintDetail(); return; }
        if (b && b.dataset.po) { const s = S[sel]; s.docs = pickSel.slice(); if (s.docs.length) s.items = []; else if (!s.items.length) s.items = [['', 1, 'pcs', '']]; pickOpen = false; all(); return; }
        if (b && b.dataset.un) { S[sel].docs = []; if (!S[sel].items.length) S[sel].items = [['', 1, 'pcs', '']]; all(); return; }
        if (b && b.dataset.ai) { S[sel].items.push(['', 1, 'pcs', '']); all(); return; }
        if (b && b.dataset.r !== undefined) { S[sel].items.splice(+b.dataset.r, 1); all(); return; }
        if (row) { sel = +row.dataset.i; confirmDel = -1; pickOpen = false; all(); }
    });
    root.addEventListener('input', e => {
        const t = e.target, s = S[sel]; if (!s) return;
        if (t.dataset.f) { s[t.dataset.f] = t.dataset.f === 'travel_min' ? (+t.value || 0) : t.value; paintList(); totals(); }
        else if (t.dataset.k !== undefined) { s.items[+t.dataset.j][+t.dataset.k] = t.value; paintList(); totals(); }
    });
    root.addEventListener('change', e => {
        const t = e.target, s = S[sel];
        if (t.dataset.f === 'kind') { s.kind = t.value; if (s.kind === 'amb') { s.docs = []; if (!s.items.length) s.items = [['', 1, 'pcs', '']]; } all(); }
        else if (t.dataset.pick) { const id = +t.dataset.pick, k = pickSel.indexOf(id); if (t.checked && k < 0) pickSel.push(id); if (!t.checked && k >= 0) pickSel.splice(k, 1); paintDetail(); }
    });
    root.addEventListener('dragstart', e => { const r = e.target.closest('.sp-row[data-i]'); if (r) { dragI = +r.dataset.i; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', String(dragI)); } });
    root.addEventListener('dragover', e => { if (dragI >= 0) e.preventDefault(); });
    root.addEventListener('drop', e => { e.preventDefault(); const r = e.target.closest('.sp-row[data-i]'); if (r && dragI >= 0 && +r.dataset.i !== dragI) { const k = +r.dataset.i, it = S.splice(dragI, 1)[0]; S.splice(k, 0, it); sel = k; } dragI = -1; all(); });
    root.addEventListener('dragend', () => { dragI = -1; });
    root.closest('form')?.addEventListener('submit', sync);
})();
</script>
