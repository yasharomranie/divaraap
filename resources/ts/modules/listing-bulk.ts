import { defineBehavior } from '../core/behavior';

export const listingBulk = defineBehavior<HTMLFormElement>({
    name: 'listing-bulk',
    selector: '[data-listing-bulk]',
    mount(form) {
        const choices = Array.from(document.querySelectorAll<HTMLInputElement>('[data-bulk-choice]')).filter(input => input.form === form);
        const all = form.querySelector<HTMLButtonElement>('[data-bulk-all]');
        const clear = form.querySelector<HTMLButtonElement>('[data-bulk-clear]');
        const preview = form.querySelector<HTMLButtonElement>('[data-bulk-preview]');
        const count = form.querySelector<HTMLElement>('[data-bulk-count]');
        const status = form.querySelector<HTMLElement>('[data-bulk-status]');
        if (!all || !clear || !preview || !count || !status) return;
        all.hidden = false;
        clear.hidden = false;
        status.hidden = false;
        const update = (): void => {
            const selected = choices.filter(input => input.checked).length;
            count.textContent = new Intl.NumberFormat(document.documentElement.lang || 'en').format(selected);
            preview.disabled = selected === 0;
            all.disabled = selected === choices.length;
            clear.disabled = selected === 0;
        };
        all.addEventListener('click', () => { choices.forEach(input => { input.checked = true; }); update(); });
        clear.addEventListener('click', () => { choices.forEach(input => { input.checked = false; }); update(); });
        choices.forEach(input => { input.addEventListener('change', update); });
        update();
    },
});
