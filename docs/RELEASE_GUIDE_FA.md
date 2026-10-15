# راهنمای استقرار و بازگشت نسخهٔ فارسی

این راهنما برای نسخهٔ فارسی با واحد ریال است. فاز ۷ روی محیط توسعه و دیتابیس تست مستقل بررسی شده و build تصویر تولید موفق بوده است؛ اجرای تصویر تولید در این محیط به‌علت محدودیت دیسک انجام نشد و باید پیش از انتشار روی staging بررسی شود؛ سرور بیرونی، دامنه و گواهی TLS جزو محیط این اجرا نیستند. شناسهٔ نسخهٔ قبلیِ سازگار با ریال: `df55e5c0d`.

## قرارداد داده و اجرا

- PHP 8.4، PostgreSQL 16، Node 24 هنگام build، Composer 2، FFmpeg/FFprobe و GD با پشتیبانی WebP لازم‌اند. Dockerfile این وابستگی‌ها را آماده می‌کند.
- composer.lock و package-lock.json در مخزن ثبت‌اند. از `composer install` و `npm ci` استفاده کنید؛ update وابستگی بخشی از استقرار نیست.
- زمان دیتابیس UTC، نمایش فارسی شمسی/تهران و واحد اصلی IRR است. مبلغ ورودی ریالی بدون ضریب ذخیره می‌شود. ارز و مبلغ تاریخی تغییر نمی‌کند.
- APP_KEY در تمام replicaها و نسخه‌ها ثابت بماند. شروع کانتینر کلید نمی‌سازد، migration و seed عمومی اجرا نمی‌کند.
- Compose از queue/cache/session دیتابیسی استفاده می‌کند؛ Redis برای این مسیر لازم نیست. worker هر دو صف default و videos را می‌خواند. retry_after=1920 از timeout=1860 بیشتر است؛ timeout خود پردازش ویدیو 1800 ثانیه است.
- فایل‌ها در storage_data و maintenance/cache/view در framework_data ماندگارند. دیتابیس در db_data است. config Nginx از فایل مخزن به‌صورت read-only mount می‌شود.

## آماده‌سازی نصب تازه

از ریشهٔ checkout نسخهٔ انتخاب‌شده:

```sh
[ -f .env ] || cp .env.example .env
chmod 600 .env
docker compose build app
```

قبل از `up`، مقدار DB_PASSWORD، APP_URL، تنظیمات ایمیل واقعی و شناسه/کلید/رمز Reverb را در `.env` یا مدیر متغیرهای سرور تنظیم کنید. Compose نیازمند APP_KEY و DB_PASSWORD است. برای APP_URL از دامنهٔ HTTPS خودتان استفاده کنید. حالت DEMO در تولید خاموش باشد. اطلاعات AWS/نقشه/ورود اجتماعی فقط برای سرویس فعال و تنظیم‌شده لازم است.

فقط برای نصب تازه و کلید خالی، با تصویر ساخته‌شده کلید ایجاد کنید:

```sh
docker run --rm --entrypoint php \
  --mount "type=bind,source=$PWD/.env,target=/var/www/html/.env" \
  divaraap:release artisan key:generate --force
chmod 600 .env
```

در ارتقای نصب موجود این دستور را اجرا نکنید؛ همان APP_KEY فعلی حفظ شود.

```sh
docker compose config --quiet
docker compose up -d db
docker compose run --rm app php artisan migrate --force
docker compose run --rm app php artisan db:seed --class='Modules\Location\Database\Seeders\IranianLocationSeeder' --force
```

seeder اختصاصی ایران فقط مراکز استان‌های غایب را اضافه می‌کند و رکورد موجود/غیرفعال/حذف نرم‌شده را تغییر نمی‌دهد. seed عمومی شامل حساب‌ها، آگهی‌ها و محتوای نمونه است و برای تولید اجرا نمی‌شود. دسته‌ها، صفحات و قیمت طرح‌های واقعی را مدیر محصول تعیین می‌کند؛ قیمت‌های تاریخی خارجی به‌صورت خودکار ریالی نمی‌شوند.

برای اولین مدیر، از فرمان تعاملی پروژه استفاده کنید و سپس نقش admin را به همان حساب بدهید:

```sh
docker compose run --rm app php artisan make:filament-user
read -r RELEASE_ADMIN_EMAIL
export RELEASE_ADMIN_EMAIL
docker compose run --rm -e RELEASE_ADMIN_EMAIL app php artisan tinker --execute='$user = \Modules\User\App\Models\User::query()->where("email", getenv("RELEASE_ADMIN_EMAIL"))->firstOrFail(); $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate("admin", "web")); echo "Admin role assigned.";'
unset RELEASE_ADMIN_EMAIL
```

در prompt ایمیل، ایمیل همان حساب جدید را وارد کنید. رمز حساب در فرمان خطی یا Git ذخیره نشود.

```sh
docker compose up -d app worker reverb
```

## TLS و Reverb

پورت‌های 8000 و 8080 فقط روی loopback میزبان باز می‌شوند. پراکسی TLS میزبان باید درخواست برنامه را به 8000 و مسیرهای Reverb را به 8080 بفرستد. نمونهٔ بخش location در virtual host دارای گواهی معتبر:

```nginx
location /app/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
}
location /apps/ {
    proxy_pass http://127.0.0.1:8080;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
}
location / {
    proxy_pass http://127.0.0.1:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Nginx داخل کانتینر، X-Forwarded-Proto را به HTTPS در FastCGI منتقل می‌کند. این تنظیم برای ورودی کنترل‌شدهٔ پراکسی/loopback است؛ پورت داخلی برنامه را مستقیم عمومی نکنید. پراکسی باید header را با scheme خودش بازنویسی کند.

برای نمونهٔ فوق REVERB_HOST برابر دامنهٔ عمومی، REVERB_SCHEME=https و REVERB_PORT=443 باشد. PHP نیز باید به همین endpoint عمومی دسترسی داشته باشد. سرور Reverb در کانتینر روی 8080 گوش می‌دهد؛ تنظیمات server و endpoint عمومی متفاوت‌اند. worker و reverb باید همان APP_KEY و REVERB_APP_ID/KEY/SECRET برنامه را دریافت کنند. SESSION_SECURE_COOKIE=true برای دامنهٔ HTTPS تنظیم شود.

سقف فایل ویدیو به‌صورت پیش‌فرض 100MB است؛ PHP upload_max_filesize=100M، post_max_size=110M، Nginx=110M و Livewire با این سقف هماهنگ‌اند. قوانین MIME، تعداد و سقف اختصاصی هر فرم همچنان اعمال می‌شوند.

## ارتقای نصب موجود

ابتدا شناسهٔ Git و image نسخهٔ جاری را ثبت و image را نگه دارید:

```sh
git rev-parse HEAD
docker image tag divaraap:release divaraap:previous
docker compose exec -T app php artisan down --retry=60
docker compose stop worker reverb
mkdir -p backups
chmod 700 backups
docker compose exec -T db pg_dump -U openclassify -d openclassify -Fc > backups/pre-release.dump
docker compose exec -T app tar -C storage/app -czf - . > backups/media-pre-release.tar.gz
chmod 600 backups/*
```

نسخهٔ جدید را checkout/build و migration را روی همان دیتابیس اجرا کنید؛ فایل `.env` و volumeها حفظ شوند:

```sh
docker compose build app
docker compose run --rm app php artisan migrate --force
docker compose up -d --force-recreate app worker reverb
docker compose exec -T app php artisan up
```

maintenance flag در framework_data باقی می‌ماند تا `up` اجرا شود. شروع مجدد سرویس‌ها image/کد جدید را به worker و Reverb هم می‌رساند. `docker compose down -v` بخشی از استقرار یا rollback نیست.

پس از ارتقا، خانه، ثبت‌نام/ورود، جست‌وجو، ثبت/ویرایش آگهی، عکس، مبلغ ریالی، ویدیو، ارسال پیام بین دو حساب آزمایشی، پنل و مدیریت را روی دامنهٔ خود بررسی کنید. دادهٔ آزمایشی فقط متعلق به همان آزمون باشد و پس از کار حذف شود. `failed_jobs` و لاگ worker بررسی شوند.

## بازگشت

برای اشکال کد در فاز ۷، image قبلی سازگار با ریال را برگردانید؛ به migrationهای فاز ۶ و مبلغ‌ها دست نزنید:

```sh
docker compose exec -T app php artisan down --retry=60
docker compose stop worker reverb
docker image tag divaraap:previous divaraap:release
docker compose up -d --force-recreate --no-build app worker reverb
docker compose exec -T app php artisan up
```

checkout فایل‌های Compose/Nginx باید با نسخهٔ برگشتی هماهنگ باشد. APP_KEY، دیتابیس و فایل‌های بارگذاری‌شده حفظ شوند. اگر مشکل داده/اسکیماست، قبل از restore نوشتن متوقف شود و snapshot هماهنگ دیتابیس و رسانه انتخاب شود؛ restore تغییرات پس از snapshot را برمی‌گرداند. بازگردانی backup ابتدا روی دیتابیس جدا تمرین شود. در این اجرا pg_dump/pg_restore روی دیتابیس تست مستقل تمرین شد.

migration افزایش مبلغ در rollback ظرفیت ستون را کوچک نمی‌کند؛ این کار از حذف مبلغ‌های بزرگ جلوگیری می‌کند. rollback تنظیمات قدیمی می‌تواند پیش‌فرض را به USD/+90 برگرداند؛ برای بازگشت کد فاز ۷ نیازی به آن نیست.

## بررسی قابل تکرار در محیط cloud

```sh
bash scripts/cloud-environment.sh install
bash scripts/cloud-environment.sh start
bash scripts/cloud-environment.sh test
bash scripts/cloud-environment.sh php php scripts/check-localization.php
npm run lint
npm run build
python3 scripts/check-runtime.py
```

ابزار مرورگر این اجرا Playwright 1.62.0، Chromium سیستم و axe-core 4.10.3 است. برای تکرار ممیزی عمومی:

```sh
npm --cache /workspace/.cache/npm --prefix /workspace/.tools/phase7 install --no-audit --no-fund axe-core@4.10.3
python3 scripts/check-browser.py --axe-path /workspace/.tools/phase7/node_modules/axe-core/axe.min.js --output /tmp/public-browser.json
```

`--listing-id` اختیاری است و باید شناسهٔ یک آگهی قابل نمایش باشد. این checker فقط GET انجام می‌دهد و زبان، جهت، فونت بارگذاری‌شده، overflow و قواعد خودکار WCAG A/AA را کنترل می‌کند. آزمون خودکار جای همهٔ بررسی‌های دستی دسترس‌پذیری را نمی‌گیرد.

در محیط دارای CA پروکسی، build با اعتبارسنجی TLS فعال انجام شود:

```sh
BUILDX_CONFIG=/workspace/.cache/buildx docker build \
  --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt \
  --build-arg COMPOSER_INSTALL_PREFERENCE=source \
  -t divaraap:release .
```

CA به‌صورت secret فقط هنگام build mount می‌شود و در image کپی نمی‌شود. مسیر source در این محیط به‌دلیل 403 آرشیو GitHub API استفاده شده است. تصویر تولید با Debian ساخته می‌شود؛ نصب بسته‌های Alpine در شبکهٔ این محیط 403 بود. Dockerfile مراحل فایل‌سیستم را برای کاهش مصرف فضای VFS کم کرده است؛ برای build به فضای آزاد کافی نیاز است.
