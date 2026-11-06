// Layar sopir (HP saja): pindai kode dengan kamera + kirim posisi GPS selama trip berjalan. Kamera dan GPS butuh HTTPS (secure context) dan layar sentuh; selain itu bagian ini tetap tersembunyi.
const root = document.querySelector('[data-driver]');
const phone = matchMedia('(pointer: coarse)').matches && window.isSecureContext;
if (root && phone) {
    document.querySelectorAll('[data-hp-only]').forEach((el) => (el.hidden = false));
    const csrf = document.querySelector('meta[name=csrf-token]')?.content;
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify(body) });

    // GPS: hanya setelah sopir menekan "Bagikan lokasi"; kirim paling cepat tiap 20 detik; berhenti saat server menjawab 409 (trip selesai) atau sopir mematikan.
    const gps = root.querySelector('[data-gps]');
    if (gps && navigator.geolocation) {
        const KEY = 'adminku-gps-' + gps.dataset.url;
        const state = gps.querySelector('[data-gps-state]'), btn = gps.querySelector('[data-gps-toggle]');
        let watch = null, last = 0;
        const stop = (msg) => { if (watch !== null) navigator.geolocation.clearWatch(watch); watch = null; localStorage.removeItem(KEY); btn.textContent = 'Bagikan lokasi'; state.textContent = msg || 'Lokasi tidak dibagikan'; state.dataset.on = ''; };
        const start = () => {
            localStorage.setItem(KEY, '1'); btn.textContent = 'Hentikan berbagi'; state.dataset.on = '1'; state.textContent = 'Mencari lokasi…';
            watch = navigator.geolocation.watchPosition(async (p) => {
                if (Date.now() - last < 20000) return;
                last = Date.now();
                try {
                    const r = await post(gps.dataset.url, { lat: p.coords.latitude, lng: p.coords.longitude, accuracy: Math.round(p.coords.accuracy) });
                    if (r.status === 409) return stop('Trip selesai, lokasi tidak lagi dibagikan');
                    state.textContent = r.ok ? 'Lokasi dibagikan (' + new Date().toTimeString().slice(0, 5) + ')' : 'Gagal kirim lokasi, mencoba lagi';
                } catch (e) { state.textContent = 'Tidak ada sinyal, mencoba lagi'; }
            }, (e) => stop(e.code === 1 ? 'Izin lokasi ditolak' : 'Lokasi tidak tersedia'), { enableHighAccuracy: true, maximumAge: 10000 });
        };
        btn.addEventListener('click', () => (watch === null ? start() : stop()));
        if (localStorage.getItem(KEY)) start(); // halaman dimuat ulang: lanjut bila sopir sudah mengizinkan
    }

    // Scan: BarcodeDetector bila ada (Chrome Android), cadangan pustaka jsQR (hanya QR).
    const dlg = root.querySelector('[data-scan-dialog]'), video = dlg?.querySelector('video'), msg = dlg?.querySelector('[data-scan-msg]');
    let stream = null, timer = null;
    const close = () => { clearInterval(timer); stream?.getTracks().forEach((t) => t.stop()); stream = null; dlg.close(); };
    dlg?.querySelector('[data-scan-close]').addEventListener('click', close);
    root.querySelectorAll('[data-scan]').forEach((b) => b.addEventListener('click', async () => {
        msg.textContent = 'Arahkan kamera ke kode / QR pada surat jalan';
        dlg.showModal();
        try { stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } }); } catch (e) { msg.textContent = 'Kamera tidak diizinkan'; return; }
        video.srcObject = stream; await video.play();
        const det = 'BarcodeDetector' in window ? new BarcodeDetector() : null;
        const jsqr = det ? null : (await import('jsqr')).default;
        const cv = document.createElement('canvas'), cx = cv.getContext('2d', { willReadFrequently: true });
        let busy = false;
        timer = setInterval(async () => {
            if (busy || video.readyState < 2) return;
            busy = true;
            let code = null;
            try {
                if (det) code = (await det.detect(video))[0]?.rawValue;
                else { cv.width = video.videoWidth; cv.height = video.videoHeight; cx.drawImage(video, 0, 0); code = jsqr(cx.getImageData(0, 0, cv.width, cv.height).data, cv.width, cv.height)?.data; }
            } catch (e) { /* bingkai gagal dibaca: coba lagi */ }
            if (code) {
                clearInterval(timer);
                const r = await post(b.dataset.url, { code });
                if (r.ok) { close(); location.reload(); } else { msg.textContent = 'Gagal menyimpan hasil pindai'; busy = false; }
            } else busy = false;
        }, 400);
    }));
}
