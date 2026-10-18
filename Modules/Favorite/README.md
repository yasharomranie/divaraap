# Favorite

Everything a buyer can save for later: favorited listings, favorited sellers, and saved searches.

- Favoriting a listing/seller is a simple pivot toggle, implemented on the `User` model (`toggleFavoriteListing`, `toggleFavoriteSeller`).
- `FavoriteSearch` is the one real entity this module owns: a named, de-duplicated (by signature) saved search with its filters serialized as JSON.
- The favorites index page lists saved listings, sellers, and searches for the current user.

## Buyer alerts

Saved searches, followed sellers and saved listings have optional in-app alerts. Preference routes enforce authentication, active status and source ownership. `/favorites/alerts` manages preferences; it and the existing notification inbox process bounded publication batches for the current user. No worker, scheduler or external delivery provider is required. New publications are recorded by the Favorite provider's Listing saved listener; query-builder bulk updates intentionally do not emit model events. Use the normal listing activation APIs.

BuyerPublication stores one first-active receipt per listing. BuyerAlert owns cursors, price baselines and per-source preferences. BuyerAlertReceipt atomically deduplicates notification delivery. Model queries remain separate across modules, without cross-module SQL joins. Price checks use current values, not historical ticks. The three new tables have soft deletion; publication and delivery unique keys intentionally remain durable across soft deletion.
