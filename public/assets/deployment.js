(() => {
    if (window.divaraapDeploymentController) {
        window.divaraapDeploymentController.scan();
        return;
    }
    const attempted = new Set();
    let running = false;
    let queued = null;
    const display = (message) => {
        const status = document.getElementById('deployment-status');
        if (status) {
            status.textContent = message;
            status.scrollIntoView({ block: 'nearest' });
        }
        return status;
    };
    const install = async (job) => {
        if (!job || typeof job.id !== 'string' || typeof job.token !== 'string') return;
        if (!/^[a-f0-9]{32}$/.test(job.id) || !/^[a-f0-9]{64}$/.test(job.token)) return;
        const key = `${job.id}:${job.token}`;
        if (running || attempted.has(key)) return;
        if (!document.getElementById('deployment-status')) { queued = job; return; }
        attempted.add(key);
        queued = null;
        running = true;
        display('درخواست نصب ارسال شد؛ پشتیبان‌گیری و نصب در حال انجام است. صفحه را نبندید.');
        const notify = (event) => { event.preventDefault(); event.returnValue = ''; };
        window.addEventListener('beforeunload', notify);
        try {
            const response = await fetch('/deployment-runner.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ id: job.id, token: job.token }),
            });
            let result;
            try { result = await response.json(); } catch {
                display(`پاسخ معتبر نصب دریافت نشد (HTTP ${response.status}). وضعیت ثبت‌شده روی سرور را بررسی کنید؛ درخواست ممکن است همچنان در حال اجرا باشد.`);
                return;
            }
            const message = typeof result.message === 'string' ? result.message : `اجرای نصب ناموفق بود (HTTP ${response.status}).`;
            const steps = Array.isArray(result.steps) ? result.steps.filter((step) => typeof step === 'string') : [];
            const status = display([message, ...steps].join('\n'));
            if (response.ok && result.ok === true && status) {
                const link = document.createElement('a');
                link.href = '/admin'; link.textContent = 'ورود به نسخهٔ جدید';
                status.append(document.createElement('br'), link);
            }
        } catch {
            display('ارتباط مرورگر با اجراکنندهٔ نصب قطع شد. وضعیت ثبت‌شده روی سرور را بررسی کنید؛ اگر هنوز «آماده» است، دکمهٔ دریافت و نصب همان بسته را ادامه می‌دهد.');
        } finally {
            running = false;
            window.removeEventListener('beforeunload', notify);
        }
    };
    let uploading = false;
    document.addEventListener('click', async (event) => {
        const trigger = event.target instanceof Element ? event.target.closest('[data-deployment-upload-start]') : null;
        if (!trigger || uploading || running) return;
        const container = trigger.closest('[data-deployment-upload]');
        const file = container.querySelector('[data-deployment-package]').files[0];
        const checksumFile = container.querySelector('[data-deployment-checksum]').files[0];
        if (!file || !checksumFile) { display('ابتدا بستهٔ ZIP و فایل بررسی صحت را انتخاب کنید.'); return; }
        if (file.size < 1 || file.size > 268435456 || checksumFile.size > 16384) { display('حجم فایل بسته یا فایل بررسی صحت معتبر نیست.'); return; }
        uploading = true;
        trigger.disabled = true;
        try {
            const text = await checksumFile.text();
            const checksum = text.match(/^([a-f0-9]{64})[ \t]+\*?divaraap-update\.zip\s*$/i)?.[1];
            if (!checksum) { display('فایل بررسی صحت متعلق به divaraap-update.zip نیست.'); return; }
            const componentRoot = trigger.closest('[wire\\:id]');
            const component = window.Livewire.find(componentRoot.getAttribute('wire:id'));
            display('در حال شروع بارگذاری بسته از دستگاه…');
            const id = await component.$call('startPackageUpload', file.size, checksum);
            for (let offset = 0; offset < file.size;) {
                const chunk = file.slice(offset, offset + 262144);
                const encoded = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result.split(',')[1]);
                    reader.onerror = reject;
                    reader.readAsDataURL(chunk);
                });
                const next = await component.$call('uploadPackageChunk', id, offset, encoded);
                if (next !== offset + chunk.size) throw new Error('Upload offset mismatch');
                offset = next;
                display(`بارگذاری بسته: ${Math.floor(offset * 100 / file.size)}٪`);
            }
            display('بارگذاری تمام شد؛ بررسی صحت و آماده‌سازی نصب…');
            await component.$call('finishPackageUpload', id);
        } catch {
            display('بارگذاری یا آماده‌سازی بسته انجام نشد. اتصال مرورگر و فایل بررسی صحت را بررسی کنید؛ برای تلاش تازه دوباره بارگذاری را بزنید.');
        } finally {
            uploading = false;
            trigger.disabled = false;
        }
    });
    const scan = () => {
        if (queued) install(queued);
        const element = document.querySelector('[data-deployment-job]');
        if (!element) return;
        try { install(JSON.parse(element.getAttribute('data-deployment-job'))); } catch { }
    };
    window.divaraapDeploymentController = { scan };
    window.addEventListener('deployment-ready', (event) => { event.stopImmediatePropagation(); install(event.detail); }, true);
    document.addEventListener('livewire:navigated', scan);
    new MutationObserver(scan).observe(document.documentElement, { childList: true, subtree: true });
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan, { once: true });
    scan();
})();
