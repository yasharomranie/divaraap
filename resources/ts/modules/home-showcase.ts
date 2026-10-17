import { defineBehavior } from '../core/behavior';

export const homeShowcase = defineBehavior<HTMLElement>({
    name: 'home-showcase',
    selector: '[data-home-showcase]',
    mount(element) {
        const details = element.querySelector<HTMLDetailsElement>('[data-home-details]');
        const desktop = window.matchMedia('(min-width: 64rem)');
        if (details !== null) {
            details.open = desktop.matches;
            desktop.addEventListener('change', () => { details.open = desktop.matches; });
        }
        const slides = Array.from(element.querySelectorAll<HTMLElement>('[data-home-slide]'));
        const positions = Array.from(element.querySelectorAll<HTMLButtonElement>('[data-home-position]'));
        const controls = element.querySelector<HTMLElement>('[data-home-controls]');
        if (slides.length < 2 || controls === null) return;
        let current = 0;
        const show = (index: number): void => {
            current = (index + slides.length) % slides.length;
            slides.forEach((slide, position) => { slide.hidden = position !== current; });
            positions.forEach((button, position) => {
                button.setAttribute('aria-current', String(position === current));
            });
        };
        controls.hidden = false;
        positions.forEach((button, index) => { button.addEventListener('click', () => { show(index); }); });
        element.querySelector('[data-home-previous]')?.addEventListener('click', () => { show(current - 1); });
        element.querySelector('[data-home-next]')?.addEventListener('click', () => { show(current + 1); });
        controls.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') return;
            event.preventDefault();
            const rtl = document.documentElement.dir === 'rtl';
            show(current + ((event.key === 'ArrowLeft') === rtl ? 1 : -1));
            positions[current]?.focus();
        });
    },
});
