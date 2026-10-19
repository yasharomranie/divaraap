import type { Marker } from 'leaflet';
import { defineBehavior } from '../core/behavior';
import { query } from '../core/dom';

export const listingCreateLocation = defineBehavior<HTMLDetailsElement>({
    name: 'listing-create-location',
    selector: '[data-create-location]',
    mount(root) {
        const latitude = query('#create-latitude', HTMLInputElement, root);
        const longitude = query('#create-longitude', HTMLInputElement, root);
        const canvas = query('[data-create-location-canvas]', HTMLElement, root);
        const status = query('[data-create-location-status]', HTMLElement, root);
        const current = query('[data-create-location-current]', HTMLButtonElement, root);
        const clear = query('[data-create-location-clear]', HTMLButtonElement, root);
        if (!latitude || !longitude || !canvas || !status || !current || !clear) return;
        let initialized = false;
        const initialize = async (): Promise<void> => {
            if (initialized || !root.open) return;
            initialized = true;
            status.textContent = root.dataset['loading'] ?? '';
            current.hidden = false;
            clear.hidden = false;
            const write = (lat: string, lng: string): void => {
                latitude.value = lat;
                longitude.value = lng;
                latitude.dispatchEvent(new Event('input', { bubbles: true }));
                longitude.dispatchEvent(new Event('input', { bubbles: true }));
            };
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            if (!root.isConnected) return;
            const map = L.map(canvas, { zoomAnimation: false, fadeAnimation: false, markerZoomAnimation: false, inertia: false, scrollWheelZoom: false, zoomControl: false, maxZoom: 18 });
            L.control.zoom({ zoomInTitle: root.dataset['zoomIn'] ?? '', zoomOutTitle: root.dataset['zoomOut'] ?? '' }).addTo(map);
            let marker: Marker | null = null;
            let locationRequest = 0;
            const sync = (): boolean => {
                const lat = Number(latitude.value);
                const lng = Number(longitude.value);
                const valid = latitude.value !== '' && longitude.value !== '' && Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 85.05112878 && Math.abs(lng) <= 180;
                if (!valid) { marker?.remove(); marker = null; return false; }
                const point: [number, number] = [lat, lng];
                if (marker) marker.setLatLng(point);
                else marker = L.marker(point, { icon: L.divIcon({ className: 'listing-map-pin', html: '•', iconSize: [44, 44], iconAnchor: [22, 22] }) }).addTo(map);
                return true;
            };
            const pick = (lat: number, lng: number): void => {
                write(Math.max(-85.051, Math.min(85.051, lat)).toFixed(3), (((lng + 180) % 360 + 360) % 360 - 180).toFixed(3));
                sync();
                status.textContent = root.dataset['selected'] ?? '';
            };
            if (sync()) map.setView([Number(latitude.value), Number(longitude.value)], 13);
            else map.setView([32, 53], 5);
            map.on('click', event => { pick(event.latlng.lat, event.latlng.lng); });
            latitude.addEventListener('input', sync);
            longitude.addEventListener('input', sync);
            clear.addEventListener('click', () => { locationRequest++; current.disabled = false; write('', ''); sync(); status.textContent = root.dataset['empty'] ?? ''; });
            current.addEventListener('click', () => {
                if (!('geolocation' in navigator) || !window.isSecureContext) { status.textContent = root.dataset['geolocationFailed'] ?? ''; return; }
                const request = ++locationRequest;
                current.disabled = true;
                status.textContent = root.dataset['locating'] ?? '';
                navigator.geolocation.getCurrentPosition(position => {
                    if (request !== locationRequest || !root.isConnected) return;
                    current.disabled = false;
                    pick(position.coords.latitude, position.coords.longitude);
                    map.setView([Number(latitude.value), Number(longitude.value)], 14, { animate: false });
                }, () => { if (request !== locationRequest || !root.isConnected) return; current.disabled = false; status.textContent = root.dataset['geolocationFailed'] ?? ''; }, { timeout: 10000, maximumAge: 60000 });
            });
            const timer = window.setTimeout(() => { status.textContent = root.dataset['failed'] ?? ''; }, 12000);
            let failed = false;
            const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' });
            tiles.on('load', () => { window.clearTimeout(timer); if (!failed) status.textContent = root.dataset['ready'] ?? ''; });
            tiles.on('tileerror', () => { failed = true; window.clearTimeout(timer); status.textContent = root.dataset['failed'] ?? ''; });
            tiles.addTo(map);
            const resize = (): void => { if (root.open) map.invalidateSize({ animate: false }); };
            root.addEventListener('toggle', resize);
            const observer = new MutationObserver(() => {
                if (root.isConnected) return;
                observer.disconnect();
                window.clearTimeout(timer);
                map.remove();
            });
            observer.observe(document.body, { childList: true, subtree: true });
        };
        const start = (): void => { void initialize().catch(() => { status.textContent = root.dataset['failed'] ?? ''; }); };
        root.addEventListener('toggle', start);
        start();
    },
});
