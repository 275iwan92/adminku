// Layar tinjau Stok Opname: ringkasan (jumlah / nilai lebih-kurang) dihitung server, jadi halaman dimuat ulang setelah sel tersimpan.
document.addEventListener('dl:sheet-save', (e) => {
    if (e.target.closest('[data-reload-on-save]')) setTimeout(() => location.reload(), 600);
});

// Simpan Cadangan di lembar input Stok Opname: isian sudah tersimpan per sel; tombol ini memastikannya. Layar mengirim jumlah baris terisi yang dilihatnya, server membandingkan dengan databasenya
// (dicoba beberapa kali supaya simpan sel yang masih berjalan sempat selesai).
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-count-save]');
    if (!btn) return;
    const sheet = document.querySelector('[data-dl-sheet]')?.dlSheet;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const toast = window.Devlib?.toast;
    if (!sheet || !toast) return;
    btn.disabled = true;
    let res = null;
    for (let i = 0; i < 3; i++) {
        await new Promise((r) => setTimeout(r, 700));
        const seen = sheet.data.filter((r) => (r.counted_qty !== '' && r.counted_qty != null) || r.skipped).length;
        try {
            const r = await fetch(btn.dataset.countSave, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ seen }) });
            res = { ok: r.ok, ...(await r.json().catch(() => ({}))) };
        } catch {
            res = { ok: false, error: 'Tidak bisa menghubungi server. Periksa koneksi, lalu coba lagi.' };
        }
        if (res.ok) break;
    }
    toast(res.ok ? res.message : (res.error || 'Gagal menyimpan.'), res.ok ? 'success' : 'danger');
    btn.disabled = false;
});

// Hitung Opname tampilan HP (aktif hanya di halaman yang punya [data-hp-count]).
import './hp-count';

// Cari Barang tampilan HP: isi kolom terpilih saat fokus supaya pindai berikutnya menimpa.
import './hp-find';

// Uang Masuk: dropdown Rekening Bank hanya tampil bila saklar Kas Tujuan = Rekening bank (Tunai memakai kas tunai otomatis).
const cashKindSync = () => {
    const k = document.querySelector('[name="cash_kind"]');
    if (!k) return;
    document.querySelectorAll('.sstock-cashbank').forEach((el) => {
        const box = el.parentElement.children.length === 1 ? el.parentElement : el;
        box.style.display = k.value === 'bank' ? '' : 'none';
    });
};
document.addEventListener('change', (e) => { if (e.target.matches?.('[name="cash_kind"]')) cashKindSync(); });
document.addEventListener('DOMContentLoaded', cashKindSync);

// Uang Keluar: peringatan langsung bila Jumlah melebihi sisa Uang Masuk yang dipilih (server tetap menolak saat simpan).
const cashOverSync = () => {
    const f = document.querySelector('form[data-cash-remaining]');
    const amountEl = f?.querySelector('[name="amount"]');
    if (!amountEl) return;
    const remaining = JSON.parse(f.dataset.cashRemaining || '{}');
    const id = f.querySelector('[name="cash_in_id"]')?.value;
    const amount = window.Devlib?.h?.parseNum ? window.Devlib.h.parseNum(amountEl.value) : parseFloat(amountEl.value);
    const field = amountEl.closest('.dl-field');
    let note = f.querySelector('[data-cash-overwarn]');
    const over = id && remaining[id] !== undefined && Number(amount) > Number(remaining[id]);
    field?.classList.toggle('dl-field--tone-warn', !!over);
    if (!over) return note?.remove();
    if (!note) { note = Object.assign(document.createElement('div'), { className: 'sstock-overwarn' }); note.dataset.cashOverwarn = ''; field.after(note); }
    note.textContent = `Jumlah melebihi sisa Uang Masuk ini (sisa Rp ${Math.round(remaining[id]).toLocaleString('id-ID')}). Kurangi jumlahnya atau pilih Uang Masuk lain.`;
};
document.addEventListener('input', (e) => { if (e.target.closest?.('form[data-cash-remaining]')) cashOverSync(); });
document.addEventListener('change', (e) => { if (e.target.closest?.('form[data-cash-remaining]')) cashOverSync(); });
document.addEventListener('DOMContentLoaded', cashOverSync);
