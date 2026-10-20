import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/schedule-review.json').read_text())
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror',lambda error:errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    url=f'http://localhost:8000/panel/my-listings/{state["listing"]}/schedule'
    page.goto(url,wait_until='networkidle')
    page.locator('#listing-publish-at').fill(state['input'])
    page.once('dialog',lambda dialog:dialog.dismiss())
    page.get_by_role('button',name='ذخیرهٔ موعد انتشار',exact=True).click()
    assert page.locator('[name="_method"][value="DELETE"]').count()==0
    page.once('dialog',lambda dialog:dialog.accept())
    page.get_by_role('button',name='ذخیرهٔ موعد انتشار',exact=True).click()
    page.get_by_role('button',name='لغو زمان‌بندی',exact=True).wait_for()
    assert page.locator('#listing-publish-at').input_value()==state['input']
    assert page.get_by_text('در انتظار موعد انتشار',exact=True).is_visible()
    guest=browser.new_context()
    assert guest.request.get(f'http://localhost:8000/listings/{state["listing"]}').status==404
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('#listing-publish-at').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.evaluate('getComputedStyle(document.activeElement).outlineStyle!=="none"')
    Path('/tmp/schedule-review').mkdir(exist_ok=True)
    page.screenshot(path='/tmp/schedule-review/mobile.png',full_page=True)
    native=browser.new_context(java_script_enabled=False,storage_state=context.storage_state())
    plain=native.new_page()
    plain.goto(url,wait_until='networkidle')
    plain.get_by_role('button',name='لغو زمان‌بندی',exact=True).click()
    plain.wait_for_load_state('networkidle')
    assert plain.get_by_text('متوقف‌شده',exact=True).is_visible()
    assert guest.request.get(f'http://localhost:8000/listings/{state["listing"]}').status==404
    plain.locator('#listing-publish-at').fill(state['input'])
    plain.get_by_role('button',name='ذخیرهٔ موعد انتشار',exact=True).click()
    plain.wait_for_load_state('networkidle')
    assert plain.get_by_text('در انتظار موعد انتشار',exact=True).is_visible()
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/listing-schedule-fixtures.php','due'],check=True)
    assert guest.request.get(f'http://localhost:8000/listings/{state["listing"]}').status==200
    page.goto(f'http://localhost:8000/panel/my-listings/{state["pending"]}/schedule',wait_until='networkidle')
    page.locator('#listing-publish-at').fill(state['input'])
    page.get_by_role('button',name='ذخیرهٔ موعد انتشار',exact=True).click()
    page.get_by_role('button',name='لغو زمان‌بندی',exact=True).wait_for()
    assert page.get_by_text('این آگهی هنوز تأیید نشده است؛ رسیدن موعد به‌تنهایی آن را منتشر نمی‌کند.',exact=True).is_visible()
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert page.get_by_role('heading',name='Schedule publication',exact=True).is_visible()
    assert not errors,errors
    browser.close()
print('PASS: Tehran date input, save confirmation/cancel, hidden future listing, native cancel/reschedule, actual due-time publication on guest request, pending approval, keyboard, RTL/LTR and four widths.')
