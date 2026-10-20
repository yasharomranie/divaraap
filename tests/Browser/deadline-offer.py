import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/deadline-offer-review.json').read_text())
base = 'http://localhost:8000'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    page.clock.install()
    errors, clicks = [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    def request(req):
        if '/advertising/events' in req.url and req.method == 'POST' and req.post_data_json.get('kind') == 'click': clicks.append(req.post_data_json)
    page.on('request', request)
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    timed = card.locator('[data-ad-deadline]')
    card.scroll_into_view_if_needed()
    assert card.get_by_text('آگهی آزمایش پیشنهاد زمان‌دار', exact=True).is_visible()
    assert timed.get_attribute('data-deadline') == state['deadline']
    assert 'باقی‌مانده:' in card.locator('[data-deadline-remaining]').inner_text()
    assert 'PRIVATE DEADLINE OWNER' not in card.inner_text()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert card.is_visible()
    page.set_viewport_size({'width':390,'height':844})
    link = card.locator('a')
    link.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert link.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    card.screenshot(path='/tmp/deadline-offer-mobile.png')
    link.click()
    page.wait_for_url(base+f'/listings/{state["listing"]}')
    assert clicks
    page.goto(base, wait_until='networkidle')
    assert timed.get_attribute('data-deadline') == state['deadline']
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('پیشنهاد دارای مهلت واقعی', exact=True).count() > 0
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile', wait_until='networkidle')
    assert page.locator('[data-ad-deadline]').count() == 1
    assert page.locator('[data-behavior="advertising-deadline"]').count() == 0
    assert page.locator('[data-banner] a').count() == 0
    assert page.locator('[data-event-token]').count() == 0
    native = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844}).new_page()
    native.goto(base, wait_until='networkidle')
    assert native.locator(f'[data-banner="{state["banner"]}"] time').get_attribute('datetime') == state['deadline']
    native.locator(f'[data-banner="{state["banner"]}"] a').click()
    native.wait_for_url(base+f'/listings/{state["listing"]}')
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert 'Remaining:' in card.locator('[data-deadline-remaining]').inner_text()
    duration = timed.evaluate('(e)=>Date.parse(e.dataset.deadline)-Date.parse(e.dataset.serverNow)')
    page.clock.fast_forward(duration-45000)
    assert 'less than a minute' in card.locator('[data-deadline-remaining]').inner_text()
    wall = page.evaluate('Date.now()')
    page.clock.set_system_time(wall-3600000)
    page.evaluate('document.dispatchEvent(new Event("visibilitychange"))')
    assert 'less than a minute' in card.locator('[data-deadline-remaining]').inner_text()
    page.clock.fast_forward(60000)
    assert card.is_hidden()
    assert card.locator('a').get_attribute('href') is None
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/deadline-offer-fixtures.php','expire'], check=True)
    native.goto(base, wait_until='networkidle')
    assert native.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: fixed absolute deadline across reload/save, tracked/native listing links, real admin save, untracked preview, clock rollback without reset, expiry hides/disables CTA, server suppression without JavaScript, RTL/LTR, font, focus and four widths.')
