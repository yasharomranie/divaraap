# سازگاری تأییدشده با MySQL

نسخهٔ محصول برای نصب تازه از **MySQL** استفاده می‌کند. مقدار پیش‌فرض config/database.php و نمونه‌های .env.example و .env.shared.example به mysql تغییر کرده‌اند. تنظیمات .env نصب فعلی و دادهٔ آن تغییر نکرده‌اند. پشتیبانی قبلی PostgreSQL/MariaDB حفظ شده است.

## نتایج روی موتور واقعی

| موتور | نصب تازه و seed | مجموعهٔ کامل | توابع اجرای فرمان غیرفعال |
| --- | --- | --- | --- |
| MySQL Community 8.0.46 | موفق | ۱۲۶ تست، ۲۰۱۷ assertion موفق | ۷ تست، ۳۳ assertion موفق |
| MySQL Community 8.4.11 | موفق | ۱۲۶ تست، ۲۰۱۷ assertion موفق | ۷ تست، ۳۳ assertion موفق |

هر دو سرور از utf8mb4 و حالت strict شامل ONLY_FULL_GROUP_BY و STRICT_TRANS_TABLES استفاده کردند. محدودیت‌های SQL برای عبور تست‌ها خاموش نشدند. ایندکس یکتای رکورد فعال و آزادشدن مقدار پس از حذف نرم، مبلغ ریالی دقیق، جست‌وجو و مسیرهای اصلی داخل suite بررسی شدند.

روی MySQL 8.4.11، مرورگر با پروفایل shared و توابع proc_open/exec/shell_exec/system/passthru/popen/symlink غیرفعال، این مسیرها را موفق انجام داد:

- ثبت‌نام و ذخیرهٔ شمارهٔ موبایل فارسی؛
- بارگذاری عکس و ساخت تصویرهای لازم؛
- انتخاب دسته/شهر، پیش‌نمایش و ثبت آگهی با ۱۲٬۵۰۰٬۰۰۰٬۰۰۰ ریال؛
- ارسال پیام و دریافت با polling در مرورگر حساب دوم، بدون Reverb؛
- آپلود MP4، وضعیت ready و دریافت فایل از HTTP بدون symlink.

داده‌های آزمایشی در دیتابیس‌های مستقل بودند. پس از بررسی، کانتینرها و volumeهای اختصاصی آزمون حذف شدند؛ دیتابیس توسعه و دادهٔ قبلی دست‌نخورده ماندند. [نتایج ماشینی](release-checks/mysql-compatibility.json).

## تنظیمات مشتری

از نمونهٔ .env.shared.example استفاده می‌شود؛ نصاب آینده این تنظیمات را از فرم دریافت می‌کند:

```dotenv
HOSTING_PROFILE=shared
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

نام دیتابیس و کاربر باید با مقادیر ساخته‌شده در سی‌پنل، از جمله پیشوند حساب، یکسان باشند. PHP 8.4، افزونهٔ pdo_mysql و سایر نیازمندی‌های [راهنمای هاست اشتراکی](SHARED_HOSTING_FA.md) همچنان لازم‌اند. این تأیید شامل MySQL 5.7/قدیمی‌تر یا تمام تنظیمات سفارشی شرکت‌های هاستینگ نیست.

## تکرار آزمون برای توسعه‌دهنده

فایل docs/environment/phpunit-mysql.xml، اتصال mysql و دیتابیس مستقل divaraap_shared_testing را انتخاب می‌کند. این آزمون برای دادهٔ واقعی نیست. سرور آزمایشی با نام divaraap-mysql در شبکهٔ divaraap-dev، کاربر divaraap و رمز از فایل خصوصی خارج از Git آماده شود. برای PHP، تصویر توسعه باید pdo_mysql داشته باشد؛ Dockerfileهای مخزن آن را نصب می‌کنند.

فایل خصوصی محیط آزمون باید DB_CONNECTION=mysql، DB_HOST=divaraap-mysql، DB_PORT=3306، DB_DATABASE=divaraap_shared_testing، DB_USERNAME=divaraap، DB_PASSWORD تنظیم‌شده و MAIL_MAILER=array داشته باشد. قبل از migrate/seed، اتصال TCP احرازشده به همان دیتابیس تأیید شود؛ mysqladmin ping روی socket هنگام راه‌اندازی اولیه به‌تنهایی کافی نیست.

```sh
docker exec --env-file /secure/mysql-test.env -w /app divaraap-php php artisan migrate --force
docker exec --env-file /secure/mysql-test.env -w /app divaraap-php php artisan db:seed --force
docker exec --env-file /secure/mysql-test.env -w /app divaraap-php vendor/bin/phpunit -c /app/docs/environment/phpunit-mysql.xml
docker exec --env-file /secure/mysql-test.env -e HOSTING_PROFILE=shared -w /app divaraap-php php -d disable_functions=proc_open,exec,shell_exec,system,passthru,popen,symlink vendor/bin/phpunit -c /app/docs/environment/phpunit-mysql.xml --filter SharedHostingTest
```

این دستورها مربوط به ساخت و آزمون توسعه‌دهنده‌اند؛ مشتری در بستهٔ نصبی آینده به ترمینال نیاز نخواهد داشت. فایل خصوصی رمز در Git یا خروجی لاگ قرار نگیرد.

## مرز این تحویل

این کار سازگاری با موتور MySQL و پیش‌فرض نصب جدید را تأیید می‌کند؛ دیتابیس PostgreSQL موجود را خودکار تبدیل نمی‌کند. نصب روی حساب واقعی cPanel، بستهٔ آمادهٔ نصب و دکمهٔ به‌روزرسانی هنوز مراحل بعد هستند. چیدمان امن public و PHP 8.4 باید در هاست مشتری بررسی شوند.
