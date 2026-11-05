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

const icon = (txt, cls) => L.divIcon({ className: '', html: '<span class="sp-pin ' + cls + '">' + txt + '</span>', iconSize: [26, 26], iconAnchor: [13, 13] });

window.TripMap = {
    router: straightRouter,
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
                L.marker(pts[0], { icon: icon('K', 'sp-pk'), interactive: false }).addTo(layer);
                stops.forEach((s, i) => {
                    if (s.lat == null || s.lng == null) return;
                    const m = L.marker([s.lat, s.lng], { icon: icon(i + 1, (s.kind === 'ant' ? 'sp-pa' : 'sp-pb') + (i === sel ? ' sp-ps' : '')), draggable: !locked, zIndexOffset: i === sel ? 500 : 0 }).addTo(layer);
                    m.on('click', () => cb.onSelect(i));
                    m.on('dragend', () => { const p = m.getLatLng(); cb.onMove(i, +p.lat.toFixed(6), +p.lng.toFixed(6)); });
                    pts.push([s.lat, s.lng]);
                });
                if (pts.length > 1) {
                    L.polyline([...pts, pts[0]], { color: '#2f7fd1', weight: 3, opacity: 0.8, dashArray: '6 6' }).addTo(layer);
                    if (fit) map.fitBounds(pts, { padding: [30, 30], maxZoom: 15 });
                }
            },
        };
    },
};
