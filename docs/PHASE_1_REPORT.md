# گزارش فاز ۱ — خط مبنای اجرایی

وضعیت: مانع StateFusion با اصلاح کد برطرف شد و فاز ۱ در محیط فعلی تکمیل است. برنامه، صف و Reverb اجرا و بررسی شدند؛ فارسی‌سازی فاز ۲ هنوز آغاز نشده است.

## نتیجهٔ بررسی‌ها

| بررسی | نتیجه |
|---|---|
| تحلیل ساختار با Graphify | انجام شد؛ مسیر تست‌ها، seed و وابستگی StateFusion بررسی شد |
| نصب وابستگی‌های npm | موفق |
| npm run type-check | موفق |
| npm run lint | موفق |
| npm run build | موفق؛ Vite 8.3.4، ۱۸ ماژول، manifest و فایل‌های CSS/JS تولید شدند |
| PHP 8.4 و extensionها | تصویر Docker ساخته شد؛ PHP 8.4.26 و Composer 2.10.3 اجرا شدند |
| بررسی syntax کد PHP | ۳۷۵ فایل در app، Modules، config، database، routes و tests بدون خطای syntax |
| PostgreSQL | نسخهٔ 16.15 اجرا شد؛ دیتابیس‌های openclassify و openclassify_testing ساخته شدند |
| درخواست واقعی به دیتابیس تست | ایجاد جدول موقت، درج JSONB و خواندن موفق؛ transaction برگشت داده شد |
| نصب کامل Composer | موفق؛ ۲۴۰ بستهٔ حل‌شده، بررسی platform requirements موفق |
| migration و seed برنامه | موفق در دیتابیس‌های مستقل توسعه و تست؛ ۱۲۰ آگهی و ۵ کاربر نمونه در توسعه |
| آزمون‌های Feature | ۴۶ تست و ۸۴ assertion موفق، exit code صفر؛ ۳۹ تست موجود و ۷ تست جدید وضعیت |
| وب، worker و Reverb | پاسخ ۲۰۰ و HTML برنامه در خانه، آگهی‌ها و ورود؛ handshake، subscription و تحویل broadcast موفق؛ job محلی log-only توسط worker پردازش شد |

## مانع قبلی و اصلاح انجام‌شده

نسخهٔ upstream به `a909m/filament-statefusion` با constraint `^2.3` وابسته بود. نسخهٔ حل‌شده `v2.3.0` بود. اطلاعات رسمی Packagist به این سورس اشاره می‌کرد:

- مخزن: `https://github.com/A909M/Filament-StateFusion`
- commit: `ad8904426012c51255670bcd30520ca031bb71a8`

Git از همین مخزن پاسخ Repository not found گرفت. صفحهٔ GitHub و آرشیو همان commit در codeload هم HTTP 404 برگرداندند. این شواهد نشان می‌دهد نسخه در این محیط قابل دریافت نیست؛ از آن‌ها نمی‌توان خصوصی‌شدن یا حذف‌شدن مخزن را قطعی نتیجه گرفت.

اثر در کد با Graphify پیدا و با سورس تأیید شد:

- `Modules/Listing/States/ListingStatus.php`: interface و trait بسته.
- `Modules/User/App/States/UserStatus.php`: interface و trait بسته.
- `Modules/Admin/Providers/AdminPanelProvider.php`: plugin بسته.

با درخواست کاربر برای رفع مانع، dependency حذف و اتصال آن با Select، SelectColumn و SelectFilter استاندارد Filament جایگزین شد. مدل‌های وضعیت Spatie و transitionها حفظ شدند؛ helperهای کوچک در app/Support/Filament تبدیل State به مقدار فرم و تغییر وضعیت جدول را انجام می‌دهند. ویرایش inline از مجوز canEdit هر Resource تبعیت می‌کند. تست‌های Livewire، hydration و ذخیرهٔ فرم کاربر/آگهی، event تغییر وضعیت، فیلتر وضعیت و رد مقدار نامعتبر در فرم و جدول را پوشش می‌دهند. بستهٔ ساختگی یا namespace جایگزین StateFusion ایجاد نشد.

نصب اولیهٔ آرشیوهای Composer نیز در api.github.com با CONNECT 403 مواجه شد. مسیر استاندارد `--prefer-source` برای دیگر بسته‌ها استفاده شد. Git داخل کانتینر ابتدا trust store پروکسی را نمی‌شناخت؛ `GIT_SSL_CAINFO` به bundle مورد اعتماد mountشده متصل شد و read از Laravel موفق شد. TLS یا اعتبارسنجی artifactها غیرفعال نشد.

## خروجی قابل استفاده

- `docs/environment/README.md`: دستورهای نصب، اجرا و تست و حدود ماندگاری محیط.
- `scripts/cloud-environment.sh`: helper نصب/اجرا/تست؛ startup قبل از کامل شدن وابستگی‌ها متوقف می‌شود.
- `docs/environment/Dockerfile`: PHP 8.4 با extensionهای لازم، بدون تغییر Dockerfile.dev ناسازگار upstream.
- `docs/environment/phpunit.xml`: اجرای suite موجود روی دیتابیس جدا؛ upstream فایل تنظیمات PHPUnit ندارد.
- `docs/environment/resolved-versions.json`: نسخه‌های حل‌شدهٔ نصب‌شده و checksum lockهای محلی؛ StateFusion در فهرست جدید وجود ندارد.
- `scripts/check-runtime.py` و `scripts/runtime-smoke.php`: بررسی قابل تکرار وب، Reverb و صف بدون ارسال ایمیل بیرونی.
- `docs/LOCALIZATION_INVENTORY.md`: ۱۸ ماژول، ۳۵ قالب Blade و حدود ۳۵۹ کلید ترجمهٔ انگلیسی؛ شمار کلیدها تقریبی است.

Lockهای ایجادشده محلی و ignored هستند؛ lock جدید در Git اضافه نشده است. تنظیمات محلی با storage عمومی روی دیسک آماده شده‌اند؛ credential سرویس‌های بیرونی در فایل جدید کپی نشده است. دستور install دوباره اجرا شد و موفق بود؛ start دو بار بدون تکثیر سرویس اجرا شد و تست‌ها از طریق runner مستقیم PHPUnit با exit code صفر کامل شدند. اجرای artisan test با configuration خارجی در این نسخه هشدار duplicate option می‌داد؛ helper تست اکنون PHPUnit را مستقیم اجرا می‌کند.

## تنظیمات ذخیره‌شده و حدود اعتبار

فیلدهای `install_script` و `start_skill` در draft تنظیمات محیط ذخیره و پس از رفع مانع به دستورهای معتبر جدید به‌روز شدند. ذخیرهٔ draft نصب را اجرا نمی‌کند و انتشار انجام نشده است. اعتبارسنجی این گزارش مربوط به محیط فعلی است؛ بازگردانی در یک task تازه هنوز بررسی نشده است. volume دیتابیس و پردازش‌های زنده نباید در snapshot فرض شوند؛ در نبود دادهٔ محلی، نصب و seed نمونه دوباره لازم است.

## یافته‌های خارج از اصلاح StateFusion

- داده‌های نمونهٔ آگهی ارز TRY دارند، در حالی که تنظیمات اولیه فقط USD را مجاز می‌کند. ذخیرهٔ فرم کامل بعضی آگهی‌های نمونه بدون اصلاح ارز خطای validation می‌دهد.
- بعضی ویدیوهای نمونه مسیر فایل قابل استفاده ندارند و فرم کامل ویرایش آن‌ها source video معتبر می‌خواهد.
- تست ذخیرهٔ وضعیت آگهی یک نمونهٔ بدون ویدیو با ارز مجاز آماده می‌کند؛ اعتبارسنجی فرم یا assertionها غیرفعال نشده‌اند. این انتخاب پوشش رفع StateFusion را از مشکلات دادهٔ نمونه جدا می‌کند.
- هوش مصنوعی، object storage، نقشه، ورود اجتماعی و پرداخت بیرونی در این فاز اعتبارسنجی نشده‌اند. تبدیل ویدیو با FFmpeg هم در smoke فعلی پوشش ندارد.

## قدم بعدی

فاز ۲: زیرساخت فارسی، گزینهٔ fa، فونت فارسی و جهت RTL؛ طبق AGENTS.md تحلیل با Graphify و تصمیم‌های UI/UX با اسکیل UI/UX Pro Max انجام شود.
