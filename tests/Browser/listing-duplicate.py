import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/duplicate-review.json').read_text())
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error:errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    url = f'http://localhost:8000/panel/my-listings/{state["listing"]}/duplicate'
    page.goto(url,wait_until='networkidle')
    assert page.get_by_role('heading',name='ساخت آگهی مشابه',exact=True).is_visible()
    assert not page.locator('[name="photos"]').is_checked()
    assert not page.locator('[name="location"]').is_checked()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('.listing-duplicate__text').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.evaluate('getComputedStyle(document.activeElement).outlineStyle!=="none"')
    Path('/tmp/duplicate-review').mkdir(exist_ok=True)
    page.screenshot(path='/tmp/duplicate-review/mobile.png',full_page=True)
    native = browser.new_context(java_script_enabled=False,storage_state=context.storage_state())
    plain = native.new_page()
    plain.goto(url,wait_until='networkidle')
    plain.locator('[name="photos"]').check()
    plain.get_by_role('button',name='ساخت پیش‌نویس تازه',exact=True).click()
    plain.wait_for_url('**/panel/create-listing')
    page.goto(url,wait_until='networkidle')
    assert page.get_by_role('link',name='ادامهٔ پیش‌نویس فعلی',exact=True).is_visible()
    assert page.locator('[name="source_version"]').count() == 0
    page.get_by_role('link',name='ادامهٔ پیش‌نویس فعلی',exact=True).click()
    page.locator('[wire\\:key^="saved-photo-"] img').wait_for()
    cid = page.locator('.quick-create').evaluate('el=>{while(el&&!el.hasAttribute("wire:id"))el=el.parentElement;return el.getAttribute("wire:id");}')
    assert page.evaluate('id=>Livewire.find(id).get("latitude")',cid) == ''
    page.evaluate('async id=>await Livewire.find(id).call("goToCategoryStep")',cid)
    page.evaluate('async id=>await Livewire.find(id).call("goToDetailsStep")',cid)
    assert page.locator('#listing-title').input_value() == state['title']
    page.locator('#listing-title').fill('پیش‌نویس مشابه قابل ویرایش')
    page.locator('.quick-create__draft button').first.click()
    page.get_by_text('پیش‌نویس در حساب شما ذخیره شد',exact=True).wait_for()
    page.once('dialog',lambda dialog:dialog.accept())
    page.get_by_role('button',name='حذف پیش‌نویس',exact=True).click()
    page.get_by_text('پیش‌نویس هنوز ذخیره نشده',exact=True).wait_for()
    page.goto(url,wait_until='networkidle')
    assert page.locator('[name="source_version"]').count() == 1
    assert page.locator('dd').first.inner_text() == state['title']
    assert page.locator('[name="photos"]').count() == 1
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(url,wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.get_by_role('heading',name='Create similar listing',exact=True).is_visible()
    assert not errors,errors
    browser.close()
print('PASS: review, optional private photo copy, location opt-in, native POST, existing draft protection, wizard continuation/edit/discard, keyboard focus, RTL/LTR and four widths.')
