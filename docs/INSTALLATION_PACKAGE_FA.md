# بستهٔ نصب هاست اشتراکی

بسته دارای کد برنامه و کتابخانه‌های production، frontend و دارایی‌های Filament ساخته‌شده، وزیرمتن محلی و نصاب فارسی است. Composer/npm/SSH، worker، Cron و اجرای shell روی هاست برای نصب اولیه لازم نیستند. دکمهٔ به‌روزرسانی خودکار هنوز ساخته نشده است.

## چیدمان و حفاظت

ZIP در Home حساب، یک سطح بالاتر از public_html، استخراج می‌شود. پوشهٔ divaraap خصوصی و public_html عمومی است. ریشهٔ خصوصی .htaccess منع دسترسی نیز دارد. مسیر public در bootstrap بسته به‌صورت نسبی ثبت می‌شود تا کش و انتقال مسیر درست باشند. برای addon domain با چیدمان دیگر باید مسیرها پیش از نصب تنظیم و آزموده شوند؛ بسته را روی سایت فعال یا public_html دارای فایل‌های دیگر استخراج نکنید.

کد تصادفی اختصاصی در INSTALLATION-CODE.txt بیرون از public_html قرار دارد و فقط SHA-256 آن در فایل خصوصی install.token نگه داشته می‌شود. ZIP و کد نصب عمومی نشوند. نصاب CSRF، HTTPS روی دامنهٔ عمومی، قفل هم‌زمانی و بررسی خالی‌بودن دیتابیس دارد؛ .env موجود را بازنویسی نمی‌کند. پس از نصب installed.lock ایجاد و توکن حذف می‌شود. در خطای میانی، سایت عمومی فعال نمی‌شود و pending marker برای بازیابی باقی می‌ماند؛ جدول‌ها خودکار حذف نمی‌شوند. راهنمای بازیابی در distribution/INSTALL_FA.md است.

تنظیمات محیط و APP_KEY روی هاست مقصد ساخته می‌شوند. .env توسعه، credentialها، auth.json، کاربران/فایل‌های بارگذاری‌شدهٔ توسعه و cache/logهای محیط داخل بسته نیستند. هیچ حساب نمونه یا رمز پیش‌فرضی ساخته نمی‌شود. دسته‌های پایه و مراکز استان‌های ایران مقداردهی می‌شوند؛ مدیر باید متن صفحات قوانین، SMTP و خدمات تجاری را تکمیل کند.

## بررسی انجام‌شده

- ۱۳۱ تست و ۲۰۳۵ assertion در suite موفق؛ پنج تست ایمنی نصاب با ۱۸ assertion.
- ZIP در مسیر دیگری استخراج و با PHP 8.4، MariaDB 11.4 و proc_open/exec/shell_exec/system/passthru/popen/symlink غیرفعال نصب شد.
- درخواست بدون CSRF و کد معتبر رد شد؛ نصب روی دیتابیس دارای یک رکورد آزمایشی رد و رکورد حفظ شد.
- نصب تازه فقط با فرم مرورگر، ساخت مدیر، قفل نصب، پاسخ ۴۰۴ به نصب مجدد، خانهٔ فارسی و ورود به مدیریت موفق بودند.
- رمز دیتابیس دارای دلار، علامت نقل‌قول و backslash آزمایش شد؛ round-trip فایل dotenv نیز درست بود.
- دارایی‌های محلی و بستهٔ وابستگی production بررسی شدند. فایل‌های داخل کتابخانه‌ها صرفاً بر مبنای نام شبیه تست حذف نمی‌شوند؛ بعضی نام‌ها مانند Carbon/Traits/Test.php وابستگی واقعی runtime هستند.

این آزمون شبیه‌سازی محدودیت‌های هاست است؛ روی حساب واقعی cPanel مشتری اجرا نشده است. PHP 8.4، افزونه‌های نشان‌داده‌شده در نصاب، HTTPS و دسترسی نوشتن لازم‌اند. سقف زمان وب‌سرور می‌تواند مستقل از PHP باشد.

## ساخت قابل تکرار برای توسعه‌دهنده

ابتدا lockfileها نصب و frontend و assetهای Filament ساخته شده باشند. تصویر PHP محیط باید pdo_mysql داشته باشد. خروجی همیشه خارج از checkout باشد و build موجود حفظ می‌شود.

```sh
npm ci
npm run build
python3 scripts/build-shared-package.py --output /workspace/.environment/divaraap/new-package
docker exec -w /setup/new-package/build/divaraap divaraap-php composer install --no-dev --no-scripts --optimize-autoloader --no-interaction --prefer-dist
docker exec -w /setup/new-package/build/divaraap divaraap-php composer check-platform-reqs --no-dev
python3 scripts/finalize-shared-package.py --output /workspace/.environment/divaraap/new-package
```

اسکریپت build کد tracked را می‌گیرد؛ ابتدا تغییرات موردنظر stage/commit شوند. هر بسته توکن تازه دارد. finalizer، نبودن تنظیمات و دادهٔ runtime، production بودن وابستگی‌ها، وجود frontend/Filament و سلامت ZIP را بررسی می‌کند و SHA256SUMS.txt و manifest تولید می‌کند. خروجی حجیم ZIP و کد اختصاصی نصب داخل Git ثبت نمی‌شوند. برای نصب مشتری، [راهنمای داخل بسته](../distribution/INSTALL_FA.md) مبناست.
