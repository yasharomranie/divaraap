import { defineBehavior } from '../core/behavior';
import { query } from '../core/dom';
import { request } from '../core/http';

interface Option { key: string; text: string }
interface Suggestions { titles: Option[]; descriptions: Option[] }
type TextField = HTMLInputElement | HTMLTextAreaElement;

export const listingTextSuggestions = defineBehavior<HTMLDetailsElement>({
    name: 'listing-text-suggestions',
    selector: '[data-listing-text-suggestions]',
    mount(root) {
        const generate = query('[data-suggest-generate]', HTMLButtonElement, root);
        const undo = query('[data-suggest-undo]', HTMLButtonElement, root);
        const results = query('[data-suggest-results]', HTMLElement, root);
        const status = query('[data-suggest-status]', HTMLElement, root);
        if (!generate || !undo || !results || !status) return;
        const field = (name: 'title' | 'description'): TextField | null => {
            const element = document.getElementById(root.dataset[name === 'title' ? 'titleId' : 'descriptionId'] ?? '');
            return element instanceof HTMLInputElement || element instanceof HTMLTextAreaElement ? element : null;
        };
        const context = (): Record<string, unknown> => {
            const city = document.getElementById(root.dataset['cityId'] ?? '');
            let cityName = '';
            if (city instanceof HTMLSelectElement && city.value !== '' && city.value !== '-1') cityName = city.selectedOptions[0]?.textContent.trim() ?? '';
            else if (city instanceof HTMLInputElement) cityName = city.value;
            return {
                title: field('title')?.value ?? '', description: field('description')?.value ?? '',
                category_id: Number(root.dataset['categoryId']) || null, city: cityName,
                description_limit: field('description')?.maxLength === 1450 ? 1450 : 4000,
            };
        };
        const write = (target: TextField, value: string): void => {
            target.value = value;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            target.dispatchEvent(new Event('blur'));
        };
        let snapshot = '';
        let last: { name: 'title' | 'description'; before: string; after: string } | null = null;
        generate.hidden = false;
        undo.addEventListener('click', () => {
            if (last === null) return;
            const target = field(last.name);
            if (target?.value !== last.after) { status.textContent = root.dataset['stale'] ?? ''; return; }
            write(target, last.before);
            last = null;
            undo.hidden = true;
            results.replaceChildren();
            status.textContent = root.dataset['restored'] ?? '';
        });
        const renderGroup = (name: 'title' | 'description', options: Option[]): void => {
            if (options.length === 0) return;
            const group = document.createElement('details');
            group.open = name === 'title';
            const heading = document.createElement('summary');
            heading.textContent = root.dataset[name === 'title' ? 'titleLabel' : 'descriptionLabel'] ?? '';
            group.append(heading);
            for (const [index, option] of options.entries()) {
                const card = document.createElement('div');
                card.className = 'listing-text-tools__option';
                const label = document.createElement('label');
                const id = `suggestion-${root.dataset['titleId'] ?? ''}-${name}-${String(index)}`;
                label.htmlFor = id;
                label.textContent = root.dataset[option.key] ?? heading.textContent;
                const preview = document.createElement('textarea');
                preview.id = id;
                preview.className = 'textarea';
                preview.rows = name === 'title' ? 2 : 5;
                preview.readOnly = true;
                preview.dir = 'auto';
                preview.value = option.text;
                const apply = document.createElement('button');
                apply.type = 'button';
                apply.className = 'button button--secondary';
                apply.dataset['suggestApply'] = name;
                apply.textContent = root.dataset[name === 'title' ? 'useTitle' : 'useDescription'] ?? '';
                apply.addEventListener('click', () => {
                    const target = field(name);
                    if (!target || JSON.stringify(context()) !== snapshot) { status.textContent = root.dataset['stale'] ?? ''; return; }
                    last = { name, before: target.value, after: option.text };
                    write(target, option.text);
                    results.replaceChildren();
                    undo.hidden = false;
                    status.textContent = root.dataset['applied'] ?? '';
                });
                card.append(label, preview, apply);
                group.append(card);
            }
            results.append(group);
        };
        generate.addEventListener('click', () => {
            const run = async (): Promise<void> => {
                generate.disabled = true;
                root.setAttribute('aria-busy', 'true');
                status.textContent = root.dataset['loading'] ?? '';
                results.replaceChildren();
                const body = context();
                snapshot = JSON.stringify(body);
                const controller = new AbortController();
                const timer = window.setTimeout(() => { controller.abort(); }, 10000);
                const response = await request<Suggestions>({ url: root.dataset['endpoint'] ?? '', method: 'POST', body, signal: controller.signal });
                window.clearTimeout(timer);
                generate.disabled = false;
                root.setAttribute('aria-busy', 'false');
                if (!root.isConnected) return;
                if (!response.ok) { status.textContent = root.dataset['failed'] ?? ''; return; }
                if (JSON.stringify(context()) !== snapshot) { status.textContent = root.dataset['stale'] ?? ''; return; }
                renderGroup('title', response.value.titles);
                renderGroup('description', response.value.descriptions);
                status.textContent = root.dataset[response.value.titles.length + response.value.descriptions.length > 0 ? 'ready' : 'empty'] ?? '';
            };
            void run();
        });
    },
});
