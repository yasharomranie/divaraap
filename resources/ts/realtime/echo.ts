import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { InboxMessagePayload } from '../core/types';

type InboxListener = (payload: InboxMessagePayload) => void;

const listeners = new Set<InboxListener>();
let client: Echo<'reverb'> | null = null;
let polling = false;

function parseInboxPayload(value: unknown): InboxMessagePayload | null {
    if (typeof value !== 'object' || value === null) return null;
    const payload = value as Record<string, unknown>;
    const conversation = payload['conversation'] as Record<string, unknown> | undefined;
    const message = payload['message'] as Record<string, unknown> | undefined;
    const counts = payload['counts'] as Record<string, unknown> | undefined;
    if (conversation === undefined || message === undefined || counts === undefined) return null;
    if (typeof conversation['id'] !== 'number' || typeof message['id'] !== 'number' ||
        typeof message['body'] !== 'string' || typeof message['sender_id'] !== 'number' ||
        typeof message['created_at'] !== 'string' || typeof counts['unread_messages_total'] !== 'number') return null;
    return {
        id: message['id'], conversationId: conversation['id'], body: message['body'],
        senderId: message['sender_id'], createdAt: message['created_at'],
        outgoing: message['is_mine'] === true, unreadTotal: counts['unread_messages_total'],
    };
}

function startPolling(endpoint: string): void {
    if (polling) return;
    polling = true;
    const thread = document.querySelector<HTMLElement>('[data-inbox-thread]');
    const conversation = thread?.dataset['inboxThread'];
    let cursor = Math.max(0, ...Array.from(document.querySelectorAll<HTMLElement>('[data-message-id]'),
        (element) => Number(element.dataset['messageId'] ?? 0)));
    const interval = Math.max(5000, Number(document.body.dataset['inboxPollInterval'] ?? 5000));
    const tick = async (): Promise<void> => {
        try {
            if (document.hidden) return;
            const url = new URL(endpoint, window.location.origin);
            if (conversation !== undefined) {
                url.searchParams.set('conversation', conversation);
                url.searchParams.set('after', String(cursor));
            }
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const value = await response.json() as { messages?: unknown[]; unread_total?: number };
            if (typeof value.unread_total === 'number') {
                for (const badge of document.querySelectorAll<HTMLElement>('[data-inbox-badge]')) {
                    badge.textContent = value.unread_total > 99 ? '99+' : String(value.unread_total);
                    badge.hidden = value.unread_total === 0;
                }
            }
            for (const message of value.messages ?? []) {
                const payload = parseInboxPayload(message);
                if (payload === null) continue;
                cursor = Math.max(cursor, payload.id);
                for (const listener of listeners) listener(payload);
            }
        } catch {
            return;
        } finally {
            window.setTimeout(() => { void tick(); }, interval);
        }
    };
    void tick();
}

function connect(): void {
    const endpoint = document.body.dataset['inboxPollUrl'];
    if (endpoint !== undefined && endpoint !== '') {
        startPolling(endpoint);
        return;
    }
    if (client !== null) return;
    const data = document.body.dataset;
    const channel = data['inboxChannel'];
    const key = data['reverbKey'];
    if (channel === undefined || channel === '' || key === undefined || key === '') return;
    const secure = data['reverbScheme'] === 'https';
    const port = Number(data['reverbPort'] ?? (secure ? '443' : '80'));
    client = new Echo({
        broadcaster: 'reverb', key, client: new Pusher(key, {
            cluster: '', wsHost: data['reverbHost'] ?? window.location.hostname,
            wsPort: port, wssPort: port, forceTLS: secure, enabledTransports: ['ws', 'wss'],
            disableStats: true,
            channelAuthorization: {
                endpoint: '/broadcasting/auth', transport: 'ajax',
                headers: { 'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '' },
            },
        }),
    });
    client.private(channel).listen('.inbox.message.created', (value: unknown) => {
        const payload = parseInboxPayload(value);
        if (payload === null) return;
        for (const listener of listeners) listener(payload);
    });
}

export function inboxSocketId(): string | undefined {
    return client?.socketId();
}

export function subscribeToInbox(listener: InboxListener): () => void {
    listeners.add(listener);
    connect();
    return (): void => { listeners.delete(listener); };
}
