# اسکیل طراحی UI/UX Pro Max

اسکیل از ابزار رسمی `ui-ux-pro-max-cli@2.15.0` نصب شده است:

```bash
npm --cache /workspace/.cache/npm install --global --prefix /workspace/.tools/uipro ui-ux-pro-max-cli@2.15.0
/workspace/.tools/uipro/bin/uipro init --ai codex --offline
```

مسیر اسکیل اصلی: `.agents/skills/ui-ux-pro-max/SKILL.md`.
داده‌ها و اسکریپت‌های Python همراه اسکیل در مخزن ثبت شده‌اند؛ برای استفادهٔ روزمره فقط Python 3 لازم است و جست‌وجو شبکه یا API مدل نمی‌خواهد. نصب‌کننده چند اسکیل مکمل طراحی هم در `.agents/skills/` قرار داده است؛ قانون اصلی پروژه بر UI/UX Pro Max متمرکز است.

## قانون پروژه

در هر کار طراحی، ساخت یا اصلاح رابط، ابتدا اسکیل خوانده شود و جست‌وجوی مرتبط انجام شود. برای تحلیل ساختار کد ابتدا Graphify استفاده شود؛ برای انتخاب طراحی و بررسی UX از این اسکیل استفاده شود. توصیه‌های آن با معماری Laravel/Blade/Filament و الزامات فارسی، RTL و رابط بدون انیمیشن تطبیق داده شوند.

## اجرای نمونه

```bash
python3 .agents/skills/ui-ux-pro-max/scripts/search.py "keyboard navigation" --domain ux
python3 .agents/skills/ui-ux-pro-max/scripts/search.py "form validation" --stack laravel
python3 .agents/skills/ui-ux-pro-max/scripts/search.py "classifieds marketplace minimal" --design-system -p Divaraap
```

اسکریپت جست‌وجو با Python موجود اجرا و راهنمای برچسب فرم و دسترسی صفحه‌کلید بازیابی شد. نتیجهٔ جست‌وجو پیشنهاد طراحی است و به معنای تست رابط واقعی نیست. نصب اسکیل به‌خودی‌خود ظاهر برنامه را تغییر نمی‌دهد. در نشست تازه، اسکیل پروژه باید از مسیر ثبت‌شده خوانده شود.
