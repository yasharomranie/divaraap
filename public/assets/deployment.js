(() => {
    if (window.divaraapDeploymentListener) return;
    window.divaraapDeploymentListener = true;
    window.addEventListener('deployment-ready', async (event) => {
        const status = document.getElementById('deployment-status');
        if (!status) return;
        status.textContent = 'نسخه آماده است؛ پشتیبان‌گیری و نصب در حال انجام است. صفحه را نبندید.';
        try {
            const response = await fetch('/deployment-runner.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: event.detail.id, token: event.detail.token }),
            });
            const result = await response.json();
            status.textContent = [result.message, ...(result.steps || [])].join('\n');
            if (result.ok) {
                const link = document.createElement('a');
                link.href = '/admin';
                link.textContent = 'ورود به نسخهٔ جدید';
                status.append(document.createElement('br'), link);
            }
        } catch {
            status.textContent = 'پاسخ نصب دریافت نشد. ابتدا سایت را بررسی کنید؛ در صورت نیاز /recover.php را با کد بازیابی باز کنید.';
        }
    });
})();
