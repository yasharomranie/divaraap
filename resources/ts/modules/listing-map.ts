import type { Marker } from 'leaflet';
import { defineBehavior } from '../core/behavior';
import { query, queryAll } from '../core/dom';

interface Point { id: number; title: string; price: string; lat: number; lng: number; url: string }
interface Bounds { west: number; east: number; south: number; north: number }

export const listingMap = defineBehavior<HTMLElement>({
    name: 'listing-map',
    selector: '[data-listing-map]',
    mount(root) {
        const canvas = query('[data-map-canvas]', HTMLElement, root);
        const status = query('[data-map-status]', HTMLElement, root);
        const retry = query('[data-map-retry]', HTMLButtonElement, root);
        const area = query('[data-map-search-area]', HTMLAnchorElement, root);
        if (canvas === null || status === null || retry === null || area === null) return;
        retry.addEventListener('click', () => { window.location.reload(); });
        status.textContent = root.dataset['loading'] ?? '';
        const initialize = async (): Promise<void> => {
            const L = await import('leaflet');
            await import('leaflet/dist/leaflet.css');
            if (!root.isConnected) return;
            const points = JSON.parse(query('[data-map-points]', HTMLScriptElement, root)?.textContent ?? '[]') as Point[];
            const bounds = JSON.parse(query('[data-map-bounds]', HTMLScriptElement, root)?.textContent ?? 'null') as Bounds | null;
            const map = L.map(canvas, { zoomAnimation: false, fadeAnimation: false, markerZoomAnimation: false, inertia: false, scrollWheelZoom: false, zoomControl: false, keyboard: true, worldCopyJump: true, attributionControl: true, maxZoom: 18 });
            L.control.zoom({ zoomInTitle: root.dataset['zoomIn'] ?? 'Zoom in', zoomOutTitle: root.dataset['zoomOut'] ?? 'Zoom out' }).addTo(map);
            document.addEventListener('livewire:navigating', () => { map.remove(); }, { once: true });
            const tiles = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors' });
            let failed = false;
            const tileTimeout = window.setTimeout(() => { status.textContent = root.dataset['failed'] ?? ''; retry.hidden = false; }, 12000);
            map.on('unload', () => { window.clearTimeout(tileTimeout); });
            tiles.on('tileerror', () => { window.clearTimeout(tileTimeout); failed = true; status.textContent = root.dataset['failed'] ?? ''; retry.hidden = false; });
            tiles.on('load', () => { window.clearTimeout(tileTimeout); if (!failed) { status.textContent = root.dataset['ready'] ?? ''; retry.hidden = true; } });
            tiles.addTo(map);
            const groups = new Map<string, Point[]>();
            for (const point of points) {
                const key = `${String(point.lat)},${String(point.lng)}`;
                const group = groups.get(key) ?? [];
                group.push(point);
                groups.set(key, group);
            }
            const markers = new Map<number, Marker>();
            for (const group of groups.values()) {
                const first = group[0];
                if (first === undefined) continue;
                const lng = bounds !== null && bounds.west > bounds.east && first.lng < bounds.west ? first.lng + 360 : first.lng;
                const label = group.length === 1 ? first.title : `${String(group.length)} ${root.dataset['nearby'] ?? ''}`;
                const marker = L.marker([first.lat, lng], { keyboard: true, title: label, alt: label, icon: L.divIcon({ className: 'listing-map-pin', html: group.length > 1 ? String(group.length) : '•', iconSize: [44, 44], iconAnchor: [22, 22] }) });
                const popup = document.createElement('div');
                popup.className = 'listing-map-popup';
                popup.dir = document.documentElement.dir || 'rtl';
                for (const point of group.slice(0, 10)) {
                    const link = document.createElement('a');
                    link.href = point.url;
                    link.textContent = point.title;
                    const price = document.createElement('p');
                    price.textContent = point.price;
                    popup.append(link, price);
                }
                if (group.length > 10) {
                    const hint = document.createElement('p');
                    hint.textContent = root.dataset['groupHint'] ?? '';
                    popup.append(hint);
                }
                marker.bindPopup(popup, { autoPan: false, maxHeight: 180 }).addTo(map);
                marker.on('popupopen', () => { map.panInside(marker.getLatLng(), { paddingTopLeft: [20, Math.min(canvas.clientHeight - 40, 220)], paddingBottomRight: [20, 20], animate: false }); });
                for (const point of group) markers.set(point.id, marker);
            }
            if (bounds !== null) map.fitBounds([[bounds.south, bounds.west], [bounds.north, bounds.east < bounds.west ? bounds.east + 360 : bounds.east]], { animate: false, maxZoom: 16 });
            else if (points.length > 0) map.fitBounds(points.map((point) => [point.lat, point.lng] as [number, number]), { padding: [24, 24], maxZoom: 13, animate: false });
            else map.setView([32, 53], 5);
            const updateArea = (): void => {
                const current = map.getBounds();
                const url = new URL(window.location.href);
                url.searchParams.delete('page');
                const clamp = (lat: number): number => Math.max(-85.05112878, Math.min(85.05112878, lat));
                const south = clamp(current.getSouth());
                const north = clamp(current.getNorth());
                if (south >= north) { area.hidden = true; return; }
                const wrap = (lng: number): number => ((lng + 180) % 360 + 360) % 360 - 180;
                const wholeWorld = current.getEast() - current.getWest() >= 360;
                url.searchParams.set('west', wholeWorld ? '-180' : wrap(current.getWest()).toFixed(6));
                url.searchParams.set('east', wholeWorld ? '180' : wrap(current.getEast()).toFixed(6));
                url.searchParams.set('south', String(south));
                url.searchParams.set('north', String(north));
                area.href = url.toString();
                area.hidden = false;
            };
            map.on('moveend', updateArea);
            updateArea();
            for (const button of queryAll('[data-map-focus]', HTMLButtonElement, root)) {
                const marker = markers.get(Number(button.dataset['mapFocus']));
                if (marker === undefined) continue;
                button.hidden = false;
                button.addEventListener('click', () => { map.setView(marker.getLatLng(), Math.max(map.getZoom(), 13), { animate: false }); marker.openPopup(); marker.getPopup()?.getElement()?.querySelector('a')?.focus(); });
            }
        };
        void initialize().catch(() => { status.textContent = root.dataset['failed'] ?? ''; retry.hidden = false; });
    },
});
