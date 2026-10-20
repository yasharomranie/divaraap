import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/listing-collection-review.json').read_text())
base = 'http://localhost:8000'
def fixture(action):
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/listing-collection-fixtures.php',action], check=True)
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors, clicks = [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    def request(req):
        if '/advertising/events' in req.url and req.method == 'POST':
            data = req.post_data_json
            if data.get('kind') == 'click': clicks.append(data)
    page.on('request', request)
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    card.scroll_into_view_if_needed()
    assert card.locator('a').count() == 3
    assert card.get_by_text('مجموعه منتخب آزمایشی', exact=True).is_visible()
    assert 'PRIVATE COLLECTION OWNER' not in card.inner_text()
    assert page.locator('html').get_attribute('dir') == 'rtl'
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert card.is_visible()
        assert len(card.locator('.advertising-collection__grid').evaluate('(e)=>getComputedStyle(e).gridTemplateColumns').split()) == (2 if width < 768 else 3)
    page.set_viewport_size({'width':390,'height':844})
    link = card.locator('a').nth(1)
    link.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert link.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    card.screenshot(path='/tmp/listing-collection-mobile.png')
    link.click()
    page.wait_for_url(base+f'/listings/{state["listings"][1]}')
    assert clicks and clicks[-1]['listing'] == state['listings'][1]
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('مجموعه آگهی تبلیغ‌شده', exact=True).count() > 0
    assert page.get_by_text('آگهی‌های مجموعه', exact=False).count() > 0
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile&placement=home-top', wait_until='networkidle')
    assert page.locator('[data-ad-collection] a').count() == 0
    assert page.locator('[data-event-token]').count() == 0
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert card.get_by_text('3 listings in this collection', exact=True).is_visible()
    nojs = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844})
    native = nojs.new_page()
    native.goto(base, wait_until='networkidle')
    native.locator(f'[data-ad-listing="{state["listings"][2]}"]').first.click()
    native.wait_for_url(base+f'/listings/{state["listings"][2]}')
    fixture('pause')
    page.goto(base, wait_until='networkidle')
    assert card.locator('a').count() == 2
    assert card.locator(f'[data-ad-listing="{state["listings"][0]}"]').count() == 0
    fixture('pause-two')
    page.goto(base, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: real collection, second-card tracked click, native links without JavaScript, live availability, admin save, safe preview, RTL/LTR, font, keyboard focus and four widths.')
