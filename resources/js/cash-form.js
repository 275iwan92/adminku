/* Form Kas (Uang Masuk dan Uang Keluar): perilaku sisi klien. Aktif hanya di halaman yang punya [name=cash_kind] atau form[data-cash-remaining]. */
// Uang Masuk: dropdown Rekening Bank hanya tampil bila saklar Kas Tujuan = Rekening bank (Tunai memakai kas tunai otomatis).
const cashKind = (root = document) => root.querySelector('[name="cash_kind"]:checked')?.value ?? root.querySelector('[name="cash_kind"]')?.value;
const cashKindSync = () => {
    const k = document.querySelector('[name="cash_kind"]');
    if (!k) return;
    document.querySelectorAll('.sstock-cashbank').forEach((el) => {
        const box = el.parentElement.children.length === 1 ? el.parentElement : el;
        box.style.display = cashKind() === 'bank' ? '' : 'none';
    });
};
document.addEventListener('change', (e) => { if (e.target.matches?.('[name="cash_kind"]')) cashKindSync(); });
window.akReady?.(cashKindSync);

// Uang Keluar: Kas Asal (Tunai / Rekening bank), tautan Uang Masuk opsional yang MENGUNCI kas, saldo kas, ringkasan, dan peringatan langsung bila Jumlah melebihi batas
// (batas = sisa Uang Masuk tertaut dan/atau saldo kas; server tetap menolak saat simpan dan mengecek ulang saat Approve).
const cashOverSync = () => {
    const f = document.querySelector('form[data-cash-remaining]');
    const amountEl = f?.querySelector('[name="amount"]');
    if (!amountEl) return;
    const J = (k) => JSON.parse(f.dataset[k] || '{}');
    const remaining = J('cashRemaining'), balances = J('cashBalances'), links = J('cashLinks'), banks = J('cashBanks'), names = J('cashAccounts');
    const cashId = f.dataset.cashMain || '';
    const rp = (n) => `${n < 0 ? '- ' : ''}Rp ${Math.abs(Math.round(n)).toLocaleString('id-ID')}`;
    const kindEl = f.querySelector('[name="cash_kind"]');
    const bankEl = f.querySelector('[name="cash_account_id"]');
    const link = f.querySelector('[name="cash_in_id"]')?.value;
    const linked = link && links[link] !== undefined;

    // Tautan mengunci saklar dan rekening mengikuti kas Uang Masuk itu.
    if (linked && kindEl && bankEl) {
        const acct = String(links[link]);
        const kind = acct === cashId ? 'cash' : 'bank';
        if (cashKind(f) !== kind) window.akSetField(f, 'cash_kind', kind);
        if (kind === 'bank' && bankEl.value !== acct) window.akSetField(f, 'cash_account_id', acct);
    }
    [kindEl?.closest('.dl2-field'), bankEl?.closest('.sstock-cashbank')].forEach((el) => {
        if (!el) return;
        el.classList.toggle('is-locked', !!linked);
        el.querySelectorAll('input[type=radio]').forEach((b) => { b.disabled = !!linked; });
    });

    const acct = linked ? String(links[link]) : (cashKind(f) === 'cash' ? cashId : (bankEl?.value || ''));
    const balance = acct && balances[acct] !== undefined ? Number(balances[acct]) : null;
    const amount = Number(window.akNum(amountEl.value) || 0);
    const field = amountEl.closest('.dl2-field');
    f.querySelectorAll('[data-cash-sum], [data-cash-overwarn], [data-cash-saldo]').forEach((el) => el.remove());

    // Saldo kas ini, tepat di bawah pilihan kas.
    const anchor = (cashKind(f) === 'bank' || linked) && bankEl ? bankEl.closest('.sstock-cashbank') : kindEl?.closest('.dl2-field');
    if (anchor) {
        const saldo = Object.assign(document.createElement('p'), { className: 'sstock-cashsaldo' });
        saldo.dataset.cashSaldo = '';
        saldo.innerHTML = balance === null ? 'Pilih rekening untuk melihat saldo.' : `Saldo kas ini: <b>${rp(balance)}</b>`;
        anchor.after(saldo);
    }

    const limits = [];
    if (linked && remaining[link] !== undefined) limits.push(['sisa Uang Masuk tertaut', Number(remaining[link])]);
    if (balance !== null) limits.push(['saldo kas', balance]);
    const over = limits.find(([, v]) => amount > v);
    field?.classList.toggle('is-warn', !!over);
    if (!limits.length) return;
    const rows = [];
    if (linked) rows.push(['Sisa saldo Uang Masuk', rp(Number(remaining[link])), null], ['Sisa setelah ini', rp(Number(remaining[link]) - amount), Number(remaining[link]) - amount < 0]);
    if (balance !== null) rows.push(['Saldo kas setelah ini', rp(balance - amount), balance - amount < 0]);
    const sum = Object.assign(document.createElement('div'), { className: 'sstock-cashsum' });
    sum.dataset.cashSum = '';
    sum.innerHTML = rows.map(([l, v, neg]) => `<div><span>${l}</span><b class="${neg ? 'is-neg' : ''}">${v}</b></div>`).join('');
    field.after(sum);
    if (over) {
        const note = Object.assign(document.createElement('div'), { className: 'sstock-overwarn', textContent: `Jumlah melebihi ${over[0]} (${rp(over[1])}). Kurangi jumlahnya, atau pilih kas / Uang Masuk lain.` });
        note.dataset.cashOverwarn = '';
        sum.after(note);
    }
};
document.addEventListener('input', (e) => { if (e.target.closest?.('form[data-cash-remaining]')) cashOverSync(); });
document.addEventListener('change', (e) => { if (e.target.closest?.('form[data-cash-remaining]')) cashOverSync(); });
window.akReady?.(cashOverSync);
