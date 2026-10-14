import { defineBehavior } from '../core/behavior';
import { lockScroll, onDocument, query, queryAll, toggleClass } from '../core/dom';

export const listingFilters = defineBehavior<HTMLFormElement>({
    name: 'listing-filters',
    selector: '[data-filter-form]',
    mount(form) {
        const autoInputs = queryAll<HTMLSelectElement>('[data-filter-auto]', HTMLSelectElement, form);
        const resetButton = query<HTMLButtonElement>('[data-filter-reset]', HTMLButtonElement, form);

        for (const input of autoInputs) {
            input.addEventListener('change', () => {
                form.requestSubmit();
            });
        }

        if (resetButton !== null) {
            resetButton.addEventListener('click', () => {
                for (const field of queryAll<HTMLInputElement>('input[type="text"], input[type="number"]', HTMLInputElement, form)) {
                    field.value = '';
                }

                for (const checkbox of queryAll<HTMLInputElement>('input[type="checkbox"]', HTMLInputElement, form)) {
                    checkbox.checked = false;
                }

                for (const select of queryAll<HTMLSelectElement>('select', HTMLSelectElement, form)) {
                    select.selectedIndex = 0;
                }

                form.requestSubmit();
            });
        }
    },
});

export const filterDrawer = defineBehavior<HTMLElement>({
    name: 'filter-drawer',
    selector: '[data-filter-drawer]',
    mount(drawer) {
        const openers = queryAll<HTMLButtonElement>('[data-filter-drawer-open]', HTMLButtonElement);
        const closers = queryAll<HTMLButtonElement>('[data-filter-drawer-close]', HTMLButtonElement, drawer);
        const panel = query<HTMLElement>('[role="dialog"]', HTMLElement, drawer);
        const desktop = window.matchMedia('(min-width: 64rem)');
        let opener: HTMLButtonElement | null = null;

        const setOpen = (open: boolean): void => {
            const modalOpen = open && !desktop.matches;
            toggleClass(drawer, 'is-open', modalOpen);
            drawer.setAttribute('aria-hidden', desktop.matches || modalOpen ? 'false' : 'true');
            panel?.setAttribute('role', desktop.matches ? 'region' : 'dialog');
            if (desktop.matches) panel?.removeAttribute('aria-modal');
            else panel?.setAttribute('aria-modal', 'true');
            lockScroll(modalOpen);
            if (modalOpen) {
                query<HTMLButtonElement>('.drawer__head button', HTMLButtonElement, drawer)?.focus();
            } else {
                opener?.focus();
            }
        };

        setOpen(false);
        desktop.addEventListener('change', () => { setOpen(false); });

        for (const button of openers) {
            button.addEventListener('click', () => {
                opener = button;
                setOpen(true);
            });
        }

        for (const closer of closers) {
            closer.addEventListener('click', () => {
                setOpen(false);
            });
        }

        onDocument('keydown', (event) => {
            if (!drawer.classList.contains('is-open')) return;
            if (event.key === 'Escape') {
                event.preventDefault();
                setOpen(false);
            }
            if (event.key !== 'Tab' || panel === null) return;
            const controls = queryAll<HTMLElement>('button:not([disabled]), a[href], input:not([disabled]), select:not([disabled]), textarea:not([disabled])', HTMLElement, panel)
                .filter((element) => element.offsetParent !== null);
            const first = controls[0];
            const last = controls.at(-1);
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last?.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first?.focus();
            }
        });
    },
});

export const viewModeToggle = defineBehavior<HTMLElement>({
    name: 'view-mode-toggle',
    selector: '[data-view-toggle]',
    mount(toggle) {
        const target = query<HTMLElement>('[data-listing-collection]', HTMLElement);

        if (target === null) {
            return;
        }

        const buttons = queryAll<HTMLButtonElement>('[data-view-mode]', HTMLButtonElement, toggle);

        const apply = (mode: string): void => {
            target.dataset['viewMode'] = mode;

            for (const button of buttons) {
                const active = button.dataset['viewMode'] === mode;
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            }

            try {
                localStorage.setItem('openclassify.viewMode', mode);
            } catch {
                return;
            }
        };

        let initial = 'grid';

        try {
            initial = localStorage.getItem('openclassify.viewMode') ?? 'grid';
        } catch {
            initial = 'grid';
        }

        apply(initial === 'list' ? 'list' : 'grid');

        for (const button of buttons) {
            button.addEventListener('click', () => {
                apply(button.dataset['viewMode'] ?? 'grid');
            });
        }
    },
});
