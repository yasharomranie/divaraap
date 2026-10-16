# دیپلوی با فایل سیگنال و Cron در cPanel

صفحهٔ جدید در `/admin/production-deploy` قرار دارد: تنظیمات ← دیپلوی با کرون. Laravel 13، Filament 5 و Livewire 4 استفاده می‌شوند. مجوز `production-deploy.access` با migration ثبت و به نقش `admin` داده می‌شود. قاعدهٔ مرکزی پروژه نیز نقش admin را مدیر کامل می‌داند؛ شرط ورود کلی پنل همچنان نقش admin است.

پنل فقط `deploy.trigger` را روی `Storage::disk('local')` می‌نویسد. این دیسک در پروژه `storage/app/private` است. سرویس هیچ فرمان، Git، Artisan، Process یا تابع اجرای شِل فراخوانی نمی‌کند. کرون اجرای سیستم‌عامل را انجام می‌دهد. فایل لاگ `deploy_log.txt` است و زمان آخرین تغییرش با منطقهٔ زمانی صریح برنامه ساخته و با ابزار شمسی پروژه نمایش داده می‌شود.

حالت «در انتظار اجرا» فقط وجود فایل سیگنال را نشان می‌دهد. کرون آن را پیش از شروع کار حذف می‌کند؛ بنابراین برداشته‌شدن badge به معنی پایان موفق دیپلوی نیست. نتیجه را از لاگ بخوانید و تا پایان اجرای فعلی درخواست تازه ثبت نکنید.

## پیش‌نیاز نصب فعلی

مسیر شناخته‌شدهٔ نصب شما `/home/yas/adorax/divaraap` است. دستور زیر فقط وقتی معتبر است که این مسیر یک checkout واقعی Git باشد و همان کد، سایت را ارائه کند. این وضعیت از داخل محیط توسعه قابل تأیید نیست.

بسته‌های ZIP قبلی `.git` ندارند. نصب‌های نسخه‌بندی‌شده نیز با `active-release.json` به `releases/<id>` اشاره می‌کنند؛ `git pull` در ریشهٔ چنین نصبی، کد فعال را عوض نمی‌کند. فرمان آماده این دو حالت را تشخیص می‌دهد، پیام را داخل لاگ می‌نویسد و به سایت و سیگنال دست نمی‌زند.

اگر نصب فعلی هنوز ZIP یا نسخه‌بندی‌شده است، پیش از فعال‌سازی این کرون باید یک checkout فعال آماده شود. راه انتقال بدون SSH:

1. از فایل‌ها و پایگاه دادهٔ فعلی پشتیبان بگیرید. دیتابیس موجود را نگه دارید.
2. با Git Version Control در cPanel یا یک کرون یک‌باره، مخزن را در یک مسیر خالی مثل `/home/yas/adorax/source` clone کنید. مسیر نصب موجود را overwrite یا reset نکنید. اگر امکانات Git هاست متفاوت است، ابتدا همان مسیر و امکانات را مشخص کنید.
3. `.env`، پوشهٔ `storage` و `vendor` را از نصب فعلی به checkout تازه کپی کنید. `APP_KEY` و اطلاعات دیتابیس را حفظ کنید. فایل‌های `bootstrap/hosting-public.php` و `bootstrap/hosting-runtime.php` و کش‌های قدیمی را به checkout استاندارد منتقل نکنید.
4. فایل‌های آمادهٔ فرانت‌اند و Filament را از بستهٔ سازگار همان نسخه به پوشهٔ `public` در checkout تازه منتقل کنید؛ از جمله `build`، `assets`، `css`، `js`، `fonts` و `vendor`. فقط `git pull` این فایل‌ها را تحویل نمی‌دهد: `vendor` و `public/build` در این مخزن داخل Git نیستند. تغییر dependency یا ظاهر در آینده نیز به تحویل artifact سازگار یا مرحلهٔ build خارج از درخواست وب نیاز دارد.
5. Document Root دامنه را روی `public` در checkout تازه تنظیم و ورود، صفحات و فایل‌های کاربران را بررسی کنید. اگر مسیر تازه را انتخاب کردید، مقدار `DEPLOY_APP` در فرمان کرون را به همان مسیر، مثلاً `/home/yas/adorax/source`، تغییر دهید. کد فعال و `storage/app/private` مورد استفادهٔ پنل و کرون باید یکسان باشند.
6. ثبت مجوز با migration انجام می‌شود. اگر پیش از اولین اجرای کرون به migration نیاز بود، همان دستور migrate را با باینری کامل PHP در یک کرون موقت اجرا کنید و پس از موفقیت آن کرون موقت را حذف کنید. نصب تازه روی دیتابیس موجود اجرا نکنید.

## تنظیم کرون اصلی

1. در cPanel وارد **Cron Jobs** شوید.
2. زمان‌بندی **Once Per Minute** را انتخاب کنید: هر پنج فیلد `*`، یعنی `* * * * *`.
3. محتوای یک‌خطی `scripts/cpanel-cron.txt` (در بستهٔ نصب: `cpanel-cron.txt`) را در **Command** کپی کنید. عبارت زمان‌بندی را داخل Command نگذارید.
4. فقط یک نمونه از این کرون را نگه دارید.
5. در پنل روی «اجرای دیپلوی» بزنید. سیگنال باید حداکثر در اجرای بعدی کرون پردازش شود؛ صفحه هر پنج ثانیه وضعیت و لاگ را تازه می‌کند.

فرمان با مسیر فعلی نصب:

```sh
DEPLOY_APP=/home/yas/adorax/divaraap; [ -f "$DEPLOY_APP/storage/app/private/deploy.trigger" ] && { if [ ! -d "$DEPLOY_APP/.git" ] || [ -f "$DEPLOY_APP/active-release.json" ]; then printf '%s\n' 'Deployment requires an active Git checkout. ZIP/versioned-release installations must be converted first.' > "$DEPLOY_APP/storage/app/private/deploy_log.txt" 2>&1; else DEPLOY_PHP=; for DEPLOY_CANDIDATE in /opt/cloudlinux/alt-php84/root/usr/bin/php /opt/alt/php84/usr/bin/php /opt/cpanel/ea-php84/root/usr/bin/php; do [ -x "$DEPLOY_CANDIDATE" ] && { DEPLOY_PHP="$DEPLOY_CANDIDATE"; break; }; done; if [ -n "$DEPLOY_PHP" ]; then cd "$DEPLOY_APP" && { rm -f storage/app/private/deploy.trigger; GIT_TERMINAL_PROMPT=0 git pull --ff-only origin main > storage/app/private/deploy_log.txt 2>&1 && "$DEPLOY_PHP" "$DEPLOY_APP/artisan" migrate --force >> storage/app/private/deploy_log.txt 2>&1 && "$DEPLOY_PHP" "$DEPLOY_APP/artisan" migrate --path=database/settings --force >> storage/app/private/deploy_log.txt 2>&1 && "$DEPLOY_PHP" "$DEPLOY_APP/artisan" optimize:clear >> storage/app/private/deploy_log.txt 2>&1 && "$DEPLOY_PHP" "$DEPLOY_APP/artisan" optimize >> storage/app/private/deploy_log.txt 2>&1; }; else printf '%s\n' 'PHP 8.4 CLI was not found. Set its absolute path in the cron command.' > "$DEPLOY_APP/storage/app/private/deploy_log.txt" 2>&1; fi; fi; }
```

سه مسیر مطلق متداول PHP 8.4 بررسی می‌شوند و اولین فایل اجرایی انتخاب می‌شود؛ فرمان به `php` داخل PATH متکی نیست. اگر هیچ‌کدام موجود نباشد، خطای مشخص در لاگ ثبت و سیگنال حفظ می‌شود. در آن حالت مسیر تأییدشدهٔ هاست را جایگزین فهرست کنید. موجودبودن هیچ‌یک از این مسیرها روی هاست شما از اینجا تأیید نشده است.

اولین `>` لاگ قبلی را بازنویسی می‌کند و `>>` خروجی مراحل بعد را اضافه می‌کند. `--ff-only` مانع merge خودکار در production می‌شود و `&&` اجازه نمی‌دهد پس از شکست Git، مهاجرت یا optimize روی کد قبلی اجرا شود. migration تنظیمات نیز اضافه شده، چون در این پروژه `database/settings` مسیر جداگانه دارد. هیچ `git reset --hard` یا `git clean` اجرا نمی‌شود.

توکن ذخیره‌شده در صفحهٔ قدیمی به‌روزرسانی، remote مخزن Git هاست را تنظیم نمی‌کند؛ این دو روش احراز هویت مستقل دارند.

برای مخزن عمومی فعلی، remote می‌تواند `https://github.com/yasharomranie/divaraap.git` باشد. مخزن خصوصی باید از قبل احراز هویت غیرتعاملی داشته باشد؛ Fine-grained token با Contents: Read-only کافی است و انقضا دارد. remote خصوصی را فقط در فضای امن هاست تنظیم کنید؛ توکن را در چت یا لاگ قرار ندهید. `GIT_TERMINAL_PROMPT=0` مانع انتظار کرون برای ورود تعاملی می‌شود.

## درخواست دستی در صورت خطای ۵۰۰

در File Manager فایل خالی `/home/yas/adorax/divaraap/storage/app/private/deploy.trigger` را بسازید. اگر checkout فعال مسیر دیگری دارد، از `storage/app/private` همان checkout استفاده کنید. کرون همان مسیر را پردازش می‌کند. فایل باید نام دقیق `deploy.trigger` داشته باشد، بدون پسوند اضافی.

## آزمون

`DeploymentTriggerServiceTest` و `ProductionDeployTest` وضعیت سیگنال، نبودن و وجود لاگ، منطقهٔ زمانی تهران، تاریخ شمسی نیمه‌شب، escape خروجی، جلوگیری از دسترسی غیرمجاز و خطای نوشتن را بررسی می‌کنند. فایل‌های این آزمون‌ها روی `Storage::fake('local')` قرار دارند.
