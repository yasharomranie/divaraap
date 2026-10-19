import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/saved-replies-review.json').read_text())
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa', wait_until='networkidle')
    page.goto('http://localhost:8000/panel/saved-replies', wait_until='networkidle')
    editor = page.locator(f'[data-reply-id="{state["reply"]}"]')
    editor.locator('summary').click()
    editor.locator('input[name="title"]').fill('هماهنگی بازدید آزمایشی')
    expected = 'سلام، برای هماهنگی بازدید پیام بدهید.\nلطفاً زمان پیشنهادی را بفرستید.'
    editor.locator('textarea').fill(expected)
    editor.get_by_role('button', name='ذخیره', exact=True).click()
    page.wait_for_load_state('networkidle')
    assert page.get_by_text('پاسخ آماده ذخیره شد.', exact=True).is_visible()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    native = browser.new_context(java_script_enabled=False, storage_state=context.storage_state())
    plain = native.new_page()
    plain.goto('http://localhost:8000/panel/saved-replies', wait_until='networkidle')
    plain.locator(f'[data-reply-id="{state["reply"]}"] summary').click()
    assert plain.locator(f'#reply-{state["reply"]}-body').input_value() == expected
    plain.locator(f'#reply-{state["reply"]}-position').fill('3')
    plain.locator(f'[data-reply-id="{state["reply"]}"]').get_by_role('button', name='ذخیره', exact=True).click()
    plain.wait_for_load_state('networkidle')
    assert plain.get_by_text('پاسخ آماده ذخیره شد.', exact=True).is_visible()
    url = f'http://localhost:8000/panel/inbox?conversation={state["conversation"]}'
    page.goto(url, wait_until='networkidle')
    picker = page.locator('.saved-reply-picker')
    picker.locator('summary').first.click()
    picker.locator('.saved-reply-picker__entry summary').filter(has_text='هماهنگی بازدید آزمایشی').click()
    before = page.locator('[data-message-id]').count()
    page.locator('[data-thread-input]').fill('متن دستی من')
    page.once('dialog', lambda dialog:dialog.dismiss())
    picker.get_by_role('button',name='استفاده در پیام',exact=True).first.click()
    assert page.locator('[data-thread-input]').input_value() == 'متن دستی من'
    page.once('dialog',lambda dialog:dialog.accept())
    picker.get_by_role('button',name='استفاده در پیام',exact=True).first.click()
    assert page.locator('[data-thread-input]').input_value() == expected
    assert page.locator('[data-message-id]').count() == before
    assert page.locator('[data-thread-input]').evaluate('el=>el===document.activeElement')
    page.locator('[data-thread-input]').fill(expected+' آزمایش ارسال')
    page.locator('[data-thread-submit]').click()
    page.wait_for_function('(count)=>document.querySelectorAll("[data-message-id]").length===count+1', arg=before)
    assert expected+' آزمایش ارسال' in page.locator('[data-thread-messages]').inner_text()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.goto('http://localhost:8000/panel/saved-replies', wait_until='networkidle')
    page.locator(f'[data-reply-id="{state["reply"]}"] summary').click()
    page.locator(f'[data-reply-id="{state["reply"]}"] summary').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.evaluate('getComputedStyle(document.activeElement).outlineStyle!=="none"')
    Path('/tmp/reply-review').mkdir(exist_ok=True)
    page.screenshot(path='/tmp/reply-review/mobile.png', full_page=True)
    page.once('dialog',lambda dialog:dialog.dismiss())
    page.locator(f'[data-reply-id="{state["reply"]}"]').get_by_role('button',name='حذف',exact=True).click()
    assert page.locator(f'[data-reply-id="{state["reply"]}"]').count() == 1
    page.once('dialog',lambda dialog:dialog.accept())
    page.locator(f'[data-reply-id="{state["reply"]}"]').get_by_role('button',name='حذف',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert page.locator(f'[data-reply-id="{state["reply"]}"]').count() == 0
    page.goto('http://localhost:8000/lang/en', wait_until='networkidle')
    page.goto('http://localhost:8000/panel/saved-replies',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.get_by_role('heading', name='My saved replies').is_visible()
    assert not errors, errors
    browser.close()
print('PASS: native editor, private reply picker, confirmed replacement, no automatic send, manual edited send, confirmed deletion, RTL/LTR and responsive layouts.')
