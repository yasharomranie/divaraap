# Banner advertising

The admin panel exposes Banners and Placements under Banner advertising. Access requires `advertising.manage`; the migration grants it to the existing admin role. Public visitors require no authentication.

Create a banner with a desktop image, optional mobile image, accessible alternative text and optional HTTP(S) destination. JPG, PNG and WebP uploads are limited to 4 MB and stored on the existing public disk. The mobile image falls back to the desktop image. Optional start and end dates use the application timezone in the admin form. The end boundary is exclusive.

Assign a banner to any number of existing placements, with an independent numeric priority in each. Lower numbers appear first; ties use assignment ID. Banner and placement device restrictions both apply. Deleted, inactive, future and expired banners are excluded.

The migration registers nine connected placements: home top, home latest feed, all listings feed, search feed, category feed, listing details, above related listings, content page top and content page bottom. Search takes precedence over category when both filters are present. Content placements apply to published content pages collectively.

For feeds, `first_after` determines the first insertion and `interval` counts listings between subsequent insertions. Defaults are 4 and 6, so positions are 4, 10, 16 and so on. Pagination uses the paginator's global listing offset. With repetition disabled, each eligible banner appears once across the feed. Random ordering uses a stable session seed to preserve pagination continuity. Non-feed placements show the first eligible banner.

No assignments means no public markup or reserved empty slot. Images reserve their aspect ratio to prevent layout shifts. Banner links carry sponsored and safe new-tab relationship attributes. No impression tracking, click tracking, scripts or automatic rotation are added.

A new structural location requires adding its key, registering a placement, and including `advertising::slot` with `placementKey` and an optional global `position`. Existing placement settings and banner assignments need no code changes. The module does not remove existing home sections automatically.

The normal cPanel deployment migrates the three new advertising tables and registers the module and admin resources. Existing uploaded images remain in shared public storage.
