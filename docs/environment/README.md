# محیط توسعهٔ فاز ۱

این دستورها از ریشهٔ `/workspace/divaraap` اجرا می‌شوند. نیازمندی میزبان: Docker با daemon فعال، Node 24، npm، Python 3 و دسترسی شبکهٔ پروکسی محیط. PHP 8.4 و PostgreSQL 16 داخل کانتینرهای جدا اجرا می‌شوند.

## نصب و اجرا

```bash
bash scripts/cloud-environment.sh install
bash scripts/cloud-environment.sh seed
bash scripts/cloud-environment.sh start
```

`install` سورس موجود را نگه می‌دارد، تصویر PHP و extensionهای لازم را می‌سازد، تنظیمات محلی را فقط در صورت نبودن `.env` ایجاد می‌کند، وابستگی‌ها و build را آماده و migration را اجرا می‌کند. رمزهای سرویس‌های بیرونی از نمونه کپی نمی‌شوند. `seed` فقط برای دیتابیس نمونهٔ توسعه اجرا شود؛ برای دادهٔ واقعی لازم نیست. `start` وب، worker صف و Reverb را اجرا می‌کند و در اجرای مجدد پردازش فعال را تکثیر نمی‌کند.

پس از نصب، دستورهای PHP از این مسیر در دسترس‌اند:

```bash
bash scripts/cloud-environment.sh php php artisan about
bash scripts/cloud-environment.sh php composer check-platform-reqs
```

برای تست:

```bash
bash scripts/cloud-environment.sh test
npm run type-check
npm run lint
npm run build
```

مخزن upstream فایل phpunit.xml ندارد؛ `docs/environment/phpunit.xml` آزمون‌های Feature موجود را روی `openclassify_testing` اجرا می‌کند. این فایل assertionها و کد تست را تغییر نمی‌دهد. دیتابیس تست از دیتابیس توسعه جدا است. دادهٔ نمونهٔ تست فقط وقتی جدول users خالی است seed می‌شود. تست‌های موجود با transaction کار می‌کنند.

## نگهداری و عیب‌یابی

- وضعیت محلی، cache و لاگ سرویس‌ها: `/workspace/.environment/divaraap`.
- سرویس‌ها: `divaraap-php` و `divaraap-db` روی شبکهٔ `divaraap-dev`؛ دادهٔ PostgreSQL در volume با نام `divaraap-pgdata` است.
- وب و Reverb فقط روی loopback میزبان به پورت‌های 8000 و 8080 متصل‌اند؛ بررسی HTTP داخلی است و لینک preview عمومی ایجاد نمی‌شود.
- در ماشین تازه یا بازیابی بدون volume، نصب و seed نمونه را دوباره اجرا کنید. ماندگاری دادهٔ volume در snapshot محیط فرض نشده است. پردازش‌ها هم باید دوباره راه‌اندازی شوند.
- trust store میزبان به صورت read-only به کانتینر mount می‌شود. TLS و اعتبارسنجی بسته‌ها غیرفعال نمی‌شوند.
- Composer با `--prefer-source` نصب می‌کند؛ Git از `GIT_SSL_CAINFO` برای گواهی پروکسی استفاده می‌کند. این مسیر در محیط فعلی به دلیل مسدود بودن دانلود آرشیو از api.github.com انتخاب شده است و clone نخست حجیم‌تر است.
- Dockerfile.dev اصلی PHP 8.3 دارد و با requirement فعلی 8.4 سازگار نیست؛ فایل اصلی در فاز ۱ تغییر نکرده است.
- فایل‌های lock در upstream نادیده گرفته شده‌اند. نصب اول lockهای محلی ایجاد می‌کند؛ refresh با آن‌ها انجام می‌شود. `resolved-versions.json` نسخه‌ها و checksum آن‌ها را ثبت می‌کند، اما جایگزین lockfile نیست. نصب در clone تازه بدون lock ممکن است نسخه‌های سازگار جدیدتری بگیرد.
- اسکریپت از کانتینرهای اختصاصی با همین نام استفاده می‌کند؛ اگر از قبل کانتینری نامرتبط با این نام وجود دارد، پیش از اجرا نام‌ها و mountها بررسی شوند.
- credentialهای تولیدشدهٔ محلی و `.env` در Git ثبت نشوند.

صف و broadcasting در تست HTTP با sync/log جایگزین می‌شوند؛ بررسی مستقل سرویس واقعی باید جدا از نتیجهٔ این تست‌ها گزارش شود. سرویس‌های هوش مصنوعی، object storage، نقشه، ورود اجتماعی و پرداخت بیرونی بدون credential در این فاز فعال نمی‌شوند.
