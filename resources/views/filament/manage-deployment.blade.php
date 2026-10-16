<x-filament-panels::page>
    <section aria-label="وضعیت به‌روزرسانی" style="border:1px solid #94a3b8;border-radius:16px;padding:20px;min-width:0">
        <h2 style="font-weight:600;margin-bottom:12px">وضعیت به‌روزرسانی</h2>
        <div role="status" aria-live="polite" aria-atomic="true" id="deployment-status" wire:key="deployment-status-{{ hash('sha256', json_encode($deploymentStatus, JSON_THROW_ON_ERROR)) }}" style="white-space:pre-line;overflow-wrap:anywhere" wire:ignore>{{ $deploymentStatus['message'] ?? 'آمادهٔ دریافت نسخه. ابتدا تنظیمات را ذخیره کنید و کد بازیابی را نگه دارید.' }}</div>
        @if($pendingDeployment)
            <span hidden data-deployment-job="{{ json_encode($pendingDeployment, JSON_THROW_ON_ERROR) }}"></span>
        @endif
        <button type="button" class="fi-btn" style="min-height:44px;margin-top:12px" wire:click="refreshDeploymentStatus">بررسی وضعیت ثبت‌شده روی سرور</button>
        <p style="margin-top:12px"><a href="/recover.php" target="_blank" rel="noopener">بازیابی اضطراری</a> — برگشت کد، پایگاه داده را به عقب برنمی‌گرداند.</p>
    </section>
    <p>کتابخانه‌ها همراه نسخه نصب می‌شوند؛ ترمینال یا کرون نیاز نیست. هنگام نصب این صفحه را باز نگه دارید.</p>
    {{ $this->content }}
    @if ($recoveryCode)
        <div role="status"><p>کد بازیابی جدید؛ این کد را خارج از سایت ذخیره کنید:</p><p dir="ltr" class="break-all select-all">{{ $recoveryCode }}</p></div>
    @endif
    <script src="{{ asset('assets/deployment.js') }}?v=handoff-2" defer data-navigate-once></script>
</x-filament-panels::page>
