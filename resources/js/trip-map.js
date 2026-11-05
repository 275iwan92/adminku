// Peta rute planner Pengiriman: ilustrasi urutan alamat (bukan navigasi). Jarak lewat satu antarmuka `router` (km, min) supaya bisa diganti Mapbox / Google tanpa menyentuh planner.
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import '../css/trip-map.css';

const rad = (d) => (d * Math.PI) / 180;
const line = (a, b) => {
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 12742 * Math.asin(Math.sqrt(h));
};

// shortcut: garis lurus x 1,3 dan 30 km/jam, ganti dengan router jalan nyata (Mapbox / Google) bila perlu akurasi.
export const straightRouter = {
    factor: 1.3,
    kmh: 30,
    km(a, b) { return line(a, b) * this.factor; },
    min(a, b) { return Math.round((this.km(a, b) / this.kmh) * 60); },
};

const icon = (txt, cls) => L.divIcon({ className: '', html: '<span class="sp-pin ' + cls + '">' + txt + '</span>', iconSize: [26, 26], iconAnchor: [13, 13] });

window.TripMap = {
    router: straightRouter,
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
