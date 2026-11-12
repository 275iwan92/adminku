{{-- REMARK
 Tujuan       : Kotak SARAN Titik Pesan Ulang dan Jml Pesan Ulang di bawah isian pesan ulang form Barang: rumus dengan angkanya, dua angka yang boleh dicoba (hari pengaman, hari cakupan; dihitung ulang di layar, tidak disimpan) dan tombol Pakai saran yang hanya mengisi kolom (tersimpan setelah Simpan).
 Sumber hulu  : ProductController@show (leadTime, dailyUsage) lewat products/form.blade.php; App\Services\ReorderAdvice.
 Input        : $advice (ReorderAdvice::advise atau null), $unit, $canApply (Supervisor).
 Dipakai oleh : products/form.blade.php.
 Status       : Aktif.
--}}
@use('Devlib2\Locale')
@if ($advice)
    <div class="sstock-advice" data-advice data-usage="{{ $advice['usage'] }}" data-lead="{{ $advice['lead'] }}">
        <b>Saran pesan ulang</b> <span class="sstock-advice__basis">pemakaian rata-rata {{ Locale::decimal($advice['usage'], 2) }} {{ $unit }}/hari (90 hari terakhir) · lead time {{ $advice['lead'] }} hari</span>
        <div class="sstock-advice__calc">
            <label>Hari pengaman <input type="number" min="0" max="365" value="{{ $advice['safety'] }}" data-advice-safety></label>
            <label>Hari cakupan pesanan <input type="number" min="1" max="365" value="{{ $advice['cover'] }}" data-advice-cover></label>
        </div>
        <p>Titik Pesan Ulang: <strong><span data-advice-rop>{{ $advice['rop'] }}</span> {{ $unit }}</strong> <small>= pemakaian/hari × (lead time {{ $advice['lead'] }} + <span data-advice-safety-txt>{{ $advice['safety'] }}</span> hari pengaman), dibulatkan ke atas</small></p>
        <p>Jml Pesan Ulang: <strong><span data-advice-qty>{{ $advice['qty'] }}</span> {{ $unit }}</strong> <small>= pemakaian/hari × <span data-advice-cover-txt>{{ $advice['cover'] }}</span> hari cakupan, dibulatkan ke atas</small></p>
        @if ($canApply)
            <button type="button" class="dl2-btn dl2-btn-sm" data-advice-apply>Pakai saran</button>
        @endif
        <small class="sstock-advice__note">Hanya saran: mengisi kolom di atas, belum tersimpan sampai Simpan. Titik Pesan Ulang dan Jml Pesan Ulang tidak berubah sendiri.</small>
    </div>
    <style>
        .sstock-advice { margin: 8px 0; padding: 10px 14px; border: 1px solid var(--dl2-border); border-left: 3px solid var(--dl2-primary, #2563eb); border-radius: 8px; background: var(--dl2-surface); font-size: 13px; }
        .sstock-advice p { margin: 4px 0; }
        .sstock-advice small { color: var(--dl2-ink-soft); }
        .sstock-advice__basis { color: var(--dl2-ink-soft); }
        .sstock-advice__calc { display: flex; gap: 16px; flex-wrap: wrap; margin: 6px 0; }
        .sstock-advice__calc input { width: 64px; padding: 2px 6px; border: 1px solid var(--dl2-border); border-radius: 6px; background: var(--dl2-surface); color: inherit; }
        .sstock-advice__note { display: block; margin-top: 6px; }
        /* Tampilan HP: teks 14px, isian angka 16px / 44px selebar kotak, tombol penuh selebar kotak. */
        body.dl-on-phone .sstock-advice { font-size: 14px; }
        body.dl-on-phone .sstock-advice__calc { flex-direction: column; gap: 10px; }
        body.dl-on-phone .sstock-advice__calc label { display: flex; flex-direction: column; gap: 4px; font-weight: 600; }
        body.dl-on-phone .sstock-advice__calc input { width: 100%; min-height: 44px; padding: 6px 10px; font-size: 16px; box-sizing: border-box; }
        body.dl-on-phone .sstock-advice [data-advice-apply] { width: 100%; min-height: 48px; font-size: 16px; }
    </style>
    <script>
        window.akReady(() => {
            const box = document.querySelector('[data-advice]'); if (!box) return;
            const q = s => box.querySelector(s), usage = +box.dataset.usage, lead = +box.dataset.lead;
            const up = x => Math.max(1, Math.ceil(x - 1e-9));
            const int = el => Math.max(0, Math.round(+el.value) || 0);
            const calc = () => {
                const s = int(q('[data-advice-safety]')), c = Math.max(1, int(q('[data-advice-cover]')));
                const rop = up(usage * (lead + s)), qty = up(usage * c);
                q('[data-advice-rop]').textContent = rop; q('[data-advice-qty]').textContent = qty;
                q('[data-advice-safety-txt]').textContent = s; q('[data-advice-cover-txt]').textContent = c;
                return {rop, qty};
            };
            box.addEventListener('input', calc);
            const fill = (name, n) => {
                const f = document.querySelector(`[name="${name}"]`); if (!f || f.readOnly || f.disabled) return;
                f.value = new Intl.NumberFormat(document.documentElement.lang === 'en' ? 'en-US' : 'id-ID', {minimumFractionDigits: 2, maximumFractionDigits: 2}).format(n);
                ['input', 'change', 'blur'].forEach(t => f.dispatchEvent(new Event(t, {bubbles: true})));
            };
            const apply = q('[data-advice-apply]');
            if (apply) apply.addEventListener('click', () => { const r = calc(); fill('reorder_point', r.rop); fill('reorder_qty', r.qty); });
        });
    </script>
@else
    <p class="ak-note">Saran pesan ulang (Titik Pesan Ulang dan Jml Pesan Ulang) muncul setelah barang punya lead time dan ada pengeluaran dalam 90 hari terakhir.</p>
@endif
