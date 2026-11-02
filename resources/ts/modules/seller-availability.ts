import { defineBehavior } from '../core/behavior';

export const sellerAvailability = defineBehavior<HTMLElement>({
    name: 'seller-availability',
    selector: '[data-seller-availability]',
    mount(element) {
        const expires = Date.parse(element.dataset['expires'] ?? '');
        let timer: ReturnType<typeof setTimeout> | undefined;
        const cleanup = (): void => {
            if (timer !== undefined) clearTimeout(timer);
            document.removeEventListener('visibilitychange', check);
            document.removeEventListener('livewire:navigating', cleanup);
        };
        const check = (): void => {
            if (timer !== undefined) clearTimeout(timer);
            if (!element.isConnected) { cleanup(); return; }
            const remaining = expires - Date.now();
            if (!Number.isFinite(remaining) || remaining <= 0) { element.remove(); cleanup(); return; }
            timer = setTimeout(check, Math.min(remaining, 2147000000));
        };
        document.addEventListener('visibilitychange', check);
        document.addEventListener('livewire:navigating', cleanup, { once: true });
        check();
    },
});
