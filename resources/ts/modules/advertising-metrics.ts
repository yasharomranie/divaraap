import { defineBehavior } from '../core/behavior';

export const advertisingMetrics = defineBehavior<HTMLElement>({
    name: 'advertising-metrics',
    selector: '[data-behavior="advertising-metrics"]',
    mount(element) {
        const token = element.dataset['eventToken'];
        const url = element.dataset['eventUrl'];
        const video = element.querySelector<HTMLVideoElement>('[data-ad-video]');
        const image = video?.poster ? new Image() : element.querySelector('img');
        if (video?.poster && image) image.src = video.poster;
        if (!token || !url || !image || !('IntersectionObserver' in window)) return;
        let visible = false;
        let counted = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const device = (): string => matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
        const send = (kind: string, listing?: number, choice?: string): Promise<void> => {
            const csrf = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
            return fetch(url, {
                method: 'POST', credentials: 'same-origin', keepalive: true,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ token, kind, device: device(), listing, choice }),
            }).then(() => undefined).catch(() => undefined);
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
                if (!event.isTrusted || event.defaultPrevented || element.getBoundingClientRect().width === 0) return;
                const destination = new URL(link.href, location.href);
                const shopNavigation = event.button === 0 && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey
                    && (!link.target || link.target === '_self') && !link.hasAttribute('download')
                    && destination.origin === location.origin && /^\/sellers\/[1-9][0-9]*\/?$/.test(destination.pathname);
                if (shopNavigation) {
                    event.preventDefault();
                    void Promise.race([send('click', listing, choice), new Promise<void>(resolve => setTimeout(resolve, 500))])
                        .then(() => location.assign(link.href));
                } else {
                    void send('click', listing, choice);
                }
            });
            link.addEventListener('auxclick', event => {
                if (event.isTrusted && event.button === 1 && element.getBoundingClientRect().width > 0) send('click', listing, choice);
            });
        });
    },
});
