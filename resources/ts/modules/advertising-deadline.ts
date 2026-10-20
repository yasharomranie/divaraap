import { defineBehavior } from '../core/behavior';

export const advertisingDeadline = defineBehavior<HTMLElement>({
    name: 'advertising-deadline',
    selector: '[data-behavior="advertising-deadline"]',
    mount(element) {
        const deadline = Date.parse(element.dataset['deadline'] ?? '');
        const serverNow = Date.parse(element.dataset['serverNow'] ?? '');
        const root = element.closest<HTMLElement>('[data-banner]');
        const output = element.querySelector<HTMLElement>('[data-deadline-remaining]');
        if (!root || !output || !Number.isFinite(deadline) || !Number.isFinite(serverNow)) return;
        const started = performance.now();
        const wallStarted = Date.now();
        const numbers = new Intl.NumberFormat(element.dataset['locale'] === 'fa' ? 'fa-IR' : 'en');
        let timer: ReturnType<typeof setTimeout> | undefined;
        const cleanup = (): void => {
            if (timer !== undefined) clearTimeout(timer);
            document.removeEventListener('visibilitychange', update);
            document.removeEventListener('livewire:navigating', cleanup);
            window.removeEventListener('pageshow', update);
        };
        const update = (): void => {
            if (timer !== undefined) clearTimeout(timer);
            if (!element.isConnected) {
                cleanup();
                return;
            }
            const elapsed = Math.max(0, performance.now() - started, Date.now() - wallStarted);
            const remaining = deadline - serverNow - elapsed;
            if (remaining <= 0) {
                root.querySelectorAll('a').forEach(link => {
                    link.removeAttribute('href');
                    link.setAttribute('aria-disabled', 'true');
                });
                root.hidden = true;
                cleanup();
                return;
            }
            const minutes = Math.floor(remaining / 60000);
            const parts: string[] = [];
            for (const [value, label] of [
                [Math.floor(minutes / 1440), element.dataset['day']],
                [Math.floor(minutes % 1440 / 60), element.dataset['hour']],
                [minutes % 60, element.dataset['minute']],
            ] as const) {
                if (value > 0) parts.push(`${numbers.format(value)} ${label ?? ''}`);
            }
            const text = parts.length > 0 ? parts.join(' · ') : (element.dataset['underMinute'] ?? '');
            output.textContent = `${element.dataset['remaining'] ?? ''} ${text}`;
            timer = setTimeout(update, Math.min(60000, remaining));
        };
        document.addEventListener('visibilitychange', update);
        document.addEventListener('livewire:navigating', cleanup);
        window.addEventListener('pageshow', update);
        update();
    },
});
