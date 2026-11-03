{{-- REMARK
 Tujuan       : Menjaga token formulir halaman masuk tetap segar: halaman yang dibiarkan terbuka (tab lama, laptop tidur) mengambil token baru tiap 5 menit dan saat tab kembali aktif, sehingga masuk tidak pernah berujung 419. Tanpa token baru bila gagal jaringan (diam).
 Sumber hulu  : route login.token (GET, token saja).
 Output       : memperbarui semua input _token dan meta csrf-token di halaman.
 Dipakai oleh : login.blade.php, login-hp.blade.php.
 Status       : Aktif.
--}}
<script>
    (() => {
        const url = @json(route('login.token'), JSON_UNESCAPED_SLASHES);
        const refresh = () => fetch(url, {headers: {Accept: 'application/json'}, credentials: 'same-origin', cache: 'no-store'})
            .then(r => r.ok ? r.json() : null)
            .then(d => { if (d && d.token) { document.querySelectorAll('input[name="_token"]').forEach(i => i.value = d.token); document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', d.token); } })
            .catch(() => {});
        setInterval(refresh, 300000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        window.addEventListener('pageshow', e => { if (e.persisted) refresh(); });
        window.addEventListener('online', refresh);
    })();
</script>
