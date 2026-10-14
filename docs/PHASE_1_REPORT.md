# گزارش فاز ۱ — خط مبنای اجرایی

وضعیت: زیرساخت و فرانت‌اند آماده‌اند؛ فاز ۱ به دلیل در دسترس نبودن یک وابستگی PHP تکمیل نشده است. پیاده‌سازی فارسی‌سازی آغاز نشده است.

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
| نصب کامل Composer | مسدود؛ جزئیات در بخش بعد |
| migration و seed برنامه | اجرا نشده؛ نصب کامل Composer پیش‌نیاز است |
| آزمون‌های Feature موجود | اجرا نشده؛ نتیجهٔ پاس یا شکست برای این تست‌ها نداریم |
| وب، worker و Reverb | اجرای کاربردی تأیید نشده؛ وابستگی PHP مانع است |

## مانع مشخص

`composer.json` بستهٔ `a909m/filament-statefusion` با constraint `^2.3` را لازم دارد. نسخهٔ حل‌شده `v2.3.0` است. اطلاعات رسمی Packagist به این سورس اشاره می‌کند:

- مخزن: `https://github.com/A909M/Filament-StateFusion`
- commit: `ad8904426012c51255670bcd30520ca031bb71a8`

Git از همین مخزن پاسخ Repository not found گرفت. صفحهٔ GitHub و آرشیو همان commit در codeload هم HTTP 404 برگرداندند. این شواهد نشان می‌دهد نسخه در این محیط قابل دریافت نیست؛ از آن‌ها نمی‌توان خصوصی‌شدن یا حذف‌شدن مخزن را قطعی نتیجه گرفت.

اثر در کد با Graphify پیدا و با سورس تأیید شد:

- `Modules/Listing/States/ListingStatus.php`: interface و trait بسته.
- `Modules/User/App/States/UserStatus.php`: interface و trait بسته.
- `Modules/Admin/Providers/AdminPanelProvider.php`: plugin بسته.

حذف dependency به‌تنهایی کافی نیست و نیاز به اصلاح و تست رفتار وضعیت‌ها و پنل مدیریت دارد. سورس، تست‌ها و dependency declarations در این فاز تغییر نکردند و جایگزین ساختگی برای بسته ساخته نشد.

نصب اولیهٔ آرشیوهای Composer نیز در api.github.com با CONNECT 403 مواجه شد. مسیر استاندارد `--prefer-source` برای دیگر بسته‌ها استفاده شد. Git داخل کانتینر ابتدا trust store پروکسی را نمی‌شناخت؛ `GIT_SSL_CAINFO` به bundle مورد اعتماد mountشده متصل شد و read از Laravel موفق شد. TLS یا اعتبارسنجی artifactها غیرفعال نشد.

## خروجی قابل استفاده

- `docs/environment/README.md`: دستورهای نصب، اجرا و تست و حدود ماندگاری محیط.
- `scripts/cloud-environment.sh`: helper نصب/اجرا/تست؛ startup قبل از کامل شدن وابستگی‌ها متوقف می‌شود.
- `docs/environment/Dockerfile`: PHP 8.4 با extensionهای لازم، بدون تغییر Dockerfile.dev ناسازگار upstream.
- `docs/environment/phpunit.xml`: اجرای suite موجود روی دیتابیس جدا؛ upstream فایل تنظیمات PHPUnit ندارد.
- `docs/environment/resolved-versions.json`: نسخه‌های حل‌شده و checksum lockهای محلی؛ این فهرست به معنای نصب موفق همهٔ بسته‌ها نیست.
- `docs/LOCALIZATION_INVENTORY.md`: ۱۸ ماژول، ۳۵ قالب Blade و حدود ۳۵۹ کلید ترجمهٔ انگلیسی؛ شمار کلیدها تقریبی است.

Lockهای ایجادشده محلی و ignored هستند؛ lock جدید در Git اضافه نشده است. تنظیمات محلی با storage عمومی روی دیسک آماده شده‌اند؛ credential سرویس‌های بیرونی در فایل جدید کپی نشده است. دستورهای کامل startup و تست هنوز به دلیل dependency blocker قابل تأیید نیستند.

## ادامهٔ لازم

فیلدهای `install_script` و `start_skill` در draft تنظیمات محیط ذخیره شدند؛ ذخیرهٔ draft نصب را اجرا نکرده و محیط منتشر نشده است. این دستورها مانع فعلی و مراحل تأییدنشده را صریح مشخص می‌کنند. پس از رفع مانع، تنظیمات محیط باید بازبینی و ذخیره شوند و سپس انتشار انجام شود؛ ذخیرهٔ این draft به معنای آماده بودن برنامه نیست.

ابتدا نسخهٔ معتبر همین dependency باید قابل دریافت شود، یا در یک تغییر کدنویسی جداگانه، جایگزین پشتیبانی‌شدهٔ آن انتخاب و اتصال آن در برنامه اصلاح شود. سپس نصب Composer، migration و seed، تست‌های Feature، درخواست‌های HTTP واقعی و بررسی worker/Reverb ادامه یابد. فاز ۲ پس از ثبت خط مبنای اجرایی شروع شود.
