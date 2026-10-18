# Product delivery plan

The approved scope contains 67 features across eight phases. `product-roadmap.json` is the progress ledger; pending entries are not implemented. Phase 1 is the first independently reviewable delivery, not completion of the full roadmap. Validation: 204 PHPUnit tests passed; real-browser staff login, resource isolation, moderation, mobile layout and admin settings checks passed.

## Phase 1

- Staff access: advertising-manager, moderator and support roles use the existing Spatie permission system. Every in-house resource now defaults to full-admin-only unless explicitly mapped. Staff cannot reach user administration, deployment, files, site settings or the global statistics dashboard. Advertising managers can manage banners; moderators use the operations queue rather than unrestricted listing CRUD. All staff enter through the operations page.
- Activity history: existing Spatie activity storage is reused. Banner, assignment, placement, report and review state changes are audited; moderation decisions include the actor, subject and decision. Private message text, upload contents and banner destination URLs are not added to the new audit payloads. The history page is admin-only.
- Review queue: the oldest 20 pending items per authorized domain are displayed. Listing, review, report and banner records remain in their owning modules. Decisions reauthorize and lock the record in a transaction. Concurrent or duplicate decisions are rejected. Rejected listings use an explicit rejected state; owner edit, sold and republish actions lock and recheck current state so stale requests cannot bypass review; existing live banners remain approved by default. Content changed by non-admin advertising staff is paused for review.
- Product metrics: step entries and successful listing submission are deduplicated per session, day, step and filter context. They are session-day counts, not unique people or exact transaction conversion rates. Empty searches include category, country and city context, without cross-module SQL joins. Email/phone-like searches are omitted; raw IP and private messages are not recorded. Analytics can be disabled. Events older than 90 days are removed on subsequent event ingestion; no independent host scheduler is assumed.
- Capacity: current banner placements share one request-scoped budget and a minimum listing gap within each feed. Defaults preserve the existing first-after/interval behavior: maximum 10 slots per response and gap 1. Future sponsored-card renderers must reserve from the same service rather than inventing their own counter. Existing organic/featured listing ranking is unchanged.

## Delivery constraints

Use the normal cPanel release path. Migrations add product metrics and moderation metadata; existing banner images and priorities remain in place. Operations controls appear under the operations navigation group. The existing user resource assigns the new staff roles; no second account or role system is created.

New display formats, charging, buyer tools and seller tools are pending their respective phases. Phase 2 campaign foundations are delivered below; advertising analytics and delivery controls are detailed in the phase 2 deliveries below. Payment providers, notification delivery providers, AI providers and map providers require separate concrete integration choices before claiming live external functionality. No external paid service is enabled in phase 1.

## Phase 2 — campaign foundations

Features 7, 11, 12 and 13 connect to the existing advertising module. Admin and active advertising managers can create campaigns under Advertising → Campaigns, then select the campaign on each existing banner. Campaigns have a shared goal, activation switch, schedule and optional planned budget in IRR. Planned budget is metadata only: no payment, spending calculation or budget-based stop is claimed. A banner can belong to one campaign and a campaign can contain multiple banners. Both campaign and banner schedules must permit display; campaign edits cannot bypass banner review. Soft-deleting or pausing a campaign suppresses its banners without deleting images or assignments. Detaching a banner makes it independent again. Existing independent banners and per-placement priorities remain supported.

Target categories use existing active category IDs, cities use existing Location IDs and page targeting uses exact URL paths without query parameters. Empty targeting lists mean unrestricted. Every nonempty targeting dimension must match. Categories match the selected browse category or listing category exactly; descendants are not inferred. Cities match the explicit browse selection or listing's stored city and country, never guessed visitor geolocation. Unknown or missing context fails closed. Home and content pages have no category/city context, so campaigns with these restrictions do not appear there. Paths are normalized for leading/trailing slashes and do not change placement ordering. The request renderer eager-loads campaigns and shares the existing capacity service; no tracking queries or new scheduler are introduced.

Validation: campaign lifecycle/targeting, actual listing/search integration, Filament create/validation, authorization and all previous feature tests. Browser checks cover Persian RTL campaign and banner forms at 390px and 1440px with no horizontal overflow or JavaScript errors. This delivery adds one migration; the normal cPanel deployment runs it. No manual database or document-root edits are required.

Phase 2 feature 10 now measures conversation starts for internal listing destinations; store and service-request actions await their owning modules. Feature 19 is delivered in the downloadable reports section below. Protected placement previews are delivered below. Opt-in weighted campaign allocation is delivered below. Daily browser frequency caps are delivered below. A saved campaign goal does not imply conversion tracking is already active.

## Phase 2 — advertising measurement

Features 8 and 9 add viewable impression and click measurement to the existing public banner renderer. An impression requires a successfully loaded image, at least 50% intersection and one continuous second while the document is visible. Hidden device variants, failed images and initial page rendering do not count. Trusted primary/keyboard and middle-button link clicks send a nonblocking same-origin keepalive request; the destination stays a direct link. No redirect service or shell command is required. Missing JavaScript, blockers or network errors can undercount. This is browser-reported viewability, not proof of a human viewer or an antifraud certification.

Each served variant receives an encrypted two-hour token bound to its session, banner, campaign snapshot, placement, device variant and targeting context. The event endpoint uses web CSRF protection and rate limiting, checks the token and current banner approval/schedule, campaign targeting, active assignment and placement, and inserts a globally unique receipt per exposure and event kind. Concurrent replays use a database unique constraint, including cross-device replay. An exposure can produce at most one impression and one click. Reloading is a new exposure. Quick clicks can occur before one second, so CTR can exceed 100%; zero-impression CTR is shown as undefined. No impression is fabricated from a click. Campaign reassignment invalidates old exposure tokens; existing events retain their original campaign ID.

Advertising → Advertising statistics is available only to active advertising managers and full admins. It shows the last 30 application-calendar days grouped by banner, placement, device or day, optionally filtered by campaign, with impressions, clicks and CTR. Reports are limited to 100 grouped rows. Persian dates use the existing calendar helper. Counts are exposures, not unique people. No IP, session identifier, raw token, visitor fingerprint or private message is stored in the event table. Old events are removed after 90 days on subsequent ingestion once per day; there is no new scheduler. Disabling telemetry in Operations settings disables both issuance and ingestion. Retention is not a hard scheduled deletion guarantee.

Validation: feature tests cover replay/device/session/expiry handling, schedule/assignment/telemetry controls, attribution, report calculations, retention, validation and role isolation. Real Chromium checks cover offscreen and failed-image exclusion, visible impressions, click delivery, deduplication and Persian mobile/desktop reports. TypeScript/Vite build and localization checks pass. One additive event-table migration runs through the existing cPanel deployment. Downloadable advertiser reports are delivered below; additional conversion types and payment/billing remain pending their roadmap entries.

## Phase 2 — conversation attribution

Feature 10 is partially delivered for the currently available conversation flow. A valid tracked click whose destination matches the configured application origin and an active `/listings/{id}` page can receive attribution. The latest eligible internal-listing click available to the browser is carried in a Laravel-encrypted, HttpOnly, SameSite=Lax, 24-hour cookie containing only its click-event ID and timestamp. HTTPS requests set Secure. This separate cookie avoids overwriting attribution when a keepalive click request overlaps immediate navigation; it also survives login session regeneration. Out-of-order simultaneous clicks in multiple tabs can still undercount or choose the last received cookie. Cookie/network loss and different browsers prevent attribution. An old replay does not replace a newer click already present in the request cookie.

The Conversation module emits ConversationStarted only after a newly created conversation and any supplied first message have succeeded. Advertising listens to that domain event; no cross-module SQL join, message text or user ID enters analytics. Opening an existing conversation, a failed/unauthorized action, another listing, an external link or an expired click does not produce a conversion. A database-unique receipt allows at most one conversion per source click. Attribution uses the click's campaign/placement/device snapshot and remains valid if a campaign is paused after the click. Source and target IDs stay within the advertising event table; the target is a public listing ID. Telemetry opt-out is checked again at conversion time. Listener failures are reported and do not block conversation creation.

The existing statistics page now includes New conversations for each grouping and campaign filter; it does not claim purchases or unique buyers. This is action-day reporting rather than a click cohort conversion rate. One additive migration adds target_listing_id and source_click_id. Existing click records without a destination snapshot cannot be backfilled as conversions. There is no frontend conversion endpoint, new scheduler, payment or document-root change. Store views and service requests remain unimplemented until the respective modules exist, so roadmap entry 10 remains in_progress.

Validation includes actual authenticated conversation requests, existing-thread exclusion, matching destination/window/telemetry rules, latest eligible click, external URL rejection, public payload validation, and analytics failure isolation. Chromium confirmed immediate internal navigation, the HttpOnly attribution cookie, a single stored conversion and Persian reports at 390px/1440px.

## Phase 2 — daily frequency limits

Feature 14 adds nullable daily_frequency_cap to the existing Banner and Campaign models and forms (1–10,000; empty means unlimited). Campaign caps are shared across all member banners, devices and placements; banner and campaign limits must both permit display. Existing priority/random ordering, scheduling, approval and request-wide capacity remain in force. Once a cap is reached, subsequent requests filter that banner before selection, allowing the next eligible banner at a slot. Existing feed slot offsets and no-repeat behavior are preserved. The application-calendar day determines reset; this is not a rolling 24-hour window.

Only successfully inserted viewable impression events increase frequency counts; page render, click, conversion and duplicate exposure receipts do not. Impression insertion and counter increments share one database transaction. Each visitor/scope/subject/day bucket has a unique database constraint, and increments are atomic. The browser receives an encrypted HttpOnly SameSite=Lax 30-day first-party cookie only when limited ads are evaluated. HTTPS sets Secure. Buckets store an HMAC of the random browser value rather than its raw value, IP or account ID. One request loads all current-day counts once. Cookies are browser-specific and survive normal login session rotation; deleting cookies or switching browsers creates a new counter. Event tokens for capped exposures bind to that browser fingerprint. Events and frequency buckets remain separate in the Advertising module without cross-module SQL joins.

These are delivery limits applied to later server responses, not a hard physical visibility guarantee across already loaded tabs. Already issued exposures are still recorded accurately and may take the total above the configured cap; they are not silently discarded to force the report to match the cap. JavaScript, cookie or network blocking can prevent measurement/enforcement. Newly enabled limits start counting subsequent capped exposures and do not reconstruct earlier visitor history. Capped ads are suppressed when operations telemetry is disabled, while independent uncapped ads continue to render without tracking. Old frequency buckets are purged after seven days on later capped-impression ingestion once per day; no independent cron is introduced and retention is not a scheduled-deletion guarantee.

Validation covers render-versus-impression counting, duplicate receipts, browser separation and replay rejection, shared campaign limits, stricter banner limits, next-day reset, telemetry opt-out and Filament validation. Real Chromium checked a loaded/viewable capped banner disappearing on reload in favor of its fallback, independent browser counters, the HttpOnly cookie and Persian forms at 390px/1440px. One additive migration runs during the normal cPanel deploy.

## Phase 2 — campaign share allocation

Feature 15 adds delivery_weight (1–1,000, default 1) to the existing Campaign model. Existing placements keep their current priority/random mode. To enable weighting, edit a placement and select Campaign shares. No global switch silently changes delivery. The campaign form and table expose its weight; placement table exposes ordering. Both use existing advertising.manage authorization and activity logging.

Eligibility, moderation, campaign/banner schedules, targeting, placement and banner device constraints, daily browser frequency limits and page capacity remain authoritative. Allocation only sees eligible assignments. Each campaign is one weighted group regardless of its number of creatives; each independent banner is its own group with weight 1. At each virtual feed opportunity, a deterministic session/day/placement seed selects an eligible group in proportion to its weight. A weight of 3 against 1 means about 75% of selections across many independent seeds while both groups remain available. This is selection probability, not a guaranteed impression, click, budget, reservation or paid-inventory percentage. Static placements keep their selection within the same session/day/pool rather than changing on every refresh.

Within a selected group, creatives follow assignment priority then assignment ID. With repeat enabled they cycle in that order. With repeat disabled each creative appears once in the virtual sequence; exhausted groups leave the pool and remaining weights renormalize. Thus complete nonrepeating feeds remain limited by inventory and their aggregate creative counts are not a promised campaign percentage. More creatives do not inflate initial group weight. Eligibility changes can alter the virtual sequence. The existing absolute insertion offset is used across pagination, and matching device pools receive the same selection. There are no extra database writes or new client assets for selection; the allocator uses bounded memory and reconstructs the prefix when reading a later virtual slot.

Validation: 2,000-seed tests for equal campaign weights despite 8-versus-1 creatives, 3:1 weights, independent-banner unit weight, priority rotation, no-repeat exhaustion and deterministic pagination reconstruction. Renderer tests cover inactive campaigns and device fallback; all previous priority/random/cap tests still pass. Filament validates weight limits and saves the explicit placement mode. Browser checks cover Persian RTL fields at 390px and 1440px. One additive campaign-column migration runs through normal cPanel deployment.

## Phase 2 — automatic delivery stops

Feature 16 adds optional total impression_limit, click_limit and cost_limit to campaigns. Any reached limit stops the campaign in subsequent delivery requests; all its banners inherit the stop, regardless of placement, weight or creative count. Existing manual pause, start/end dates, targeting and moderation retain precedence. When a limited campaign is excluded, eligible independent or other-campaign banners remain available. Raising a limit can automatically permit delivery again without clearing history or toggling banner activation. Limited campaigns fail closed when operations telemetry is disabled; unlimited campaigns retain previous behavior. The campaign table displays the reason for a delivery stop alongside totals.

The event insertion transaction updates persistent recorded_impressions/recorded_clicks and recorded_cost under a campaign row lock only after a new receipt is inserted. Replays do not add totals, and conversion events are not billed. Counters survive raw-event retention. The additive migration initializes counts from retained, nondeleted historical impression/click records; earlier purged history cannot be reconstructed and historical estimated cost starts at zero. Totals thereafter persist for the life of the campaign and do not reset daily. Automatic counters use saveQuietly to avoid flooding the administrative activity log, while limit/rate edits are audited.

Cost limits are internal estimates in IRR, separate from planned_budget and from future paid billing. Managers configure cost per single viewable impression and per click, both initially zero. The encrypted exposure token snapshots rates at serving time; changing rates affects future exposures. No payment provider, invoice or monetary deduction is introduced. Delivery stops at the cost limit or when the balance cannot cover the larger of the current impression/click rates. With zero rates, no cost accrues. The planning-budget field does not control delivery.

Already served valid exposures can still record actual impressions/clicks and their snapshotted costs after an automatic stop; these may exceed limits. This is explicitly a subsequent-delivery control, not a strict financial reservation or physical visibility guarantee across in-flight requests/tabs. Those events bypass only the automatic limits, not manual pause, schedules, assignment checks, token validity or consent. The original click attribution pipeline remains in force. Private visitor details and message text are not added to counters.

Validation covers threshold boundaries, replay deduplication, in-flight events, price snapshots, cost balance, automatic resumption, history retention, independent fallback, telemetry opt-out, scheduling and form bounds. Real Chromium confirmed an actual viewable impression stopping a campaign, independent fallback on reload, stop reason in the admin table and Persian forms at 390px/1440px. The single migration runs through the existing cPanel release path; no new cron or manual database work is needed.

## Phase 2 — protected banner and placement preview

Feature 17 adds Advertising → Advertising preview, plus Preview links on saved banner rows and edit pages. Active advertising managers and full admins can choose a saved banner, a supported placement and a 390px mobile or 1200px desktop iframe viewport. The page clearly labels the viewport dimensions and provides keyboard-focusable internal scrolling without overflowing the admin page. Unsaved form edits are not previewed. Draft, paused and unapproved creatives can be inspected without making them public; deleted records are excluded. Device incompatibility produces an explicit warning.

Public slots and previews share the same creative.blade.php markup, image source selection, alt escaping and production advertising styles. The preview loads the saved AppearanceTheme variables and Persian font, using a CSS-only Vite entry, with no application tracking JavaScript. The iframe is sandboxed with allow-same-origin only, allowing same-origin fonts while blocking scripts, forms, popups and navigation; destination anchors are omitted. The authenticated render route checks advertising.manage and active status before loading data, validates placement/device inputs, and sets private/no-store, SAMEORIGIN and noindex/nofollow headers. No public query flag disables tracking on real advertising slots.

The renderer does not call BannerRenderer, issue exposure tokens, reserve advertising capacity, generate a frequency cookie or mutate campaign totals. It only invokes the shared creative template with trackingToken=null and isPreview=true. Generic sample listing cards illustrate feed context, show the configured first-after value and cap placeholder cards at six. This is a clearly labeled sample placement, not a complete reproduction of the site's live page, a simulation of targeting, or publication approval. The actual site continues to apply moderation, schedules, targeting, caps, weights and allocation.

Validation covers draft rendering, no trackers/links/product scripts, unchanged stopped-campaign totals and frequency rows, device warnings, bad inputs, deleted records, active-role authorization and HTML escaping. Chromium confirmed responsive Persian controls, true iframe dimensions, mobile/desktop currentSrc and real image aspect ratios, sandboxing and zero advertising event requests. CSS/Vite build, localization and existing public renderer tests pass. This delivery adds no database migration.

## Phase 2 — advertising health warnings

Feature 18 adds Advertising → Advertising warnings using the existing advertising.manage permission. Opening the page or choosing Check again runs a check for the current active admin/advertising manager. No background timer, independent cron, email or SMS delivery is assumed. The page shows its last successful check and errors instead of claiming an incomplete scan is healthy. The dashboard displays up to 100 open warnings per viewer with edit links; results describe the last scan, not a continuously monitored remote service.

Checks cover missing/inaccessible main images, configured mobile images, invalid nonempty HTTP(S) destination URLs, enabled campaigns ending within 24 hours and enabled campaigns already ended. A missing optional mobile image uses the existing desktop fallback; an empty optional link is valid. Active approved unexpired banners are inspected, with manually disabled/deleted campaigns and banners omitted; drafts and rejected creatives do not spam managers. Storage uses the existing public disk. URL validity follows Banner::destination; no arbitrary target URL is fetched, no remote availability or redirect/TLS check is claimed. The scan never disables ads, changes media, resets counters or bypasses review.

A soft-deletable advertising_warnings ledger holds recipient, source, issue code, timestamps and resolution state. It is not a second notification inbox: delivery uses Notification::UserNotification::publish and the existing unread/read UI. A unique recipient/source/code fingerprint and row lock create one notification per unresolved episode, with transaction retries for concurrent checks. Reading/deleting a notification does not resubmit the warning. When the issue disappears on a subsequent scan it is closed; if it recurs, the same ledger row is reopened and a new notification is generated once. End-soon and ended are separate states. Warnings are per authorized viewer; scanning one account does not notify all staff. No private destination URL, upload path, image contents or token is copied into notification bodies.

Source queries stay in Banner and Campaign, notification writes stay behind the Notification model API, and no cross-module SQL join is used. The new advertising notification type is translated and filterable in the existing notification resource. Diagnostics and notification writes share a database transaction, so a failed check does not leave partially published episodes. Existing inbox retention remains unchanged; warning state is retained for deduplication rather than relying on a cache TTL.

Validation covers duplicate checks, read notifications, resolution and recurrence, optional media/link behavior, deadline transition, ignored inactive/deleted subjects, separate authorized viewers, access denial and escaping. Chromium verified Persian controls and warning cards at 390px/1440px, safe edit links, retry feedback and no destination-secret disclosure. The additive warning-table migration runs with the normal cPanel deployment.

## Progress

| ID | Phase | Feature | Status |
|---|---|---|---|
| 1 | 1 | نقش‌های مستقل مدیریت | verified |
| 2 | 1 | سابقهٔ اقدامات | verified |
| 3 | 1 | صف یکپارچهٔ بررسی | verified |
| 4 | 1 | تحلیل مراحل ثبت آگهی | verified |
| 5 | 1 | گزارش جست‌وجوی بدون نتیجه | verified |
| 6 | 1 | کنترل ظرفیت تبلیغات | verified |
| 7 | 2 | مدیریت کمپین | verified |
| 8 | 2 | آمار نمایش واقعی | verified |
| 9 | 2 | آمار کلیک و نرخ کلیک | verified |
| 10 | 2 | سنجش اقدام پس از کلیک | in_progress |
| 11 | 2 | هدف‌گیری دسته‌بندی | verified |
| 12 | 2 | هدف‌گیری شهر | verified |
| 13 | 2 | هدف‌گیری صفحهٔ مشخص | verified |
| 14 | 2 | محدودیت دفعات نمایش | verified |
| 15 | 2 | سهم نمایش کمپین | verified |
| 16 | 2 | توقف خودکار کمپین | verified |
| 17 | 2 | پیش‌نمایش جایگاه | verified |
| 18 | 2 | هشدار مشکلات کمپین | verified |
| 19 | 2 | گزارش تبلیغ‌دهنده | verified |
| 20 | 3 | هشدار آگهی دلخواه | verified |
| 21 | 3 | دنبال‌کردن فروشنده | verified |
| 22 | 3 | مقایسهٔ آگهی‌ها | verified |
| 23 | 3 | هشدار کاهش قیمت | verified |
| 24 | 3 | سابقهٔ مشاهده | verified |
| 25 | 3 | مجموعه‌های شخصی | verified |
| 26 | 3 | جست‌وجوی بهتر فارسی | pending |
| 27 | 3 | جست‌وجو روی نقشه | pending |
| 28 | 3 | پیشنهادهای مرتبط | pending |
| 29 | 4 | داشبورد عملکرد آگهی | pending |
| 30 | 4 | راهنمای بهبود آگهی | pending |
| 31 | 4 | پیشنهاد عنوان و توضیح | pending |
| 32 | 4 | پیش‌نویس ماندگار | pending |
| 33 | 4 | پاسخ‌های آماده | pending |
| 34 | 4 | وضعیت موقت آگهی | pending |
| 35 | 4 | ساخت آگهی از آگهی قبلی | pending |
| 36 | 4 | زمان‌بندی انتشار آگهی | pending |
| 37 | 4 | صفحهٔ فروشگاه | pending |
| 38 | 4 | مدیریت گروهی آگهی‌ها | pending |
| 39 | 5 | نشان‌های تأیید | pending |
| 40 | 5 | زمان پاسخ‌گویی فروشنده | pending |
| 41 | 5 | نظر معتبرتر | pending |
| 42 | 5 | گزارش و مسدودسازی در گفتگو | pending |
| 43 | 5 | پیشنهاد قیمت داخل چت | pending |
| 44 | 5 | هماهنگی بازدید | pending |
| 45 | 5 | چک‌لیست معاملهٔ هر دسته | pending |
| 46 | 5 | تشخیص آگهی مشکوک و تکراری | pending |
| 47 | 6 | کارت فروشگاه محلی | pending |
| 48 | 6 | مجموعهٔ آگهی تبلیغ‌شده | pending |
| 49 | 6 | کارت درخواست خدمات | pending |
| 50 | 6 | بنر تعاملی با فیلتر آماده | pending |
| 51 | 6 | حمایت از یک دسته | pending |
| 52 | 6 | پیشنهاد دارای مهلت واقعی | pending |
| 53 | 6 | تبلیغ ویدئویی اختیاری | pending |
| 54 | 6 | کارت محتوای راهنما | pending |
| 55 | 7 | پرداخت و صورتحساب | pending |
| 56 | 7 | پنل سفارش تبلیغات | pending |
| 57 | 7 | تقویم رزرو جایگاه | pending |
| 58 | 7 | بستهٔ فروشندگان حرفه‌ای | pending |
| 59 | 7 | بستهٔ ترکیبی تبلیغات | pending |
| 60 | 7 | آزمایش دو طرح تبلیغ | pending |
| 61 | 8 | دنبال این می‌گردم | pending |
| 62 | 8 | به من خبر بده برای کالای کمیاب | pending |
| 63 | 8 | درخواست خدمات محلی | pending |
| 64 | 8 | پروندهٔ مقایسهٔ قابل اشتراک | pending |
| 65 | 8 | راهنمای قیمت | pending |
| 66 | 8 | فروشگاه‌های منتخب محله | pending |
| 67 | 8 | اشتراک فهرست انتخاب‌ها | pending |

## Phase 2 — downloadable reports and integrated acceptance

Feature 19 adds a protected UTF-8 CSV download to Advertising statistics. The table and download share campaign, grouping and application-calendar date filters, defaulting to 30 days within the retained 90-day window. Archived campaigns and deleted banner names remain reportable. The screen shows up to 100 grouped rows; streaming CSV includes every matching group in bounded batches. Dates in inputs and CSV are Gregorian ISO dates; numeric cells remain Excel-compatible. Formula-like text cells are escaped. No visitor IDs, private messages or tokens are exported. Campaign lifetime counters can differ from period reports. This report is not a billing invoice.

Integrated acceptance: 261 PHPUnit tests / 3059 assertions passed, production asset build passed, and real Chromium checks passed in Persian at 390px and 1440px. Checks covered all six advertising management pages, actual filtered CSV downloads and totals, preview isolation, and advertising-manager access with user/deployment administration denied. No migration is added by this report delivery. Feature 10 remains partial: new conversations after internal listing clicks are supported; store and service-request conversions await those modules.

Deployment and module review: use cPanel Update from Remote, then Deploy HEAD Commit after the release workflow succeeds. Keep the existing document root and installed data. Review Campaigns, Banners, Banner placements, Advertising statistics (including CSV), Advertising preview and Advertising warnings. Warning scans run on page entry/manual checks, not continuously in the background. Display weights express relative probabilities; limits affect subsequent responses and already served exposures may overshoot. Review this module as a whole before starting the next module.

## Phase 3 — buyer alerts module

Features 20, 21 and 23 extend existing saved searches, followed sellers and saved listings. Each source has an explicit opt-in control in Favorites; My alerts manages the same preferences. No duplicate favorite/follow system is introduced. Unfollowing, unsaving or deleting the source disables its alert. Controls validate source ownership, require an active authenticated user, and cap enabled subscriptions at 20 per user. Repeated enable requests preserve pending work. Re-enabling starts from the current publication cursor and price baseline.

A durable publication ledger records each listing's first active Eloquent save after installation, including pending-to-active approval. No old listing backlog is imported. Ordinary edits and repeated publication do not produce another new-listing notification. Search alerts use the same category descendants, search matching, country/city, seller and IRR price bounds as browse results; saved-search links now preserve these filters and use the actual search_term field. Deleted categories/locations fail closed. Only currently active, unexpired listings from other users can notify. Overlapping searches deduplicate per recipient/listing. Seller and search alerts can each notify for the same listing because they are different opt-in reasons.

Notifications are generated when the user opens the notification inbox or My alerts, with at most 50 new publication records per subscription per visit. Reopen to continue a larger backlog. There is no cron, queue worker, email, SMS or push dependency and no claim of background real-time delivery. Publication and delivery receipts persist; per-alert locks and transactions make repeated inbox visits idempotent. Price alerts compare the current active listing against the lowest observed price since activation using decimal arithmetic; currency changes reset the baseline. Intermediate price changes while the user is absent are not price-history events. Inactive, expired or deleted listings cannot expose content through these notifications.

One additive migration creates publication, preference and receipt tables; the standard cPanel release runs it. Existing favorites, account settings, public design and advertising behavior remain. Comparison is delivered below; viewing history is delivered below; collections are delivered below; improved Persian search, maps and recommendations remain pending; this delivery completes the buyer-alert module, not all of phase 3.

Validation: 271 PHPUnit tests / 3113 assertions passed, including 10 buyer-alert tests; browser checks exercised all three opt-in controls, real notification generation and Persian layouts at 390px and 1440px. Production assets and localization checks passed.

## Phase 3 — listing comparison module

Feature 22 adds guest and signed-in comparison through the shared listing card, listing detail and Favorites. Users explicitly add up to three listings from the same exact category. Add is idempotent; remove and clear are POST/DELETE CSRF-protected actions. Selected IDs stay in the existing browser session, without database tables, account synchronization or share links. Comparison URLs do not accept IDs from query parameters. A separate session starts empty.

Every comparison request rechecks active status, expiration and soft deletion, preserves selection order and prunes unavailable or category-incompatible entries. Only public listing values are displayed. Price labels preserve original currencies without conversion; Iranian location labels follow the existing localization. Custom fields use the existing public display formatter with stable field keys, so matching labels do not merge unrelated fields. Missing values display a dash. Only-differences filtering compares the rendered values and stays off until there are at least two entries. A completely equal comparison has an explicit empty-differences message.

The RTL table scrolls inside a keyboard-focusable, labeled region on mobile; page width remains bounded. Markup escapes titles, labels and values. Responses are private/no-store and noindex. This delivery adds no migration or third-party service. The existing cPanel Update from Remote / Deploy HEAD Commit flow builds the new styles. Future shareable comparison dossiers remain separate roadmap feature 64.

Validation: 277 PHPUnit tests / 3159 assertions passed, including six comparison tests. Real Chromium exercised guest card/detail selection, limit errors, same-session persistence, separate-session isolation, remove/clear and differences filtering at 390px and 1440px. Production asset build, route/config caching and localization checks passed.

## Phase 3 — recently viewed listings

Feature 24 adds `/history` and entry links from browse results and Favorites. A successful detail visit records only a listing ID and last-viewed timestamp in the existing browser session. Repeated visits move the listing to the front without duplicates. The history is bounded to 50 entries from the past 30 days and displays 12 per page. Session expiration can end availability sooner; there is no background deletion job or cross-device synchronization. The existing view counter keeps its previous behavior and is never incremented by rendering history.

Pause/resume, idempotent remove and clear are CSRF-protected native forms. Pausing preserves previous entries; clearing does not resume recording. The preference lasts for the browser session. An authentication identity change clears the entry list while preserving the pause preference. Owner visits and inactive-account visits are not recorded. Every read rechecks public active status, expiration and soft deletion and discards unavailable entries. Queries stay in Listing; history does not introduce cross-module joins or new database tables.

The Persian mobile-first list uses existing site tokens, localized dates with explicit application timezone, lazy thumbnails, accessible controls and escaped titles. Responses are private/no-store and noindex. No migration, worker, cron or external service is required. Use the normal cPanel release flow; no manual host changes are needed.

Validation: 283 PHPUnit tests / 3256 assertions passed, including six history tests. Real Chromium verified detail visits, recent ordering, pause/resume, removal/clear, independent browser sessions and account-switch isolation at 390px and 1440px. Production assets, localization, config cache and route cache passed.

## Phase 3 — private saved-listing collections

Feature 25 extends the existing favorites pivot with a nullable collection ID. Signed-in active users can create up to 50 private named collections, rename them, move saved listings between collections or Unfiled, and filter Favorites by collection together with existing category/status filters. Each saved listing belongs to at most one collection. There is no parallel bookmark store, public sharing or automatic conversion of viewed listings into favorites. The account owns collections across sessions; guest access requires login.

User-row transactions serialize create, rename, delete and move operations. Ownership is checked for both collection and saved-listing membership. Duplicate trimmed case-folded names are rejected per user using a signature and the existing PostgreSQL/MySQL-compatible active-record uniqueness helper. Deleted names can be reused. Removing a collection soft-deletes it and clears references on active and soft-deleted favorite pivots without removing saved listings or disabling price alerts. Deleted/foreign collections and unsaved/other-user listings cannot be assigned. Inactive users cannot mutate collections.

The normal favorite save flow now restores a previously soft-deleted favorite instead of violating its existing unique key. Restored favorites start unfiled with a new saved timestamp; remembering an already active favorite preserves its collection. Collection move/delete leaves the original saved timestamp and alert preference unchanged. Existing favorite category/status and one-year display filters remain in force. Collection filters reuse the existing favorites relation without introducing new cross-module SQL joins.

Mobile controls use existing tokens, labeled native inputs/selects, a wrapped utility navigation, preserved invalid rename input, actionable empty-folder states and a two-step native delete disclosure. Collection and Favorites responses are private/no-store; collection management is noindex. One additive migration creates favorite_collections and its nullable pivot reference; standard cPanel deployment runs it without manual SQL or host configuration.

Validation: 289 PHPUnit tests / 3316 assertions passed, including six collection tests; 16 collection/buyer-alert tests passed again after final form/navigation adjustments. Chromium exercised create, move, filtering, rename, confirmed delete, preserved bookmarks and auth denial at 390px and 1440px. Asset build, localization and production config/route caching passed.
