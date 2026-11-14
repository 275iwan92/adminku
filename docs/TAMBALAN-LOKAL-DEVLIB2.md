# Tambalan lokal adminku atas DevLib 2 (didaftarkan 2026-11-14; belum ada yang dihapus)

Tambalan di adminku yang kini ditutup oleh celah paket 9b di devlib2 (`docs/PARITY-9b.md`). Dihapus hanya atas persetujuan Jo, lalu uji ulang.

| Tambalan | Berkas | Ditutup oleh |
|---|---|---|
| Ikon FA "telanjang" (`clipboard-check` -> `<i class="fa-solid ...">`) di `.dl2-pic` | `resources/js/app.js` (blok `fix`, cabang `bare`) | 9b #10: ikon FA di `.dl2-pic` / `.dl2-lm-ic` dilukis otomatis |
| Daftar foto/kutipan Frame lewat PHP | `app/Support/Wallpapers.php` (`list`, `quotes`) | tetap diperlukan (sumber data, bukan tambalan) |
| Memo CountSchedule per permintaan | `app/Services/CountSchedule.php` | di luar devlib2 (logika adminku) |
| Selektor merek `.dl2-pshell-top b` | `resources/css/app.css` | penanda DOM v2 phone-shell; tetap |
