<x-filament-panels::page>
    <p>ابتدا تنظیمات را ذخیره کنید و کد بازیابی را در جای امن نگه دارید. کتابخانه‌ها همراه نسخه نصب می‌شوند؛ ترمینال نیاز نیست.</p>
    {{ $this->content }}
    @if ($recoveryCode)
        <div role="status"><p>کد بازیابی جدید؛ این کد را خارج از سایت ذخیره کنید:</p><p dir="ltr" class="break-all select-all">{{ $recoveryCode }}</p></div>
    @endif
    <div role="status" aria-live="polite" id="deployment-status" style="white-space:pre-line"></div>
    <p><a href="/recover.php" target="_blank" rel="noopener">بازیابی اضطراری</a> — برگشت کد، پایگاه داده را به عقب برنمی‌گرداند.</p>
    <script src="{{ asset('assets/deployment.js') }}" defer></script>
</x-filament-panels::page>
