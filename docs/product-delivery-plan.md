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

New display formats, charging, buyer tools and seller tools are pending their respective phases. Phase 2 campaign foundations are delivered below; advertising analytics and delivery controls remain pending. Payment providers, notification delivery providers, AI providers and map providers require separate concrete integration choices before claiming live external functionality. No external paid service is enabled in phase 1.

## Phase 2 — campaign foundations

Features 7, 11, 12 and 13 connect to the existing advertising module. Admin and active advertising managers can create campaigns under Advertising → Campaigns, then select the campaign on each existing banner. Campaigns have a shared goal, activation switch, schedule and optional planned budget in IRR. Planned budget is metadata only: no payment, spending calculation or budget-based stop is claimed. A banner can belong to one campaign and a campaign can contain multiple banners. Both campaign and banner schedules must permit display; campaign edits cannot bypass banner review. Soft-deleting or pausing a campaign suppresses its banners without deleting images or assignments. Detaching a banner makes it independent again. Existing independent banners and per-placement priorities remain supported.

Target categories use existing active category IDs, cities use existing Location IDs and page targeting uses exact URL paths without query parameters. Empty targeting lists mean unrestricted. Every nonempty targeting dimension must match. Categories match the selected browse category or listing category exactly; descendants are not inferred. Cities match the explicit browse selection or listing's stored city and country, never guessed visitor geolocation. Unknown or missing context fails closed. Home and content pages have no category/city context, so campaigns with these restrictions do not appear there. Paths are normalized for leading/trailing slashes and do not change placement ordering. The request renderer eager-loads campaigns and shares the existing capacity service; no tracking queries or new scheduler are introduced.

Validation: campaign lifecycle/targeting, actual listing/search integration, Filament create/validation, authorization and all previous feature tests. Browser checks cover Persian RTL campaign and banner forms at 390px and 1440px with no horizontal overflow or JavaScript errors. This delivery adds one migration; the normal cPanel deployment runs it. No manual database or document-root edits are required.

Phase 2 features 10 and 14–19 remain pending: conversions, frequency caps, campaign shares, limit-based stop, previews, warnings and downloadable reports. A saved campaign goal does not imply conversion tracking is already active.

## Phase 2 — advertising measurement

Features 8 and 9 add viewable impression and click measurement to the existing public banner renderer. An impression requires a successfully loaded image, at least 50% intersection and one continuous second while the document is visible. Hidden device variants, failed images and initial page rendering do not count. Trusted primary/keyboard and middle-button link clicks send a nonblocking same-origin keepalive request; the destination stays a direct link. No redirect service or shell command is required. Missing JavaScript, blockers or network errors can undercount. This is browser-reported viewability, not proof of a human viewer or an antifraud certification.

Each served variant receives an encrypted two-hour token bound to its session, banner, campaign snapshot, placement, device variant and targeting context. The event endpoint uses web CSRF protection and rate limiting, checks the token and current banner approval/schedule, campaign targeting, active assignment and placement, and inserts a globally unique receipt per exposure and event kind. Concurrent replays use a database unique constraint, including cross-device replay. An exposure can produce at most one impression and one click. Reloading is a new exposure. Quick clicks can occur before one second, so CTR can exceed 100%; zero-impression CTR is shown as undefined. No impression is fabricated from a click. Campaign reassignment invalidates old exposure tokens; existing events retain their original campaign ID.

Advertising → Advertising statistics is available only to active advertising managers and full admins. It shows the last 30 application-calendar days grouped by banner, placement, device or day, optionally filtered by campaign, with impressions, clicks and CTR. Reports are limited to 100 grouped rows. Persian dates use the existing calendar helper. Counts are exposures, not unique people. No IP, session identifier, raw token, visitor fingerprint or private message is stored in the event table. Old events are removed after 90 days on subsequent ingestion once per day; there is no new scheduler. Disabling telemetry in Operations settings disables both issuance and ingestion. Retention is not a hard scheduled deletion guarantee.

Validation: feature tests cover replay/device/session/expiry handling, schedule/assignment/telemetry controls, attribution, report calculations, retention, validation and role isolation. Real Chromium checks cover offscreen and failed-image exclusion, visible impressions, click delivery, deduplication and Persian mobile/desktop reports. TypeScript/Vite build and localization checks pass. One additive event-table migration runs through the existing cPanel deployment. Downloadable advertiser reports, conversion attribution, caps, allocation, budgets and warnings remain pending their roadmap entries.

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
| 10 | 2 | سنجش اقدام پس از کلیک | pending |
| 11 | 2 | هدف‌گیری دسته‌بندی | verified |
| 12 | 2 | هدف‌گیری شهر | verified |
| 13 | 2 | هدف‌گیری صفحهٔ مشخص | verified |
| 14 | 2 | محدودیت دفعات نمایش | pending |
| 15 | 2 | سهم نمایش کمپین | pending |
| 16 | 2 | توقف خودکار کمپین | pending |
| 17 | 2 | پیش‌نمایش جایگاه | pending |
| 18 | 2 | هشدار مشکلات کمپین | pending |
| 19 | 2 | گزارش تبلیغ‌دهنده | pending |
| 20 | 3 | هشدار آگهی دلخواه | pending |
| 21 | 3 | دنبال‌کردن فروشنده | pending |
| 22 | 3 | مقایسهٔ آگهی‌ها | pending |
| 23 | 3 | هشدار کاهش قیمت | pending |
| 24 | 3 | سابقهٔ مشاهده | pending |
| 25 | 3 | مجموعه‌های شخصی | pending |
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
