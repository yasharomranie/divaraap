import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/shop-card-review.json').read_text())
base = 'http://localhost:8000'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    card.scroll_into_view_if_needed()
    assert card.get_by_text('فروشگاه محلی آزمایش کارت', exact=True).is_visible()
    assert 'PRIVATE SHOP OWNER' not in card.inner_text()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert card.is_visible()
    page.set_viewport_size({'width':390,'height':844})
    card.scroll_into_view_if_needed()
    link = card.locator('a').first
    link.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert link.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    page.screenshot(path='/tmp/shop-card-mobile.png', full_page=True)
    link.click()
    page.wait_for_url(base+f'/sellers/{state["seller"]}')
    assert page.get_by_text('فروشگاه محلی آزمایش کارت', exact=True).count() > 0
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/shop-card-fixtures.php','change-shop'], check=True)
    page.goto(base, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('کارت فروشگاه محلی', exact=True).count() > 0
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base, wait_until='networkidle')
    assert page.get_by_text('فروشگاه تازه آزمایش کارت', exact=True).is_visible()
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.locator(f'[data-banner="{state["banner"]}"]').get_by_text('View shop', exact=True).is_visible()
    assert not errors, errors
    browser.close()
print('PASS: real sponsored shop card, direct existing-shop destination, public-only copy, suppression after shop changes, real admin save refresh, RTL/LTR, font, focus and four widths.')
