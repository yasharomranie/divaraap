import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import type { InboxMessagePayload } from '../core/types';

type InboxListener = (payload: InboxMessagePayload) => void;

const listeners = new Set<InboxListener>();
let client: Echo<'reverb'> | null = null;

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

function connect(): void {
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
