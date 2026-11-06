/* Audit kontras tema (WCAG AA). Tempel di konsol browser pada halaman AdminKU (sudah login), atau muat lewat Playwright/Chrome MCP.
 * auditContrast(doc?) -> daftar elemen berteks dengan rasio < 4.5 (teks besar < 3). themesAudit(['pastel_blue',...]) memutar tema lewat
 * localStorage 'dl-theme' + atribut html (shell dan semua iframe) lalu mengembalikan {tema: [pelanggaran]}. Elemen berlatar gambar/gradien dilewati. */
(function (w) {
  const cv = document.createElement('canvas').getContext('2d', {willReadFrequently: true});
  const rgba = c => { cv.clearRect(0, 0, 1, 1); cv.fillStyle = '#000'; cv.fillStyle = c; cv.fillRect(0, 0, 1, 1); const d = cv.getImageData(0, 0, 1, 1).data; return [d[0], d[1], d[2], d[3] / 255]; };
  const lum = ([r, g, b]) => { const f = v => (v /= 255) <= .03928 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; return .2126 * f(r) + .7152 * f(g) + .0722 * f(b); };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + .05) / (Math.min(x, y) + .05); };
  const over = (f, b) => [0, 1, 2].map(i => f[i] * f[3] + b[i] * (1 - f[3])).concat(1);
  function bgOf(el, win) {
    const chain = []; for (let e = el; e; e = e.parentElement) chain.push(e);
    let base = [255, 255, 255, 1];
    for (const e of chain.reverse()) {
      const s = win.getComputedStyle(e);
      if (s.backgroundImage !== 'none') return null;
      const c = rgba(s.backgroundColor); if (c[3] > 0) base = c[3] === 1 ? c : over(c, base);
    }
    return base;
  }
  w.auditContrast = function (doc = document) {
    const win = doc.defaultView, out = [];
    if (!doc.getElementById('tc-off')) { const st = doc.createElement('style'); st.id = 'tc-off'; st.textContent = '*{transition:none!important;animation:none!important}'; doc.head.appendChild(st); } // transisi menunda warna akhir
    doc.querySelectorAll('body *').forEach(el => {
      if (!/^(SCRIPT|STYLE|SVG|PATH|I)$/i.test(el.tagName) === false) return;
      const own = [...el.childNodes].some(n => n.nodeType === 3 && n.textContent.trim());
      if (!own) return;
      const s = win.getComputedStyle(el);
      if (s.visibility === 'hidden' || s.display === 'none' || +s.opacity === 0 || !el.getClientRects().length) return;
      let hid = false; for (let e = el; e; e = e.parentElement) { const t = win.getComputedStyle(e); if (+t.opacity === 0 || t.visibility === 'hidden') { hid = true; break; } }
      if (hid) return;
      const rc = el.getClientRects()[0], cx = rc.left + rc.width / 2, cy = rc.top + rc.height / 2;
      if (cx >= 0 && cy >= 0 && cx < win.innerWidth && cy < win.innerHeight) { const top = doc.elementFromPoint(cx, cy); if (top && !el.contains(top) && !top.contains(el)) return; } // tertutup overlay
      const bg = bgOf(el, win); if (!bg) return;
      const fg = rgba(s.color), eff = fg[3] < 1 ? over(fg, bg) : fg;
      const px = parseFloat(s.fontSize), big = px >= 24 || (px >= 18.66 && +s.fontWeight >= 700), need = big ? 3 : 4.5;
      const r = ratio(eff, bg);
      if (r < need) out.push({r: +r.toFixed(2), need, text: el.textContent.trim().slice(0, 30), cls: el.className?.toString().slice(0, 40), tag: el.tagName});
    });
    return out;
  };
  w.setTheme = function (code, doc = document) { doc.documentElement.dataset.dlTheme = code; };
  w.themesAudit = async function (codes, wait = 150) {
    const res = {};
    for (const c of codes) {
      localStorage.setItem('dl-theme', c);
      [document, ...[...document.querySelectorAll('iframe')].map(f => { try { return f.contentDocument; } catch (e) { return null; } })].filter(Boolean).forEach(d => setTheme(c, d));
      await new Promise(r => setTimeout(r, wait));
      res[c] = [document, ...[...document.querySelectorAll('iframe')].map(f => { try { return f.contentDocument; } catch (e) { return null; } })].filter(Boolean).flatMap(d => auditContrast(d));
    }
    return res;
  };
  // Muat tiap URL dalam iframe tersembunyi lalu audit di semua tema: screensAudit(['/stok','/help'], ['navy','soft_pink']) -> {url: {tema: jumlah pelanggaran}}
  w.screensAudit = async function (urls, codes) {
    const out = {};
    for (const u of urls) {
      const f = document.createElement('iframe'); f.style.cssText = 'position:fixed;left:0;top:0;width:1280px;height:800px;opacity:0;pointer-events:none';
      f.src = u; document.body.appendChild(f);
      await new Promise(r => { f.onload = r; setTimeout(r, 8000); }); await new Promise(r => setTimeout(r, 800));
      out[u] = {};
      for (const c of codes) { setTheme(c, f.contentDocument); out[u][c] = auditContrast(f.contentDocument).map(x => x.r + ' ' + (x.cls || x.tag).split(' ')[0] + ' "' + x.text + '"'); }
      f.remove();
    }
    return out;
  };
})(window);
