import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/guide-ad-review.json').read_text())
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
    assert 'راهنمای خرید امن' in card.inner_text()
    assert card.locator('a').count() == 1
    assert card.locator('a').get_attribute('href') == base+'/pages/'+state['slug']
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert card.is_visible()
    page.set_viewport_size({'width':390,'height':844})
    card.locator('a').focus()
    assert card.locator('a').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    card.screenshot(path='/tmp/guide-ad-mobile.png')
    card.locator('a').click()
    page.wait_for_url(base+'/pages/'+state['slug'])
    assert page.get_by_role('heading', name='راهنمای خرید امن').is_visible()
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('صفحه راهنمای منتشرشده', exact=False).first.is_visible()
    page.get_by_label('عنوان', exact=False).first.fill('کارت راهنمای خرید آزمایش')
    page.get_by_role('button',name='ذخیره',exact=True).click()
    page.get_by_text('ذخیره شد',exact=True).wait_for(state='visible')
    page.goto(base+'/lang/en',wait_until='networkidle')
    page.goto(base,wait_until='networkidle')
    assert page.locator(f'[data-ad-guide="{state["page"]}"]').first.is_visible()
    assert page.locator('html').get_attribute('dir') == 'ltr'
    nojs = browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844})
    np = nojs.new_page()
    np.goto(base,wait_until='networkidle')
    assert np.locator(f'[data-ad-guide="{state["page"]}"]').first.is_visible()
    assert np.locator(f'[data-banner="{state["banner"]}"] a').first.get_attribute('href') == base+'/pages/'+state['slug']
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/guide-ad-fixtures.php','change-guide'],check=True)
    page.goto(base,wait_until='networkidle')
    assert page.locator(f'[data-ad-guide="{state["page"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: guide CTA, published page, admin save, RTL/LTR, 4 widths, font, focus, no-JS, stale suppression')
