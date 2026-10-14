import { defineBehavior } from '../core/behavior';
import { attribute, numericAttribute, query, queryAll, setHidden, setText, toggleClass } from '../core/dom';
import { post } from '../core/http';
import { subscribeToInbox } from '../realtime/echo';
import type { InboxMessagePayload } from '../core/types';

interface SentMessage {
    readonly id: number;
    readonly body: string;
    readonly created_at: string;
}

function isSentMessage(value: unknown): value is SentMessage {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return (
        typeof candidate['id'] === 'number' &&
        typeof candidate['body'] === 'string' &&
        typeof candidate['created_at'] === 'string'
    );
}

function paintUnreadCount(count: number): void {
    for (const badge of queryAll<HTMLElement>('[data-inbox-badge]', HTMLElement)) {
        setText(badge, count > 99 ? '99+' : String(count));
        setHidden(badge, count === 0);
    }
}

function appendBubble(list: HTMLElement, id: number, body: string, timestamp: string, outgoing: boolean): void {
    if (list.querySelector(`[data-message-id="${String(id)}"]`) !== null) return;
    const item = document.createElement('li');
    item.dataset['messageId'] = String(id);
    item.className = outgoing ? 'thread__bubble thread__bubble--out' : 'thread__bubble thread__bubble--in';

    const text = document.createElement('p');
    text.className = 'thread__text';
    text.dir = 'auto';
    text.textContent = body;

    const meta = document.createElement('time');
    meta.className = 'thread__time';
    meta.dateTime = timestamp;
    meta.textContent = new Date(timestamp).toLocaleTimeString(document.documentElement.lang === 'fa' ? 'fa-IR' : 'en-GB', { hour: '2-digit', minute: '2-digit', ...(document.documentElement.lang === 'fa' ? { timeZone: 'Asia/Tehran' } : { timeZone: 'UTC' }) });

    item.append(text, meta);
    list.append(item);
    list.scrollTop = list.scrollHeight;
}

export const inboxThread = defineBehavior<HTMLElement>({
    name: 'inbox-thread',
    selector: '[data-inbox-thread]',
    mount(root) {
        const list = query<HTMLElement>('[data-thread-messages]', HTMLElement, root);
        const form = query<HTMLFormElement>('[data-thread-form]', HTMLFormElement, root);
        const field = query<HTMLTextAreaElement>('[data-thread-input]', HTMLTextAreaElement, root);
        const submit = query<HTMLButtonElement>('[data-thread-submit]', HTMLButtonElement, root);
        const conversationId = numericAttribute(root, 'data-inbox-thread');
        const endpoint = attribute(root, 'data-thread-endpoint');
        const status = query<HTMLElement>('[data-thread-status]', HTMLElement, root);
        const errorMessage = attribute(root, 'data-thread-error') ?? '';
        const readEndpoint = attribute(root, 'data-thread-read');

        if (list === null || form === null || field === null || conversationId === null || endpoint === null) {
            return;
        }

        list.scrollTop = list.scrollHeight;

        for (const quick of queryAll<HTMLButtonElement>('[data-thread-quick]', HTMLButtonElement, root)) {
            quick.addEventListener('click', () => {
                field.value = quick.dataset['threadQuick'] ?? '';
                field.focus();
            });
        }

        const send = (): void => {
            if (field.disabled) return;
            const body = field.value.trim();

            if (body === '') {
                return;
            }

            if (status !== null) setText(status, '');
            field.disabled = true;

            if (submit !== null) {
                submit.disabled = true;
            }

            void post<{ message: unknown }>(endpoint, { message: body }).then((result) => {
                field.disabled = false;

                if (submit !== null) {
                    submit.disabled = false;
                }

                if (!result.ok || !isSentMessage(result.value.message)) {
                    if (status !== null) setText(status, errorMessage);
                    field.focus();
                    return;
                }

                field.value = '';
                appendBubble(list, result.value.message.id, result.value.message.body, result.value.message.created_at, true);
                field.focus();
            });
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            send();
        });

        field.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
                event.preventDefault();
                send();
            }
        });

        subscribeToInbox((message: InboxMessagePayload) => {
            if (message.conversationId !== conversationId) {
                return;
            }

            appendBubble(list, message.id, message.body, message.createdAt, message.outgoing);
            if (!message.outgoing && readEndpoint !== null) {
                void post<{ counts?: { unread_messages_total: number } }>(readEndpoint).then((result) => {
                    if (result.ok && typeof result.value.counts?.unread_messages_total === 'number') {
                        paintUnreadCount(result.value.counts.unread_messages_total);
                    }
                });
            }
        });
    },
});

export const inboxBadge = defineBehavior<HTMLElement>({
    name: 'inbox-badge',
    selector: '[data-inbox-badge]',
    mount(badge) {
        let count = numericAttribute(badge, 'data-inbox-badge') ?? 0;

        subscribeToInbox((message) => {
            count = message.unreadTotal;
            setText(badge, count > 99 ? '99+' : String(count));
            setHidden(badge, count === 0);
        });
    },
});

export const inboxPane = defineBehavior<HTMLElement>({
    name: 'inbox-pane',
    selector: '[data-inbox-pane]',
    mount(pane) {
        const openers = queryAll<HTMLAnchorElement>('[data-thread-open]', HTMLAnchorElement, pane);

        for (const opener of openers) {
            opener.addEventListener('click', () => {
                toggleClass(pane, 'is-thread-open', true);
            });
        }

        const back = query<HTMLButtonElement>('[data-thread-back]', HTMLButtonElement, pane);

        back?.addEventListener('click', () => {
            toggleClass(pane, 'is-thread-open', false);
        });
    },
});
