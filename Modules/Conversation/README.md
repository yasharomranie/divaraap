# Conversation

Realtime buyer/seller messaging tied to a listing.

- `Conversation` (one per listing/buyer pair) and `ConversationMessage` models hold the inbox and read-state logic.
- Messages broadcast over Laravel Reverb/Echo on a private `users.{id}.inbox` channel via the `InboxMessageCreated` and `ConversationReadUpdated` events.
- The inbox UI (list pane + thread pane) lives under `resources/views`, backed by `resources/ts/modules/inbox.ts` and the shared realtime subscription.

A conversation is started from a listing's detail page and always links back to the `Listing` and `User` models by relationship, never by cross-module query.


Personal saved replies are managed at `/panel/saved-replies`, linked from the inbox. Each account owns its replies; `SavedReply` handles scoped database mutations, ordering, capacity and stale-version checks. Replies fill the composer for review and never send automatically. The existing generic quick-message catalog remains available. The `saved_replies` migration is loaded through `ConversationServiceProvider`.
