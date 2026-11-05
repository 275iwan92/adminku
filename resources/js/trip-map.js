// Peta rute planner Pengiriman: ilustrasi urutan alamat (bukan navigasi). Jarak dan waktu tempuh lewat satu antarmuka `router.matrix` supaya bisa diganti Mapbox / Google tanpa menyentuh planner.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/trip-map.css';

const rad = (d) => (d * Math.PI) / 180;
const line = (a, b) => {
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 12742 * Math.asin(Math.sqrt(h));
};

// Antarmuka router: `matrix(pts)` -> Promise<{min: menit[][], km: km[][]}> untuk titik [{lat, lng}]. Dua isi: mesin jalan nyata (OSRM, mudah diganti Mapbox / Google) dan cadangan garis lurus.
// shortcut: cadangan garis lurus x 1,3 pada 30 km/jam bila mesin rute tidak menjawab; mesin demo OSRM publik bukan untuk beban produksi, pasang server OSRM sendiri lewat SSTOCK_ROUTE_URL.
export const straightRouter = {
    name: 'garis lurus',
    async matrix(pts) {
        const km = pts.map((a) => pts.map((b) => line(a, b) * 1.3));
        return { km, min: km.map((r) => r.map((v) => Math.round((v / 30) * 60))) };
    },
};

export const osrmRouter = (base) => ({
    name: 'mesin rute',
    async matrix(pts) {
        const url = base.replace(/\/$/, '') + '/table/v1/driving/' + pts.map((p) => p.lng + ',' + p.lat).join(';') + '?annotations=duration,distance';
        const res = await fetch(url);
        const j = await res.json();
        if (!res.ok || j.code !== 'Ok' || !j.durations || j.durations.some((r) => r.some((v) => v == null))) throw new Error('route');
        return { min: j.durations.map((r) => r.map((v) => Math.round(v / 60))), km: j.distances.map((r) => r.map((v) => v / 1000)) };
    },
});

// Warna ruas: tiap ruas (dari titik sebelumnya ke alamat ini) satu warna; sama di garis peta, penanda, dan nomor di daftar planner.
const LEG = ['#2f7fd1', '#e5742b', '#2fa46a', '#9b59d0', '#e0457b', '#12a8b8', '#c9a01a', '#6b7bd6'];
const legColor = (i) => LEG[i % LEG.length];
const icon = (txt, cls, color) => L.divIcon({ className: '', html: '<span class="sp-pin ' + cls + '" style="background:' + color + '">' + txt + '</span>', iconSize: [28, 28], iconAnchor: [14, 14] });

window.TripMap = {
    router: straightRouter,
    legColor,
    straight: straightRouter,
    osrm: osrmRouter,
    // cb: { onPick(lat,lng) klik peta, onMove(i,lat,lng) seret titik, onSelect(i) klik titik }
    mount(el, office, locked, cb) {
        const map = L.map(el, { scrollWheelZoom: false }).setView([office.lat, office.lng], 12);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
        const layer = L.layerGroup().addTo(map);
        if (!locked) map.on('click', (e) => cb.onPick(+e.latlng.lat.toFixed(6), +e.latlng.lng.toFixed(6)));
        setTimeout(() => map.invalidateSize(), 200);
        return {
            draw(stops, sel, fit) {
                layer.clearLayers();
                const pts = [[office.lat, office.lng]];
                L.marker(pts[0], { icon: icon('K', 'sp-pk', '#475569'), interactive: false }).addTo(layer);
                stops.forEach((s, i) => {
                    if (s.lat == null || s.lng == null) return;
                    const m = L.marker([s.lat, s.lng], { icon: icon(i + 1, (s.kind === 'ant' ? 'sp-pa' : 'sp-pb') + (i === sel ? ' sp-ps' : ''), legColor(i)), draggable: !locked, zIndexOffset: i === sel ? 500 : 0 }).addTo(layer);
                    m.on('click', () => cb.onSelect(i));
                    m.on('dragend', () => { const p = m.getLatLng(); cb.onMove(i, +p.lat.toFixed(6), +p.lng.toFixed(6)); });
                    pts.push([s.lat, s.lng]);
                });
                if (pts.length > 1) {
                    [...pts, pts[0]].slice(1).forEach((p, k) => L.polyline([[...pts, pts[0]][k], p], { color: k < pts.length - 1 ? legColor(k) : '#64748b', weight: 5, opacity: 0.85, dashArray: k < pts.length - 1 ? null : '6 8' }).addTo(layer));
                    if (fit) map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
                }
            },
        };
    },
};
