# Graphify در پروژه

ابزار رسمی `graphifyy==0.9.90` از PyPI نصب شده و اسکیل Codex در `.codex/skills/graphify/` ثبت شده است. Graphify ابزار توسعه است و وابستگی زمان اجرای Laravel نیست.

## اجرای مجدد

نیازمندی: Python 3.10+ و uv. از ریشهٔ مخزن:

```bash
bash scripts/graphify.sh extract . --code-only --max-workers 4
bash scripts/graphify.sh cluster-only . --no-label
bash scripts/graphify.sh query "SetLocale LanguageController"
bash scripts/graphify.sh explain "SetLocale"
bash scripts/graphify.sh update .
```

اسکریپت، CLI را در پوشهٔ والد پروژه نصب/اجرا می‌کند؛ در محیط فعلی `/workspace/bin/graphify` است. برای انتخاب مسیر دیگر از `GRAPHIFY_TOOLS_ROOT` استفاده شود. برای اجرای مستقیم CLI و hook ثبت‌شدهٔ Codex در محیط فعلی، `export PATH="/workspace/bin:$PATH"` لازم است. hook ابزار Codex صرفاً no-op رسمی است؛ استفاده از گراف با دستورهای AGENTS.md هدایت می‌شود و بازسازی خودکار پس از commit نصب نشده است.

## استفاده در فارسی‌سازی

تحلیل محلی AST و پرس‌وجوی واقعی انجام شد. گراف، ارتباط `SetLocale` با `bootstrap/app.php` و متد `handle` را نشان داد. پرس‌وجوی `localeOptions` نشان داد که فرم تنظیمات و داده‌های پیش‌فرض هر دو به گزینه‌های زبان وابسته‌اند؛ بنابراین افزودن فارسی فقط در قالب سایت کافی نیست.

نقاط پیگیری فاز ۲:

- `Modules/Site/App/Http/Middleware/SetLocale.php`: پذیرش زبان نشست.
- `Modules/Site/App/Http/Controllers/LanguageController.php`: تغییر زبان.
- `Modules/Site/Filament/Admin/Pages/ManageGeneralSettings.php`: گزینه‌های زبان، فرم و مقدار پیش‌فرض.
- `app/Providers/AppServiceProvider.php`: تنظیم LanguageSwitch در Filament.
- `bootstrap/app.php`: اتصال middleware زبان به چرخهٔ درخواست.

## خروجی و حدود پوشش

`graphify-out/graph.json` گراف قابل پرس‌وجو، `GRAPH_REPORT.md` گزارش معماری و `graph.html` نمای تعاملی است. گراف و گزارش در مخزن ذخیره شده‌اند؛ cache و مسیرهای وابسته به ماشین نادیده گرفته می‌شوند.

تحلیل `--code-only` محلی است و API مدل استفاده نکرد. اسناد و تصاویر تحلیل معنایی نشده‌اند. بسیاری از فایل‌های ترجمهٔ PHP آرایهٔ داده‌اند و نماد AST تولید نمی‌کنند؛ گراف جایگزین بررسی کامل کلیدهای ترجمه و CSS نیست. ابزار `resources/css/base/tokens.css` را به دلیل نام آن حساس تشخیص داده و کنار گذاشته است؛ فایل حذف یا تغییر داده نشده است. روابط EXTRACTED از منبع استخراج شده‌اند و INFERRED قطعیت کمتری دارند. این تحلیل، اجرای برنامه یا پاس شدن تست‌های Laravel را اثبات نمی‌کند.
