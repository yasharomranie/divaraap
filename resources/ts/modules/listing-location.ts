import type { Marker, LeafletMouseEvent } from 'leaflet';
import { defineBehavior } from '../core/behavior';
import { query } from '../core/dom';

export const listingLocation = defineBehavior<HTMLElement>({
    name: 'listing-location',
    selector: '[data-listing-location]',
    mount(root) {
        const canvas = query('[data-location-canvas]', HTMLElement, root);
        const status = query('[data-location-status]', HTMLElement, root);
        const latitude = query('#map-latitude', HTMLInputElement, root);
        const longitude = query('#map-longitude', HTMLInputElement, root);
        if (canvas === null || status === null || latitude === null || longitude === null) return;
        status.textContent = root.dataset['loading'] ?? '';
        const initialize = async (): Promise<void> => {
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            if (!root.isConnected) return;
            const point = JSON.parse(query('[data-location-point]', HTMLScriptElement, root)?.textContent ?? 'null') as { lat: number; lng: number } | null;
            const map = L.map(canvas, { zoomAnimation: false, fadeAnimation: false, markerZoomAnimation: false, inertia: false, scrollWheelZoom: false, zoomControl: false, maxZoom: 18 });
            L.control.zoom({ zoomInTitle: root.dataset['zoomIn'] ?? 'Zoom in', zoomOutTitle: root.dataset['zoomOut'] ?? 'Zoom out' }).addTo(map);
            document.addEventListener('livewire:navigating', () => { map.remove(); }, { once: true });
            const tileLayer = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' });
            let failed = false;
            const tileTimeout = window.setTimeout(() => { status.textContent = root.dataset['failed'] ?? ''; }, 12000);
            map.on('unload', () => { window.clearTimeout(tileTimeout); });
            tileLayer.on('load', () => { window.clearTimeout(tileTimeout); if (!failed) status.textContent = root.dataset['ready'] ?? ''; });
            tileLayer.on('tileerror', () => { window.clearTimeout(tileTimeout); failed = true; status.textContent = root.dataset['failed'] ?? ''; });
            tileLayer.addTo(map);
            let marker: Marker | null = null;
            const pick = (lat: number, lng: number, writeFields = true): void => {
                const position: [number, number] = [Number(lat.toFixed(3)), Number(lng.toFixed(3))];
                if (writeFields) {
                    latitude.value = String(position[0]);
                    longitude.value = String(position[1]);
                }
                if (marker === null) marker = L.marker(position, { icon: L.divIcon({ className: 'listing-map-pin', html: '•', iconSize: [44, 44], iconAnchor: [22, 22] }) }).addTo(map);
                else marker.setLatLng(position);
            };
            if (point !== null) { pick(point.lat, point.lng, false); map.setView([point.lat, point.lng], 13); }
            else map.setView([32, 53], 5);
            map.on('click', (event: LeafletMouseEvent) => { const lng = ((event.latlng.lng + 180) % 360 + 360) % 360 - 180; pick(Math.max(-85.051, Math.min(85.051, event.latlng.lat)), lng); });
            status.textContent = root.dataset['ready'] ?? '';
        };
        void initialize().catch(() => { status.textContent = root.dataset['failed'] ?? ''; });
    },
});
