import { defineBehavior } from '../core/behavior';

export const advertisingMetrics = defineBehavior<HTMLElement>({
    name: 'advertising-metrics',
    selector: '[data-behavior="advertising-metrics"]',
    mount(element) {
        const token = element.dataset['eventToken'];
        const url = element.dataset['eventUrl'];
        const image = element.querySelector('img');
        if (!token || !url || !image || !('IntersectionObserver' in window)) return;
        let visible = false;
        let counted = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const device = (): string => matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
        const send = (kind: string, listing?: number, choice?: string): void => {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            void fetch(url, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ token, kind, device: device(), listing, choice }),
            }).catch(() => undefined);
        };
        const stop = (): void => {
            if (timer !== undefined) clearTimeout(timer);
            timer = undefined;
        };
        const update = (): void => {
            stop();
            if (!counted && visible && document.visibilityState === 'visible' && image.complete && image.naturalWidth > 0) {
                timer = setTimeout(() => {
                    if (!element.isConnected || document.visibilityState !== 'visible' || element.getBoundingClientRect().width === 0) return;
                    counted = true;
                    send('impression');
                    observer.disconnect();
                    document.removeEventListener('visibilitychange', update);
                    document.removeEventListener('livewire:navigating', cleanup);
                }, 1000);
            }
        };
        const observer = new IntersectionObserver(entries => {
            visible = entries.some(entry => entry.isIntersecting && entry.intersectionRatio >= 0.5);
            update();
        }, { threshold: [0, 0.5] });
        const cleanup = (): void => {
            stop();
            observer.disconnect();
            document.removeEventListener('visibilitychange', update);
            document.removeEventListener('livewire:navigating', cleanup);
        };
        observer.observe(element);
        image.addEventListener('load', update);
        document.addEventListener('visibilitychange', update);
        document.addEventListener('livewire:navigating', cleanup);
        element.querySelectorAll<HTMLAnchorElement>('a').forEach(link => {
            const listing = link.dataset['adListing'] ? Number(link.dataset['adListing']) : undefined;
            const choice = link.dataset['adChoice'];
            link.addEventListener('click', event => {
                if (event.isTrusted && element.getBoundingClientRect().width > 0) send('click', listing, choice);
            });
            link.addEventListener('auxclick', event => {
                if (event.isTrusted && event.button === 1 && element.getBoundingClientRect().width > 0) send('click', listing, choice);
            });
        });
    },
});
