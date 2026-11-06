/* REMARK
 * Tujuan       : Perilaku halaman Panduan Pengguna (help/user-guide): penukar empat mode (Presentasi, Interaktif, Blog, Detail), routing hash (#gd-, #gt-, #gb-, lainnya = Detail),
 *                mesin presentasi bertingkat (putar otomatis, jeda, swipe, panah, slide "kamera"), misi interaktif dengan Driver.js (gating klik titik), dan blog (pencarian, saringan, artikel).
 * Input        : DOM dari help/ug/modes/*.blade.php dan atribut data-*.
 * Dipakai oleh : resources/views/help/user-guide.blade.php lewat @vite('resources/js/guide.js').
 * Status       : Aktif. Tidak memuat apa pun bila #ug-root tidak ada.
 */
import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';

const root = document.getElementById('ug-root');
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const $ = (sel, ctx = root) => ctx.querySelector(sel);
const $$ = (sel, ctx = root) => [...ctx.querySelectorAll(sel)];
const store = {
    get: (k, d) => { try { return JSON.parse(localStorage.getItem(k)) ?? d; } catch { return d; } },
    set: (k, v) => { try { localStorage.setItem(k, JSON.stringify(v)); } catch { /* penyimpanan penuh atau dimatikan: progres tidak tersimpan */ } },
};

if (root) {
    const MODES = ['deck', 'tour', 'blog', 'detail'];
    // Gambar tangkapan layar yang gagal dimuat (koneksi putus, cache rusak) dicoba sekali lagi dengan alamat bercap waktu.
    const retry = (img) => { if (img.dataset.retry) return; img.dataset.retry = '1'; img.src = img.src.split('?')[0] + '?r=' + Date.now(); };
    $$('img').forEach((img) => {
        img.addEventListener('error', () => retry(img));
        if (img.complete && img.naturalWidth === 0 && img.currentSrc) retry(img);
    });    const deck = makeDeck();
    const tour = makeTour();
    const blog = makeBlog();
    const fs = makeFullscreen({
        setMode: (m) => $(`[data-mode="${m}"]`).click(),
        tour,
        go: (h) => go(h),
        restore: () => setMode(root.dataset.mode, false),
    });

    function setMode(m, remember = true) {
        if (!MODES.includes(m)) m = 'deck';
        root.dataset.mode = m;
        if (remember) store.set('adminku.ug.mode', m);
        m === 'deck' && !fs.isOpen() ? deck.resume() : deck.pause();
        if (m !== 'tour') tour.close();
    }

    function route() {
        const h = decodeURIComponent(location.hash.slice(1));
        if (!h) return;
        if (h === 'gb' || h.startsWith('gb-')) { setMode('blog', false); blog.show(h); return; }
        if (h === 'gt' || h.startsWith('gt-')) { setMode('tour', false); tour.show(h.slice(3) || null); return; }
        if (h.startsWith('gd-')) { setMode('deck', false); deck.open(h.slice(3)); return; }
        const el = document.getElementById(h);
        if (el) { setMode('detail', false); el.scrollIntoView(); }
    }

    $$('[data-mode]').forEach((b) => b.addEventListener('click', () => {
        const m = b.dataset.mode;
        setHash(location.pathname + location.search + (m === 'blog' ? '#gb' : m === 'tour' ? '#gt' : ''));
        setMode(m);
        if (m === 'blog') blog.show('gb');
        if (m === 'tour') tour.show(null);
    }));

    // Pintasan lintas mode: tombol dan tautan dengan atribut data-*.
    root.addEventListener('click', (e) => {
        const t = e.target.closest('[data-tour],[data-blog],[data-open-deck],[data-detail],[data-mission]');
        if (!t) return;
        if (t.dataset.tour) { tour.autostart = true; go('#gt-' + t.dataset.tour); }
        else if (t.dataset.blog) go('#gb-' + t.dataset.blog);
        else if (t.dataset.openDeck) go('#gd-' + t.dataset.openDeck);
        else if (t.dataset.mission) { tour.autostart = false; go('#gt-' + t.dataset.mission); }
        else if (t.hasAttribute('data-detail')) {
            const id = t.getAttribute('href').slice(1);
            e.preventDefault();
            setHash('#' + id);
            setMode('detail', false);
            document.getElementById(id)?.scrollIntoView();
        }
    });
    function go(hash) { if (location.hash === hash) route(); else location.hash = hash; }

    window.addEventListener('hashchange', route);
    setMode(root.dataset.mode, false);
    route();
}

/* ---------- Presentasi bertingkat ---------- */
function makeDeck() {
    const el = $('.ug-deck');
    const sets = Object.fromEntries($$('.ug-set', el).map((s) => [s.dataset.set, { el: s, slides: $$(':scope > .ug-s', s), parent: s.dataset.parent || null, title: s.dataset.title }]));
    const dotsEl = $('.ug-dots', el), bar = $('.ug-bar i', el), playBtn = $('[data-play]', el), crumb = $('.ug-crumb', el);
    const back = {}; // setId -> indeks slide induk yang membuka set ini
    let cur = 'main', idx = 0, timer = null, walkTimer = null, userPaused = false;
    const DUR = 8000;

    const api = { open, resume() { if (!reduce && !userPaused) play(); }, pause };

    function show(setId, i, dir = 1) {
        const set = sets[setId];
        if (!set) return;
        cur = setId; idx = Math.max(0, Math.min(i, set.slides.length - 1));
        Object.entries(sets).forEach(([k, s]) => s.el.classList.toggle('is-on', k === setId));
        set.slides.forEach((s, k) => { s.classList.toggle('is-on', k === idx); s.dataset.dir = dir; });
        dotsEl.innerHTML = set.slides.map((_, k) => `<button type="button" role="tab" aria-label="Slide ${k + 1} dari ${set.slides.length}" aria-selected="${k === idx}" class="${k === idx ? 'on' : ''}" data-slide="${k}"></button>`).join('');
        const chain = []; for (let s = setId; s; s = sets[s].parent) chain.unshift(s);
        crumb.innerHTML = chain.map((s, k) => k === chain.length - 1 ? `<span aria-current="true">${esc(sets[s].title)}</span>` : `<button type="button" data-crumb="${s}">${esc(sets[s].title)}</button>`).join('<i aria-hidden="true">&rsaquo;</i>');
        crumb.hidden = chain.length < 2;
        walk(set.slides[idx]);
        if (timer) schedule();
    }
    function open(setId) {
        if (setId === 'picker') { show('main', sets.main.slides.findIndex((s) => s.hasAttribute('data-picker'))); return; }
        if (!sets[setId]) return;
        const p = sets[setId].parent;
        if (p) back[p] = cur === p ? idx : (back[p] ?? 0);
        show(setId, 0);
    }
    function next() {
        const set = sets[cur];
        if (idx < set.slides.length - 1) return show(cur, idx + 1, 1);
        if (set.parent) return show(set.parent, back[set.parent] ?? 0, 1);
        show(cur, 0, 1);
    }
    function prev() {
        const set = sets[cur];
        if (idx > 0) return show(cur, idx - 1, -1);
        if (set.parent) show(set.parent, back[set.parent] ?? 0, -1);
    }
    function schedule() {
        clearTimeout(timer);
        const s = sets[cur].slides[idx], dur = +s.dataset.dur || DUR;
        bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; bar.style.animationDuration = dur + 'ms';
        timer = setTimeout(next, dur);
    }
    function play() { el.classList.add('is-playing'); playBtn.setAttribute('aria-pressed', 'true'); playBtn.textContent = 'Jeda'; schedule(); }
    function pause() { clearTimeout(timer); timer = null; el.classList.remove('is-playing'); playBtn.setAttribute('aria-pressed', 'false'); playBtn.textContent = 'Putar otomatis'; }

    // Slide "kamera": menyusuri langkah di tangkapan layar (zoom ke tiap titik) selama slide tampil.
    function walk(slide) {
        clearInterval(walkTimer);
        if (!slide.classList.contains('ug-walk')) return;
        const steps = JSON.parse(slide.dataset.steps), cam = $('.ug-cam', slide), ring = $('.ug-ring', slide), b = $('.ug-cap b', slide), sp = $('.ug-cap span', slide);
        let i = 0;
        const go = () => {
            const s = steps[i % steps.length];
            cam.style.transformOrigin = `${s.x}% ${s.y}%`;
            cam.classList.toggle('zoomed', !reduce);
            ring.style.left = s.x + '%'; ring.style.top = s.y + '%';
            b.textContent = `${(i % steps.length) + 1}. ${s.t}`; sp.textContent = s.d;
            i++;
        };
        go();
        walkTimer = setInterval(go, 3400);
    }

    el.addEventListener('click', (e) => {
        const t = e.target.closest('[data-go],[data-slide],[data-play],[data-open],[data-crumb]');
        if (!t) return;
        if (t.dataset.go) t.dataset.go === 'next' ? next() : prev();
        else if (t.dataset.slide) show(cur, +t.dataset.slide, +t.dataset.slide >= idx ? 1 : -1);
        else if (t.hasAttribute('data-play')) { if (timer) { userPaused = true; pause(); } else { userPaused = false; play(); } }
        else if (t.dataset.open) open(t.dataset.open);
        else if (t.dataset.crumb) show(t.dataset.crumb, back[t.dataset.crumb] ?? 0, -1);
        if (t.dataset.open && location.hash.startsWith('#gd-')) setHash('#gd-' + cur);
    });
    document.addEventListener('keydown', (e) => {
        if (root.dataset.mode !== 'deck' || /INPUT|TEXTAREA|SELECT/.test(e.target.tagName)) return;
        if (e.key === 'ArrowRight' || e.key === 'PageDown') next(); else if (e.key === 'ArrowLeft' || e.key === 'PageUp') prev();
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) pause(); else if (root.dataset.mode === 'deck') api.resume(); });
    let x0 = null;
    el.addEventListener('touchstart', (e) => { x0 = e.touches[0].clientX; }, { passive: true });
    el.addEventListener('touchend', (e) => { if (x0 === null) return; const dx = e.changedTouches[0].clientX - x0; x0 = null; if (Math.abs(dx) > 70) dx < 0 ? next() : prev(); });
    if (reduce) playBtn.hidden = true;
    show('main', 0);
    return api;
}

/* ---------- Interaktif: misi dengan Driver.js ---------- */
function makeTour() {
    const el = $('.ug-tourmode');
    const KEY = 'adminku.ug.v1.' + (root.dataset.uid || '0');
    const list = $('.ug-tlist', el), stages = Object.fromEntries($$('.ug-tstage', el).map((s) => [s.dataset.stage, s]));
    let active = null, drv = null; // active = id misi yang dibuka
    const api = { show, close, autostart: false };

    const done = () => new Set(store.get(KEY, { done: [] }).done.filter((id) => stages[id]));
    const save = (set) => store.set(KEY, { done: [...set] });

    function paintScore() {
        const d = done(), n = Object.keys(stages).length;
        $('[data-score]', el).textContent = d.size;
        $('[data-scorebar]', el).style.width = (n ? (d.size / n) * 100 : 0) + '%';
        $('[data-reset]', el).hidden = d.size === 0;
        $$('[data-mission]', list).forEach((c) => {
            const ok = d.has(c.dataset.mission);
            c.classList.toggle('is-done', ok);
            $('[data-state]', c).innerHTML = ok ? '&#10003;' : '&#9654;';
        });
    }

    function show(id) {
        close();
        if (!id || !stages[id]) { active = null; list.hidden = false; Object.values(stages).forEach((s) => { s.hidden = true; }); paintScore(); return; }
        active = id; list.hidden = true;
        Object.entries(stages).forEach(([k, s]) => { s.hidden = k !== id; });
        reset(stages[id]);
        window.scrollTo({ top: el.getBoundingClientRect().top + scrollY - 70, behavior: reduce ? 'auto' : 'smooth' });
        if (api.autostart) { api.autostart = false; setTimeout(() => start(stages[id]), reduce ? 0 : 450); }
    }
    function close() { try { drv?.destroy(); } catch { /* instance sudah tertutup */ } drv = null; }

    function reset(st, phase = 'explore') {
        st.dataset.phase = phase; st.dataset.cur = 0;
        $$('.ug-hot', st).forEach((h) => { h.className = 'ug-hot ' + (phase === 'explore' ? 'explore' : ''); });
        $$('[data-li]', st).forEach((li) => li.classList.remove('is-done', 'is-cur'));
        $('[data-n]', st).textContent = 0;
        $('[data-win]', st).hidden = true;
        $('[data-start]', st).textContent = 'Mulai misi';
    }

    function popSide(h) { const l = parseFloat(h.style.left), t = parseFloat(h.style.top); return l > 68 ? 'left' : t > 68 ? 'top' : 'bottom'; }
    function base() {
        return {
            animate: !reduce, smoothScroll: !reduce, allowClose: true, overlayOpacity: 0.55, stagePadding: 10, stageRadius: 18, popoverClass: 'ug-pop',
            nextBtnText: 'Lanjut', prevBtnText: 'Kembali', doneBtnText: 'Selesai', progressText: '{{current}} dari {{total}}',
            onPopoverRender: (p) => { p.wrapper.setAttribute('aria-live', 'polite'); },
        };
    }

    // Jelajah bebas: klik titik mana saja untuk membuka popup penjelasnya.
    function explore(h) {
        close();
        drv = driver({ ...base(), showButtons: ['close'] });
        drv.highlight({ element: h, popover: { title: h.dataset.t, description: h.dataset.d, side: popSide(h), align: 'center' } });
    }

    function start(st) {
        close();
        const hots = $$('.ug-hot', st), n = hots.length;
        reset(st, 'guided');
        let cur = 0, finished = false;
        const paint = () => {
            hots.forEach((h, i) => { h.classList.toggle('cur', i === cur); h.classList.toggle('ok', i < cur); });
            $$('[data-li]', st).forEach((li, i) => { li.classList.toggle('is-done', i < cur); li.classList.toggle('is-cur', i === cur); });
            $('[data-n]', st).textContent = cur;
        };
        const steps = hots.map((h) => ({
            element: h,
            popover: { title: h.dataset.t, description: `${h.dataset.d}<br><span class="ug-pophint">Klik titik yang berdenyut untuk lanjut.</span>`, side: popSide(h), align: 'center' },
        }));
        drv = driver({ ...base(), steps, showProgress: true, showButtons: ['close'], disableActiveInteraction: false,
            onDestroyed: () => {
                drv = null;
                if (finished) return;
                reset(st); // ditutup di tengah jalan: kembali ke jelajah bebas
            },
        });
        hots.forEach((h, i) => {
            h.onclick = () => {
                if (st.dataset.phase === 'explore') return explore(h);
                if (i !== cur) return;
                cur++; paint();
                if (cur >= n) { finished = true; complete(st); try { drv.destroy(); } catch { /* sudah tertutup */ } return; }
                drv.moveNext();
            };
        });
        paint();
        $('[data-start]', st).textContent = 'Ulangi misi';
        drv.drive(0);
    }

    function complete(st) {
        const d = done(); d.add(st.dataset.stage); save(d); paintScore();
        st.dataset.phase = 'explore';
        $$('.ug-hot', st).forEach((h) => { h.classList.remove('cur'); h.classList.add('ok', 'explore'); });
        $('[data-win]', st).hidden = false;
        st.classList.remove('celebrate'); void st.offsetWidth; st.classList.add('celebrate');
        $('[data-win]', st).scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
    }

    el.addEventListener('click', (e) => {
        const t = e.target.closest('[data-back],[data-start],[data-reset],.ug-hot');
        if (!t) return;
        const st = t.closest('.ug-tstage');
        if (t.hasAttribute('data-back')) { setHash('#gt'); show(null); }
        else if (t.hasAttribute('data-start')) start(st);
        else if (t.hasAttribute('data-reset')) { save(new Set()); paintScore(); }
        else if (t.classList.contains('ug-hot') && !t.onclick) explore(t);
    });
    // Hotspot di luar misi (belum ada handler): jelajah bebas.
    $$('.ug-hot', el).forEach((h) => { h.onclick = () => explore(h); });
    paintScore();
    return api;
}

/* ---------- Blog ---------- */
function makeBlog() {
    const el = $('.ug-blog'), home = $('[data-bhome]', el), arts = Object.fromEntries($$('.ug-art', el).map((a) => [a.dataset.art, a]));
    const input = $('[data-bsearch]', el), empty = $('[data-bempty]', el), chips = $$('[data-bgroup]', el);
    let group = '';

    function filter() {
        const q = input.value.trim().toLowerCase();
        let n = 0;
        $$('.ug-card', el).forEach((c) => {
            const ok = (!group || c.dataset.bgroupCard === group) && (!q || c.dataset.btext.includes(q));
            c.hidden = !ok; if (ok) n++;
        });
        $$('.ug-bhub', el).forEach((c) => { c.hidden = !!q || (!!group && c.dataset.bgroupCard !== group); });
        empty.hidden = n > 0;
        chips.forEach((c) => { c.classList.toggle('is-on', c.dataset.bgroup === group); });
    }
    function show(h) {
        const [id, sub] = h.slice(3).split('--');
        const art = arts[id];
        Object.values(arts).forEach((a) => { a.hidden = a !== art; });
        home.hidden = !!art;
        if (!art) { filter(); el.scrollIntoView({ block: 'start' }); return; }
        (sub ? document.getElementById(`gb-${id}--${sub}`) : el)?.scrollIntoView({ block: 'start', behavior: reduce || !sub ? 'auto' : 'smooth' });
    }
    input.addEventListener('input', filter);
    el.addEventListener('click', (e) => {
        const c = e.target.closest('[data-bgroup]'), g = e.target.closest('[data-bgroup-go]');
        if (c) { group = c.dataset.bgroup; filter(); }
        if (g) { group = g.dataset.bgroupGo; input.value = ''; filter(); }
    });
    Object.values(arts).forEach((a) => { a.hidden = true; });
    filter();
    return { show };
}

/* ---------- Bantuan layar penuh ---------- */
function makeFullscreen(ctx) {
    const el = $('#ugf');
    const scroller = $('.ugf-scroll', el), secs = $$('.ugf-s', scroller), dotsEl = $('.ugf-dots', el), playBtn = $('[data-ugf-play]', el);
    const html = document.documentElement, isOpen = () => html.classList.contains('ugf-on');
    const phone = () => matchMedia('(pointer: coarse) and (max-width: 900px), (pointer: coarse) and (max-height: 500px)').matches;
    let cur = 0, timer = null;
    const behavior = () => (reduce ? 'auto' : 'smooth');

    dotsEl.innerHTML = secs.map((s, i) => `<button type="button" aria-label="Bagian ${i + 1}: ${esc(s.getAttribute('aria-label') || '')}" data-i="${i}"></button>`).join('');
    const dots = $$('button', dotsEl);
    // Tinggi lokal (setelah diputar di HP tegak) menentukan mode ringkas, bukan tinggi layar.
    new ResizeObserver(() => el.toggleAttribute('data-compact', el.clientHeight <= 460)).observe(el);
    const io =new IntersectionObserver((entries) => entries.forEach((e) => {
        if (!e.isIntersecting) return;
        e.target.classList.add('is-in');
        cur = secs.indexOf(e.target);
        dots.forEach((d, i) => d.classList.toggle('on', i === cur));
    }), { root: scroller, threshold: 0.55 });
    secs.forEach((s) => io.observe(s));
    if (reduce) secs.forEach((s) => s.classList.add('is-in'));

    function goTo(i) { i = Math.max(0, Math.min(secs.length - 1, i)); secs[i].scrollIntoView({ behavior: behavior(), block: 'start', inline: 'start' }); }
    function stopPlay() { clearInterval(timer); timer = null; playBtn.setAttribute('aria-pressed', 'false'); $('span', playBtn).textContent = 'Putar otomatis'; }
    function startPlay() { playBtn.setAttribute('aria-pressed', 'true'); $('span', playBtn).textContent = 'Jeda'; timer = setInterval(() => goTo(cur >= secs.length - 1 ? 0 : cur + 1), 7500); }

    // Layar penuh + kunci landscape (Android Chrome; iOS tidak mendukung kunci, di sana CSS memutar tampilan).
    async function enterFull() {
        try { await html.requestFullscreen?.({ navigationUI: 'hide' }); } catch { /* iframe tanpa izin layar penuh: tetap jalan tanpa */ }
        if (phone()) { try { await screen.orientation?.lock?.('landscape'); } catch { /* tidak didukung */ } }
    }
    function leaveFull() {
        try { screen.orientation?.unlock?.(); } catch { /* abaikan */ }
        if (document.fullscreenElement) document.exitFullscreen?.().catch(() => {});
    }
    function open() { html.classList.add('ugf-on'); scroller.scrollTop = 0; scroller.scrollLeft = 0; ctx.restore(); scroller.focus({ preventScroll: true }); }
    function close() { stopPlay(); html.classList.remove('ugf-on'); leaveFull(); ctx.restore(); }

    el.addEventListener('click', (e) => {
        const t = e.target.closest('[data-ugf-close],[data-ugf-full],[data-ugf-play],[data-ugf-next],[data-ugf-go],[data-ugf-try],[data-ugf-mode],[data-i]');
        if (!t) return;
        if (t.hasAttribute('data-ugf-close')) close();
        else if (t.hasAttribute('data-ugf-full')) (document.fullscreenElement ? leaveFull() : enterFull());
        else if (t.hasAttribute('data-ugf-play')) (timer ? stopPlay() : startPlay());
        else if (t.hasAttribute('data-ugf-next')) { if (phone()) enterFull(); goTo(cur + 1); }
        else if (t.dataset.i) goTo(+t.dataset.i);
        else if (t.dataset.ugfGo) { e.preventDefault(); close(); ctx.go(t.dataset.ugfGo); }
        else if (t.dataset.ugfTry) { close(); ctx.tour.autostart = true; ctx.go('#gt-' + t.dataset.ugfTry); }
        else if (t.dataset.ugfMode) { close(); ctx.setMode(t.dataset.ugfMode); }
    });
    root.addEventListener('click', (e) => { if (e.target.closest('[data-ugf-open]')) open(); });
    scroller.addEventListener('wheel', stopPlay, { passive: true });
    scroller.addEventListener('touchstart', stopPlay, { passive: true });
    document.addEventListener('keydown', (e) => {
        if (!isOpen()) return;
        const k = e.key;
        if (k === 'Escape' && !document.fullscreenElement) close();
        else if (k === 'ArrowDown' || k === 'PageDown' || (k === ' ' && e.target === scroller)) { e.preventDefault(); stopPlay(); goTo(cur + 1); }
        else if (k === 'ArrowUp' || k === 'PageUp') { e.preventDefault(); stopPlay(); goTo(cur - 1); }
        else if (k === 'Home') { e.preventDefault(); goTo(0); }
        else if (k === 'End') { e.preventDefault(); goTo(secs.length - 1); }
    });
    document.addEventListener('visibilitychange', () => { if (document.hidden) stopPlay(); });
    window.addEventListener('hashchange', () => { if (location.hash && isOpen()) close(); });
    if (isOpen()) scroller.focus({ preventScroll: true });
    return { isOpen, open, close };
}

// Mengganti hash tanpa menambah riwayat; gagal diam-diam bila browser menolak (mis. halaman dibuka dari file:).
function setHash(u) { try { history.replaceState(null, '', u); } catch { /* abaikan */ } }

function esc(s) { return String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c])); }
