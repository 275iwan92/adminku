// Layar tinjau Stok Opname: ringkasan (jumlah / nilai lebih-kurang) dihitung server, jadi halaman dimuat ulang setelah sel tersimpan.
document.addEventListener('saved.dl2.row-sheet', (e) => {
    if (e.target.closest('[data-reload-on-save]')) setTimeout(() => location.reload(), 600);
});

// Simpan Cadangan di lembar input Stok Opname: isian sudah tersimpan per sel; tombol ini memastikannya. Layar mengirim jumlah baris terisi yang dilihatnya, server membandingkan dengan databasenya
// (dicoba beberapa kali supaya simpan sel yang masih berjalan sempat selesai).
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-count-save]');
    if (!btn) return;
    const host = document.querySelector('[data-dl2="row-sheet"]');
    const sheet = host && window.Devlib2?.classes?.['row-sheet']?.getInstance(host);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const toast = window.Devlib2?.toast;
    if (!sheet || !toast) return;
    btn.disabled = true;
    let res = null;
    for (let i = 0; i < 3; i++) {
        await new Promise((r) => setTimeout(r, 700));
        const seen = sheet.getRows().filter((r) => (r.counted_qty !== '' && r.counted_qty != null) || r.skipped).length;
        try {
            const r = await fetch(btn.dataset.countSave, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ seen }) });
            res = { ok: r.ok, ...(await r.json().catch(() => ({}))) };
        } catch {
            res = { ok: false, error: 'Tidak bisa menghubungi server. Periksa koneksi, lalu coba lagi.' };
        }
        if (res.ok) break;
    }
    toast(res.ok ? res.message : (res.error || 'Gagal menyimpan.'), { tone: res.ok ? 'success' : 'danger' });
    btn.disabled = false;
});

// Hitung Opname tampilan HP (aktif hanya di halaman yang punya [data-hp-count]).
import './hp-count';

// Cari Barang tampilan HP: isi kolom terpilih saat fokus supaya pindai berikutnya menimpa.
import './hp-find';

// Form Uang Masuk / Uang Keluar: saklar kas, saldo, ringkasan, dan peringatan batas (resources/js/cash-form.js).
import './cash-form';

// /help yang dibuka langsung (tanpa shell) dilempar ke Beranda dengan ?bantuan=<anchor|1> (skrip di help/user-guide); di sini Shell diminta membuka tab Bantuan, jadi panduan selalu tampil di dalam shell (sidebar + tab).
{
    const p = new URLSearchParams(location.search), want = p.get('bantuan');
    if (want) {
        p.delete('bantuan');
        history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : '') + location.hash);
        const fire = () => window.postMessage({ dl: 'help', hash: want === '1' ? undefined : want }, location.origin);
        document.readyState === 'complete' ? setTimeout(fire, 300) : window.addEventListener('load', () => setTimeout(fire, 300));
    }
}
// Isi bidang formulir lewat skrip (padanan Devlib.h.setField v1): ubah nilai lalu beri tahu DevLib 2 / penyimak change.
window.akSetField = (form, name, value) => {
    const el = form?.querySelector(`[name="${name}"]`);
    if (!el) return;
    const host = el.closest('[data-dl2]'), inst = host && window.Devlib2?.classes?.[host.dataset.dl2]?.getInstance(host);
    if (inst?.setValue) { inst.setValue(value, true); return; }
    el.value = value;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
};

// Angka dari isian (format Indonesia "1.234,50" atau biasa "1234.5") ke Number; kosong = 0.
window.akNum = (v) => {
    const s = String(v ?? '').replace(/[^0-9,.\-]/g, '');
    const n = s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s;
    return parseFloat(n) || 0;
};
