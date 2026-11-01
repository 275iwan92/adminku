// Pembaca angka Indonesia untuk isian jumlah di HP: SAMA PERSIS dengan Devlib.h.numStr (DevLib core.js) dan Devlib\Locale::parse (PHP).
// Titik dan koma sekaligus -> yang terakhir desimal; satu koma = desimal; titik berpola ribuan ^-?[1-9]\d{0,2}(\.\d{3})+$ = ribuan, titik lain = desimal ("0.500" = 0,5; "1.250" = 1250);
// "12," saat mengetik = 12; Rp / IDR / % / spasi diabaikan. Dipakai bila DevLib belum dimuat; kalau ada, Devlib.h.numStr yang menang supaya HP dan desktop tidak pernah beda tafsir.
export const localNumStr = (v) => {
    if (typeof v === 'number') return Number.isFinite(v) ? String(v) : '';
    let s = String(v ?? '').replace(/\s|Rp|IDR|USD|EUR|[$€¥%]/gi, '');
    const dots = (s.match(/\./g) || []).length, commas = (s.match(/,/g) || []).length;
    if (dots && commas) {
        const dec = s.lastIndexOf('.') > s.lastIndexOf(',') ? '.' : ',';
        s = s.split(dec === '.' ? ',' : '.').join('').replace(dec, '.');
    } else if (dots || commas) {
        const ch = dots ? '.' : ',', n = dots || commas;
        if (ch === ',') { if (n > 1) return ''; s = s.replace(ch, '.'); }
        else if (new RegExp('^-?[1-9]\\d{0,2}(\\' + ch + '\\d{3})+$').test(s)) s = s.split(ch).join('');
        else if (n === 1) s = s.replace(ch, '.');
        else return '';
    }
    if (!/^-?\d*\.?\d*$/.test(s) || !/\d/.test(s)) return '';
    const neg = s.startsWith('-');
    let [i, f = ''] = s.replace('-', '').split('.');
    i = i.replace(/^0+(?=\d)/, '') || '0';
    const out = i + (f ? '.' + f : '');
    return (neg && +out ? '-' : '') + out;
};

export const numStr = (v) => (globalThis.Devlib?.h?.numStr || localNumStr)(v);

// Kanonik "1234.5" untuk dikirim ke server; '' = kosong (belum dihitung) atau bukan angka (lihat validQty).
export const canon = (s) => (String(s ?? '').trim() === '' ? '' : numStr(s));

// Kosong = belum dihitung (sah). Selain itu harus angka 0 atau lebih dengan paling banyak 3 desimal (sama dengan aturan server).
export const validQty = (s) => {
    if (String(s ?? '').trim() === '') return true;
    const c = numStr(s);
    return c !== '' && !c.startsWith('-') && (c.split('.')[1] || '').length <= 3;
};
