import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/service-card-review.json').read_text())
base = 'http://localhost:8000'
request_url = base+f'/listings/{state["listing"]}?request-service=1#service-request'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors, clicks = [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    def request(req):
        if '/advertising/events' in req.url and req.method == 'POST' and req.post_data_json.get('kind') == 'click': clicks.append(req.post_data_json)
    page.on('request', request)
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    card.scroll_into_view_if_needed()
    assert card.get_by_text('درخواست خدمات', exact=True).is_visible()
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
    card.screenshot(path='/tmp/service-card-mobile.png')
    link.click()
    page.wait_for_url(request_url)
    assert clicks
    assert page.get_by_role('link', name='ورود برای درخواست خدمات', exact=True).is_visible()
    assert page.locator('#service-request-message').count() == 0
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('کارت درخواست خدمات', exact=True).count() > 0
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile', wait_until='networkidle')
    assert page.locator('[data-ad-service]').count() == 1
    assert page.locator('[data-banner] a').count() == 0
    assert page.locator('[data-event-token]').count() == 0
    buyer = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844})
    native = buyer.new_page()
    native.goto(base+'/login', wait_until='networkidle')
    native.locator('#email').fill(state['email'])
    native.locator('#password').fill('service-fixture-password')
    native.locator('button[type=submit]').click()
    native.wait_for_load_state('networkidle')
    native.goto(base+'/lang/fa', wait_until='networkidle')
    native.goto(request_url, wait_until='networkidle')
    form = native.locator('#service-request form')
    field = native.get_by_label('چه خدمتی نیاز دارید؟', exact=True)
    assert not field.evaluate('(e)=>e.validity.valid')
    field.fill('درخواست تعمیر صندلی آزمایش مرورگر')
    for width in [360,390,768,1440]:
        native.set_viewport_size({'width':width,'height':900})
        assert native.evaluate('document.documentElement.scrollWidth <= innerWidth')
    native.set_viewport_size({'width':390,'height':844})
    native.locator('#service-request').screenshot(path='/tmp/service-request-mobile.png')
    form.get_by_role('button', name='ارسال درخواست در پیام‌رسان', exact=True).click()
    native.wait_for_url('**/panel/inbox?conversation=*')
    assert native.get_by_text('درخواست تعمیر صندلی آزمایش مرورگر', exact=True).count() > 0
    native.goto(base+'/lang/en', wait_until='networkidle')
    native.goto(request_url, wait_until='networkidle')
    assert native.locator('html').get_attribute('dir') == 'ltr'
    assert native.get_by_label('What service do you need?', exact=True).is_visible()
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/service-card-fixtures.php','pause'], check=True)
    page.goto(base, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: real service card click, guest guard, admin save, untracked preview, native authenticated request and inbox message, suppression, RTL/LTR, font, focus and four widths.')
