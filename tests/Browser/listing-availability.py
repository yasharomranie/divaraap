import json
from pathlib import Path
from urllib.parse import urlencode
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/availability-review.json').read_text())
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    query = urlencode({'search':state['title']})
    url = 'http://localhost:8000/panel/my-listings?'+query
    page.goto(url,wait_until='networkidle')
    row = page.locator(f'[data-listing-id="{state["listing"]}"]')
    row.get_by_role('button',name='توقف موقت نمایش',exact=True).wait_for()
    page.once('dialog',lambda dialog:dialog.dismiss())
    row.get_by_role('button',name='توقف موقت نمایش',exact=True).click()
    assert row.get_by_role('button',name='توقف موقت نمایش',exact=True).is_visible()
    page.once('dialog',lambda dialog:dialog.accept())
    row.get_by_role('button',name='توقف موقت نمایش',exact=True).click()
    row.get_by_role('button',name='ادامهٔ نمایش',exact=True).wait_for()
    assert row.get_by_text('متوقف‌شده',exact=True).is_visible()
    guest = browser.new_context()
    assert guest.request.get(f'http://localhost:8000/listings/{state["listing"]}').status == 404
    response = context.request.get(f'http://localhost:8000/listings/{state["listing"]}')
    assert response.status == 200
    assert 'no-store' in response.headers['cache-control']
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.goto(f'http://localhost:8000/panel/my-listings/{state["listing"]}/edit',wait_until='networkidle')
    assert page.locator('#status').input_value() == 'paused'
    assert page.locator('#status option[value="active"]').is_disabled()
    page.locator('.listing-availability button').first.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.evaluate('getComputedStyle(document.activeElement).outlineStyle!=="none"')
    Path('/tmp/availability-review').mkdir(exist_ok=True)
    page.screenshot(path='/tmp/availability-review/mobile.png',full_page=True)
    native = browser.new_context(java_script_enabled=False, storage_state=context.storage_state())
    plain = native.new_page()
    plain.goto(url,wait_until='networkidle')
    plain.locator(f'[data-listing-id="{state["listing"]}"]').get_by_role('button',name='ادامهٔ نمایش',exact=True).click()
    plain.wait_for_load_state('networkidle')
    assert guest.request.get(f'http://localhost:8000/listings/{state["listing"]}').status == 200
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    page.once('dialog',lambda dialog:dialog.accept())
    page.locator(f'[data-listing-id="{state["listing"]}"]').get_by_role('button',name='Pause display',exact=True).click()
    page.get_by_role('button',name='Resume display',exact=True).wait_for()
    assert not errors, errors
    browser.close()
print('PASS: pause confirmation/cancel, owner-only preview, public visibility restored by native resume, protected editor, keyboard focus, RTL/LTR and four responsive widths.')
