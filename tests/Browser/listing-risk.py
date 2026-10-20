import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/listing-risk-review.json').read_text())
base = 'http://localhost:8000'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base+'/admin/operations', wait_until='networkidle')
    page.get_by_role('link', name='بررسی آگهی‌های مشکوک و تکراری', exact=True).last.click()
    page.wait_for_url(base+'/admin/listing-risk-review')
    for listing_id in state['listings']:
        assert page.locator(f'[data-risk-listing="{listing_id}"]').count() == 1
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    card = page.locator(f'[data-risk-listing="{state["sensitive"]}"]')
    card.locator('summary').click()
    assert card.get_by_text('لطفاً کد تایید را ارسال کنید.', exact=True).is_visible()
    button = page.get_by_role('button', name='بررسی دوباره', exact=True)
    button.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert button.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    page.evaluate('window.scrollTo(0,0)')
    page.screenshot(path='/tmp/listing-risk-mobile.png', full_page=True)
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/listing-risk-fixtures.php','clear-signal'], check=True)
    button.click()
    page.locator(f'[data-risk-listing="{state["sensitive"]}"]').wait_for(state='detached')
    moderator = browser.new_context(viewport={'width':390,'height':844})
    peer = moderator.new_page()
    peer.goto(base+'/login', wait_until='networkidle')
    peer.locator('#email').fill(state['email'])
    peer.locator('#password').fill('risk-fixture-password')
    peer.locator('button[type=submit]').click()
    peer.wait_for_load_state('networkidle')
    peer.goto(base+'/lang/fa', wait_until='networkidle')
    peer.goto(base+'/admin/listing-risk-review', wait_until='networkidle')
    assert peer.locator(f'[data-risk-listing="{state["listings"][0]}"]').is_visible()
    assert peer.get_by_role('link', name='بررسی و ویرایش آگهی', exact=True).count() == 0
    assert peer.locator(f'a[href="#risk-listing-{state["listings"][1]}"]').count() > 0
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base+'/admin/listing-risk-review', wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.get_by_role('heading', name='Listing risk review', exact=True).is_visible()
    guest = browser.new_context()
    anonymous = guest.new_page()
    anonymous.goto(base+'/admin/listing-risk-review', wait_until='networkidle')
    assert '/admin/login' in anonymous.url
    assert not errors, errors
    browser.close()
print('PASS: operations entry, duplicate evidence and protected source text, real refresh after content edit, moderator read access without unrestricted edit links, guest isolation, RTL/LTR, font, focus and four widths.')
