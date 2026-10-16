import hashlib
import json
import os
from pathlib import Path
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

base = os.environ['DEPLOYMENT_TEST_URL'].rstrip('/')
root = Path(os.environ['DEPLOYMENT_TEST_ROOT']).resolve()
assert urlparse(base).hostname in ['127.0.0.1', 'localhost']
assert (root / 'review.fixture').is_file()
private = root / 'divaraap'
old = json.loads((private / 'active-release.json').read_text())['current'] if (private / 'active-release.json').exists() else None
environment = hashlib.sha256((private / '.env').read_bytes()).hexdigest()
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    page = browser.new_page(viewport={'width': 390, 'height': 844})
    errors = []
    installs = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('request', lambda request: installs.append(request) if request.url.endswith('/deployment-runner.php') and json.loads(request.post_data or '{}').get('action') != 'status' else None)
    page.goto(base + '/admin/login', wait_until='networkidle')
    page.locator('input[type=email]').fill(os.environ['DEPLOYMENT_TEST_EMAIL'])
    page.locator('input[type=password]').fill(os.environ['DEPLOYMENT_TEST_PASSWORD'])
    page.locator('button[type=submit]').first.click()
    page.wait_for_url(base + '/admin')
    page.goto(base + '/admin/manage-deployment', wait_until='networkidle')
    assert page.get_by_text('توکنی ذخیره نشده است.', exact=False).count() == 1
    page.route('**/livewire-*/update', lambda route: route.fulfill(status=419, body='expired fixture session'))
    page.locator('[data-deployment-refresh]').click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('HTTP 419')")
    assert 'expired fixture session' not in page.locator('#deployment-status').inner_text()
    page.unroute('**/livewire-*/update')
    page.locator('[data-deployment-refresh]').click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('آخرین بررسی')")
    print('Real panel: token presence indicator, failed request feedback, and actual Livewire status refresh passed.', flush=True)
    page.get_by_role('button', name='ساخت کد بازیابی', exact=True).click()
    modal = page.locator('.fi-modal-window:visible')
    modal.wait_for()
    assert not (private / 'recovery.token').exists() or 'کد بازیابی جدید' not in page.locator('main').inner_text()
    modal.get_by_role('button', name='تایید', exact=True).click()
    page.wait_for_function("document.body.textContent.includes('کد بازیابی جدید')")
    page.get_by_role('button', name='دریافت و نصب آخرین نسخه', exact=True).click()
    modal.wait_for()
    assert len(installs) == 0
    modal.get_by_role('button', name='تایید', exact=True).click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('در حال دریافت و آماده‌سازی نسخه از گیت‌هاب')", timeout=10000)
    print('Real confirmation and immediate preparation feedback passed.', flush=True)
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('نسخهٔ جدید فعال شد')", timeout=240000)
    assert len(installs) == 1
    state = json.loads((private / 'deployment-job.json').read_text())
    active = json.loads((private / 'active-release.json').read_text())
    assert state['state'] == 'completed' and active['current'] != old
    assert (private / 'deployments' / (active['current'] + '.sql')).stat().st_size > 0
    assert hashlib.sha256((private / '.env').read_bytes()).hexdigest() == environment
    page.locator('[data-deployment-refresh]').click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('آخرین بررسی')")
    assert len(installs) == 1
    page.goto(base + '/admin/manage-deployment', wait_until='networkidle')
    assert page.locator('#deployment-status').inner_text()
    page.set_viewport_size({'width': 1440, 'height': 1000})
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
    assert not errors, errors
    print('Real GitHub deployment: one install, SQL backup, migrations, activation, preserved environment, cold reload, desktop/mobile and status after completion passed.', flush=True)
    browser.close()
