import json
from pathlib import Path
from urllib.parse import urlencode
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/bulk-review.json').read_text())
url = 'http://localhost:8000/panel/my-listings?' + urlencode({'search':'آزمون گروهی مرورگر'})
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('[data-bulk-choice]').count()==2
    assert page.locator('[data-bulk-preview]').is_disabled()
    page.locator('[data-bulk-all]').click()
    assert page.locator('[data-bulk-choice]:checked').count()==2
    page.locator('[data-bulk-clear]').click()
    assert page.locator('[data-bulk-choice]:checked').count()==0
    page.locator('[data-bulk-all]').click()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('[data-bulk-choice]').first.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.evaluate('getComputedStyle(document.activeElement).outlineStyle!="none"')
    page.locator('[data-bulk-preview]').click()
    page.wait_for_load_state('networkidle')
    assert page.get_by_role('heading',name='تأیید عملیات گروهی',exact=True).is_visible()
    guest = browser.new_context()
    for listing in state['ids']:
        assert guest.request.get(f'http://localhost:8000/listings/{listing}').status==200
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.screenshot(path='/tmp/bulk-review-mobile.png',full_page=True)
    page.locator('[name=confirmed]').check()
    page.get_by_role('button',name='اجرای عملیات روی آگهی‌های انتخاب‌شده',exact=True).click()
    page.wait_for_load_state('networkidle')
    for listing in state['ids']:
        assert guest.request.get(f'http://localhost:8000/listings/{listing}').status==404
    native = browser.new_context(java_script_enabled=False,storage_state=context.storage_state(),viewport={'width':390,'height':844})
    plain = native.new_page()
    plain.goto(url,wait_until='networkidle')
    for choice in plain.locator('[data-bulk-choice]').all(): choice.check()
    plain.locator('#bulk-action').select_option('resume')
    plain.locator('[data-bulk-preview]').click()
    plain.wait_for_load_state('networkidle')
    plain.locator('[name=confirmed]').check()
    plain.get_by_role('button',name='اجرای عملیات روی آگهی‌های انتخاب‌شده',exact=True).click()
    plain.wait_for_load_state('networkidle')
    for listing in state['ids']:
        assert guest.request.get(f'http://localhost:8000/listings/{listing}').status==200
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    page.locator('[data-bulk-all]').click()
    page.locator('#bulk-action').select_option('sold')
    page.locator('[data-bulk-preview]').click()
    page.wait_for_load_state('networkidle')
    assert page.get_by_role('heading',name='Confirm bulk action',exact=True).is_visible()
    page.locator('[name=confirmed]').check()
    page.get_by_role('button',name='Apply to selected listings',exact=True).click()
    page.wait_for_load_state('networkidle')
    page.goto(url,wait_until='networkidle')
    page.locator('[data-bulk-all]').click()
    page.locator('#bulk-action').select_option('delete')
    page.locator('[data-bulk-preview]').click()
    page.wait_for_load_state('networkidle')
    page.get_by_role('button',name='Apply to selected listings',exact=True).click()
    assert page.locator('[name=confirmed]').evaluate('(e)=>!e.validity.valid')
    page.locator('[name=confirmed]').check()
    page.get_by_role('button',name='Apply to selected listings',exact=True).click()
    page.wait_for_load_state('networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('[data-bulk-choice]').count()==0
    assert not errors,errors
    browser.close()
print('PASS: selection/count/clear, review without mutation, pause, native resume, sold, confirmed delete, keyboard, RTL/LTR and four widths.')
