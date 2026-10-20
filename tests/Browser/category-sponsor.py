import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/category-sponsor-review.json').read_text())
base = 'http://localhost:8000'
category_url = base+f'/listings?category={state["category"]}'
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
    page.goto(category_url, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    card.scroll_into_view_if_needed()
    assert card.get_by_text('فروشگاه محلی آزمایش حمایت', exact=True).is_visible()
    assert 'PRIVATE SPONSOR OWNER' not in card.inner_text()
    assert card.get_by_text('تبلیغات است؛ تأیید فروشگاه یا تغییر رتبه آگهی‌ها نیست.', exact=True).is_visible()
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
    card.screenshot(path='/tmp/category-sponsor-mobile.png')
    link.click()
    page.wait_for_url(base+f'/sellers/{state["seller"]}')
    assert clicks
    for path in ['', '/listings', f'/listings?category={state["child"]}']:
        page.goto(base+path, wait_until='networkidle')
        assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('حامی تبلیغاتی دسته', exact=True).count() > 0
    assert page.get_by_text('دسته‌بندی ـ حامی بالای نتایج', exact=True).count() > 0
    page.get_by_label('عنوان داخلی', exact=False).fill('عنوان تازه حامی آزمایشی')
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(category_url, wait_until='networkidle')
    assert card.get_by_text('عنوان تازه حامی آزمایشی', exact=True).is_visible()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile&placement=category-top', wait_until='networkidle')
    assert page.locator('[data-ad-sponsor]').count() == 1
    assert page.locator('[data-banner] a').count() == 0
    assert page.locator('[data-event-token]').count() == 0
    native = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844}).new_page()
    native.goto(category_url, wait_until='networkidle')
    native.locator(f'[data-banner="{state["banner"]}"] a').click()
    native.wait_for_url(base+f'/sellers/{state["seller"]}')
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(category_url, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert card.get_by_text('Advertising, without shop endorsement or listing ranking changes.', exact=True).is_visible()
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/category-sponsor-fixtures.php','change-shop'], check=True)
    page.goto(category_url, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: sponsored empty-category header, exact-category isolation, tracked shop click, native link without JavaScript, real admin save, safe preview, snapshot suppression, RTL/LTR, font, focus and four widths.')
