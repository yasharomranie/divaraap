import io
from pathlib import Path
from PIL import Image
from playwright.sync_api import sync_playwright

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    errors = []
    def login():
        context = browser.new_context(viewport={'width':390, 'height':844})
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto('http://localhost:8000/admin/login', wait_until='networkidle')
        page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
        page.wait_for_url('http://localhost:8000/admin')
        page.goto('http://localhost:8000/lang/fa', wait_until='networkidle')
        page.goto('http://localhost:8000/panel/create-listing', wait_until='networkidle')
        return context, page
    def component(page):
        return page.locator('.quick-create').evaluate('el=>{while(el&&!el.hasAttribute("wire:id"))el=el.parentElement;return el.getAttribute("wire:id");}')
    first_context, first = login()
    if first.evaluate('id=>Livewire.find(id).get("listingTitle")', component(first)) == 'نسخه جدید دستگاه دوم':
        first.once('dialog', lambda dialog: dialog.accept())
        first.get_by_role('button', name='حذف پیش‌نویس', exact=True).click()
        first.get_by_text('پیش‌نویس هنوز ذخیره نشده', exact=True).wait_for()
    assert first.get_by_text('پیش‌نویس هنوز ذخیره نشده', exact=True).is_visible(), 'Use an account with no existing draft.'
    image = io.BytesIO()
    Image.new('RGB', (240,180), '#247ac0').save(image, format='PNG')
    first.locator('#quick-photos').set_input_files({'name':'private-draft.png','mimeType':'image/png','buffer':image.getvalue()})
    first.get_by_text('پیش‌نویس در حساب شما ذخیره شد', exact=True).wait_for()
    first.evaluate('async id => { await Livewire.find(id).call("goToStep",3); }', component(first))
    first.locator('#listing-title').fill('پیش‌نویس مرورگر خصوصی')
    first.locator('#listing-description').fill('متن آزمایشی برای بازیابی در دستگاه دیگر')
    first.locator('.quick-create__draft button').first.click()
    first.wait_for_function('id=>Livewire.find(id).get("draftVersion")>=3', arg=component(first))
    second_context, second = login()
    assert second.locator('#listing-title').input_value() == 'پیش‌نویس مرورگر خصوصی'
    assert second.locator('#listing-description').input_value() == 'متن آزمایشی برای بازیابی در دستگاه دیگر'
    for width in [360,390,768,1440]:
        second.set_viewport_size({'width':width, 'height':900})
        assert second.evaluate('document.documentElement.scrollWidth <= innerWidth')
    second.set_viewport_size({'width':390, 'height':844})
    second.locator('#listing-title').fill('نسخه جدید دستگاه دوم')
    second.locator('.quick-create__draft button').first.click()
    second.wait_for_function('id=>Livewire.find(id).get("listingTitle")==="نسخه جدید دستگاه دوم"', arg=component(second))
    first.locator('#listing-title').fill('تغییر قدیمی دستگاه اول')
    first.locator('.quick-create__draft button').first.click()
    first.get_by_text('پیش‌نویس در جای دیگری تغییر کرده است. برای ادامه نسخهٔ تازه را باز کنید.', exact=True).wait_for()
    first.on('dialog', lambda dialog: dialog.accept())
    first.get_by_role('button', name='باز کردن نسخهٔ تازه', exact=True).click()
    first.wait_for_function('id=>Livewire.find(id).get("listingTitle")==="نسخه جدید دستگاه دوم"', arg=component(first))
    first.evaluate('async id => { await Livewire.find(id).call("goToStep",1); }', component(first))
    photo = first.locator('[wire\\:key^="saved-photo-"] img')
    photo.wait_for()
    first.wait_for_function('()=>{const el=document.querySelector(".quick-create__thumbnail img");return el&&el.complete&&el.naturalWidth>0;}')
    url = photo.get_attribute('src')
    guest = browser.new_context()
    response = guest.request.get(url, max_redirects=0)
    assert response.status in [302,303]
    first.locator('.quick-create__draft button').first.focus()
    first.keyboard.press('Tab')
    first.keyboard.press('Shift+Tab')
    assert first.evaluate('getComputedStyle(document.activeElement).outlineStyle!=="none"')
    Path('/tmp/draft-review').mkdir(exist_ok=True)
    first.screenshot(path='/tmp/draft-review/mobile.png', full_page=True)
    first.get_by_role('button', name='حذف پیش‌نویس', exact=True).click()
    first.get_by_text('پیش‌نویس هنوز ذخیره نشده', exact=True).wait_for()
    first.reload(wait_until='networkidle')
    assert first.get_by_text('پیش‌نویس هنوز ذخیره نشده', exact=True).is_visible()
    assert first.locator('html').get_attribute('dir') == 'rtl'
    first.goto('http://localhost:8000/lang/en', wait_until='networkidle')
    first.goto('http://localhost:8000/panel/create-listing', wait_until='networkidle')
    assert first.locator('html').get_attribute('dir') == 'ltr'
    first.get_by_role('button', name='Save draft', exact=True).wait_for()
    assert first.evaluate('document.documentElement.scrollWidth <= innerWidth')
    assert not errors, errors
    browser.close()
print('PASS: upload, autosave, two authenticated browser contexts, version conflict/reload, private preview, confirmed deletion, four responsive widths and keyboard focus.')
