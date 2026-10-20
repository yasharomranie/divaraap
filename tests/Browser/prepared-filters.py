import json
import subprocess
from pathlib import Path
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/prepared-filter-review.json').read_text())
base = 'http://localhost:8000'
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
    assert card.locator('a').count() == 2
    assert card.get_by_text('تا دو هزار ریال', exact=True).is_visible()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        for link in card.locator('a').all():
            assert link.bounding_box()['height'] >= 48
    page.set_viewport_size({'width':390,'height':844})
    link = card.locator('a').nth(1)
    link.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert link.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    key = link.get_attribute('data-ad-choice')
    card.screenshot(path='/tmp/prepared-filters-mobile.png')
    link.click()
    page.wait_for_url('**/listings?**')
    query = parse_qs(urlparse(page.url).query)
    assert query['min_price'] == ['3000.00'] and query['city'] == [str(state['city'])] and query['country'] == [str(state['country'])]
    assert clicks and clicks[-1]['choice'] == key
    assert page.get_by_text('آگهی ویژه آزمایش فیلتر', exact=True).count() > 0
    assert page.get_by_text('آگهی ارزان آزمایش فیلتر', exact=True).count() == 0
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('بنر تعاملی با فیلتر آماده', exact=True).count() > 0
    fields = page.get_by_label('عنوان انتخاب', exact=False)
    assert fields.count() == 2
    fields.nth(1).fill('انتخاب ویژه تازه')
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base, wait_until='networkidle')
    assert card.get_by_text('انتخاب ویژه تازه', exact=True).is_visible()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile', wait_until='networkidle')
    assert page.locator('[data-ad-filters]').count() == 1
    assert page.locator('[data-banner] a').count() == 0
    assert page.locator('[data-event-token]').count() == 0
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert card.get_by_text('Choose an option to open results with the filters shown below.', exact=True).is_visible()
    native = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844}).new_page()
    native.goto(base, wait_until='networkidle')
    native.locator(f'[data-banner="{state["banner"]}"] a').first.click()
    native.wait_for_url('**/listings?**')
    assert parse_qs(urlparse(native.url).query)['max_price'] == ['2000.00']
    assert native.get_by_text('آگهی ارزان آزمایش فیلتر', exact=True).count() > 0
    assert native.get_by_text('آگهی ویژه آزمایش فیلتر', exact=True).count() == 0
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/prepared-filter-fixtures.php','disable-city'], check=True)
    page.goto(base, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: second-choice tracked click and actual filtered results, native search without JavaScript, real admin edit, safe preview, unavailable city suppression, RTL/LTR, font, focus and four widths.')
