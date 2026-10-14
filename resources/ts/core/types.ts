export interface LocationOption {
    readonly id: number;
    readonly name: string;
    readonly display_name?: string;
}

export interface FavoriteToggleResponse {
    readonly favorited: boolean;
    readonly count: number;
}

export interface InboxThreadState {
    readonly id: number;
    readonly unread: number;
    readonly preview: string;
    readonly updatedAt: string;
}

export interface InboxState {
    readonly total: number;
    readonly threads: readonly InboxThreadState[];
}

export interface InboxMessagePayload {
    readonly id: number;
    readonly outgoing: boolean;
    readonly unreadTotal: number;
    readonly conversationId: number;
    readonly body: string;
    readonly senderId: number;
    readonly createdAt: string;
}

export function isLocationOptionList(value: unknown): value is LocationOption[] {
    return (
        Array.isArray(value) &&
        value.every((item: unknown) => {
            if (typeof item !== 'object' || item === null) {
                return false;
            }

            const candidate = item as Record<string, unknown>;

            return typeof candidate['id'] === 'number' && typeof candidate['name'] === 'string' && (candidate['display_name'] === undefined || typeof candidate['display_name'] === 'string');
        })
    );
}
