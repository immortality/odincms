// <x-admin.form.map>: a Leaflet map (OpenStreetMap tiles) with a draggable marker, kept in sync with the
// latitude / longitude inputs. Loaded only on pages that have one (resources/js/admin.js).

import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const PRECISION = 7; // ~1 cm; the columns are decimal(10, 7)

// An OdinAdmin-coloured pin (Font Awesome), so no marker images have to be bundled.
const pin = L.divIcon({
    className: 'map-pin',
    html: '<i class="fas fa-map-marker-alt"></i>',
    iconSize: [30, 40],
    iconAnchor: [15, 38],
});

function parse(value, limit) {
    const number = Number(String(value).trim().replace(',', '.'));

    return String(value).trim() !== '' && Number.isFinite(number) && Math.abs(number) <= limit ? number : null;
}

function setup(root) {
    const latInput = root.querySelector('[data-lat]');
    const lngInput = root.querySelector('[data-lng]');
    const clear = root.querySelector('[data-map-clear]');
    const center = JSON.parse(root.dataset.center || '[0, 0]');

    const current = () => {
        const lat = parse(latInput.value, 90);
        const lng = parse(lngInput.value, 180);

        return lat !== null && lng !== null ? L.latLng(lat, lng) : null;
    };

    const start = current();
    const map = L.map(root.querySelector('[data-map]'), { scrollWheelZoom: false })
        .setView(start ?? center, start ? 16 : Number(root.dataset.zoom || 12));

    L.tileLayer(root.dataset.tiles, { maxZoom: 19, attribution: root.dataset.attribution }).addTo(map);

    let marker = null;

    const place = (latLng, { pan = false } = {}) => {
        if (!marker) {
            marker = L.marker(latLng, { icon: pin, draggable: true, keyboard: true }).addTo(map);
            marker.on('dragend', () => write(marker.getLatLng()));
        } else {
            marker.setLatLng(latLng);
        }
        if (pan) map.panTo(latLng);
    };

    const write = (latLng) => {
        latInput.value = latLng.lat.toFixed(PRECISION);
        lngInput.value = latLng.lng.toFixed(PRECISION);
    };

    if (start) place(start);

    map.on('click', (event) => {
        place(event.latlng);
        write(event.latlng);
    });

    // Typed coordinates move the marker (once both are valid numbers).
    [latInput, lngInput].forEach((input) => input.addEventListener('change', () => {
        const latLng = current();
        if (latLng) place(latLng, { pan: true });
    }));

    clear?.addEventListener('click', () => {
        latInput.value = '';
        lngInput.value = '';
        marker?.remove();
        marker = null;
    });

    // The map may be laid out after it is created (cards, tabs, a resized window): keep the tiles right.
    new ResizeObserver(() => map.invalidateSize()).observe(root);
}

export function initMapPickers(root = document) {
    root.querySelectorAll('[data-map-picker]').forEach(setup);
}
