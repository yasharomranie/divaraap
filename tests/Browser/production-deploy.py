import os
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

base = os.environ.get('PRODUCTION_DEPLOY_TEST_URL', 'http://127.0.0.1:8000').rstrip('/')
assert urlparse(base).hostname in ['127.0.0.1', 'localhost']
storage = Path(os.environ.get('PRODUCTION_DEPLOY_TEST_STORAGE', str(Path(__file__).resolve().parents[2] / 'storage/app/private')))
storage.mkdir(parents=True, exist_ok=True)
files = [storage / 'deploy.trigger', storage / 'deploy_log.txt']
originals = {path: (path.read_bytes(), path.stat().st_mtime) if path.exists() else None for path in files}
try:
    for path in files:
        if path.exists():
            path.unlink()
    with sync_playwright() as p:
        browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
        page = browser.new_page(viewport={'width': 390, 'height': 844})
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(base + '/admin/login', wait_until='networkidle')
        page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
        page.wait_for_url(base + '/admin')
        page.goto(base + '/lang/fa', wait_until='networkidle')
        page.goto(base + '/admin/production-deploy', wait_until='networkidle')
        button = page.get_by_role('button', name='اجرای دیپلوی', exact=True)
        button.wait_for()
        assert page.get_by_text('هنوز لاگی برای دیپلوی ثبت نشده است.', exact=True).is_visible()
        button.click()
        page.get_by_text('در انتظار اجرا', exact=True).wait_for()
        assert files[0].exists() and not files[1].exists()
        assert button.count() == 0
        payload = 'git pull completed\n' + '\n'.join(('output-' + str(i) + '-' + 'x' * 300) for i in range(100)) + '\n<script>window.badLogExecuted=true</script>'
        files[0].unlink()
        files[1].write_text(payload)
        page.get_by_role('button', name='اجرای دیپلوی', exact=True).wait_for(timeout=12000)
        pre = page.locator('pre[dir=ltr]')
        pre.wait_for()
        assert pre.inner_text() == payload
        assert not page.evaluate('Boolean(window.badLogExecuted)')
        assert pre.evaluate('(element) => element.scrollHeight > element.clientHeight && element.scrollWidth > element.clientWidth')
        pre.focus()
        page.keyboard.press('End')
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert page.locator('.admin-mobile-tabs [aria-current=page]').count() == 1
        page.set_viewport_size({'width': 1440, 'height': 1000})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert not errors, errors
        print('Real cron-deploy page: click writes only private signal; polling sees cron completion; escaped log scrolls on both axes; keyboard, RTL, mobile/desktop and navigation passed.')
        browser.close()
finally:
    for path, previous in originals.items():
        if previous is None:
            if path.exists():
                path.unlink()
        else:
            path.write_bytes(previous[0])
            os.utime(path, (previous[1], previous[1]))
