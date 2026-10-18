# Favorite

Everything a buyer can save for later: favorited listings, favorited sellers, and saved searches.

- Favoriting a listing/seller is a simple pivot toggle, implemented on the `User` model (`toggleFavoriteListing`, `toggleFavoriteSeller`).
- `FavoriteSearch` stores a named, de-duplicated (by signature) saved search with its filters serialized as JSON.
- The favorites index page lists saved listings, sellers, and searches for the current user.

## Buyer alerts

Saved searches, followed sellers and saved listings have optional in-app alerts. Preference routes enforce authentication, active status and source ownership. `/favorites/alerts` manages preferences; it and the existing notification inbox process bounded publication batches for the current user. No worker, scheduler or external delivery provider is required. New publications are recorded by the Favorite provider's Listing saved listener; query-builder bulk updates intentionally do not emit model events. Use the normal listing activation APIs.

BuyerPublication stores one first-active receipt per listing. BuyerAlert owns cursors, price baselines and per-source preferences. BuyerAlertReceipt atomically deduplicates notification delivery. Model queries remain separate across modules, without cross-module SQL joins. Price checks use current values, not historical ticks. The three new tables have soft deletion; publication and delivery unique keys intentionally remain durable across soft deletion.

## Listing comparison

ListingComparison keeps at most three same-category public listings in the existing browser session. ComparisonController exposes the GET screen and CSRF-protected add, remove and clear operations. Listing owns eligibility queries; the existing custom-field formatter optionally returns stable keys for row alignment. No comparison database, login requirement or externally shareable URL is introduced. Every read prunes stale selections. The public page is private/no-store and noindex.

## Recently viewed

ListingHistory stores ID/timestamp pairs in the existing session, bounded to 50 entries and 30 days. ListingController records eligible detail visits independently of its existing view counter. HistoryController provides private/no-store reads, pause/resume and CSRF-protected removal/clear. Identity changes discard previous entries; pause preference remains. Unavailable listings are pruned on access, and no listing titles, searches, IP addresses or user-agent details are stored in history. No database or cross-device synchronization is added.

## Private collections

FavoriteCollection owns per-account folders and transactional ownership/duplicate checks. FavoriteListing keeps the nullable collection reference on the existing pivot and owns moves/restoration. Removing a folder clears references, including soft-deleted favorites, while retaining bookmarks and price alerts. Every move requires an existing live favorite for the same account. CollectionController uses authenticated native forms; collection filters combine with the existing Favorites page. Collection names are private, limited to 60 characters and 50 active folders per user. There are no public share links or additional bookmark tables.
