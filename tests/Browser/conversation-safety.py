import json
from pathlib import Path
from playwright.sync_api import sync_playwright
root=Path(__file__).resolve().parents[2]
state=json.loads((root/'storage/framework/chat-safety-review.json').read_text())
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    context=browser.new_context(viewport={'width':390,'height':844})
    page=context.new_page()
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    safety=f'http://localhost:8000/conversations/{state["thread"]}/safety'
    inbox=f'http://localhost:8000/panel/inbox?conversation={state["thread"]}'
    page.goto(inbox,wait_until='networkidle')
    page.get_by_role('link',name='گزارش یا مسدودسازی',exact=True).click()
    page.wait_for_url(safety)
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.screenshot(path='/tmp/chat-safety-mobile.png',full_page=True)
    form=page.locator(f'form[action$="/conversations/{state["thread"]}/block"]')
    form.locator('button[type=submit]').click()
    assert form.locator('[name=confirmed]').evaluate('(e)=>!e.validity.valid')
    form.locator('[name=confirmed]').check()
    form.locator('button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    page.goto(inbox,wait_until='networkidle')
    assert page.locator('[data-thread-form]').count()==0
    assert page.get_by_text('EXISTING BUYER FIXTURE',exact=True).is_visible()
    csrf=page.locator('meta[name=csrf-token]').get_attribute('content')
    response=context.request.post(f'http://localhost:8000/conversations/{state["thread"]}/messages',data={'message':'STALE TAB ATTEMPT','_token':csrf},headers={'Accept':'application/json'})
    assert response.status==422
    page.goto(safety,wait_until='networkidle')
    report=page.locator(f'form[action$="/conversations/{state["thread"]}/report"]')
    report.locator('[name=reason]').select_option('offensive')
    report.locator('[name=message_id]').select_option(str(state['message']))
    report.locator('[name=details]').fill('Browser report fixture')
    report.locator('[name=confirmed]').check()
    report.locator('button[type=submit]').click()
    page.wait_for_load_state('networkidle')
    assert page.get_by_text('گزارش برای بررسی مدیریت ثبت شد.',exact=True).is_visible()
    page.goto('http://localhost:8000/admin/reports',wait_until='networkidle')
    page.locator('a[href*="/admin/reports/"][href$="/edit"]').first.click()
    page.wait_for_load_state('networkidle')
    evidence=page.get_by_label('پیام گزارش‌شده؛ فقط برای بررسی مدیریت',exact=True)
    assert evidence.input_value()=='SELECTED REPORT FIXTURE'
    assert evidence.is_disabled()
    assert 'UNSELECTED REPORT FIXTURE' not in [field.input_value() for field in page.locator('textarea').all()]
    native=browser.new_context(java_script_enabled=False,storage_state=context.storage_state(),viewport={'width':390,'height':844})
    plain=native.new_page()
    plain.goto('http://localhost:8000/panel/blocked-users',wait_until='networkidle')
    row=plain.locator('article').filter(has=plain.get_by_text('آزمایش ایمنی گفتگو',exact=True))
    row.locator('[name=confirmed]').check()
    row.locator('button[type=submit]').click()
    plain.wait_for_load_state('networkidle')
    assert plain.get_by_text('آزمایش ایمنی گفتگو',exact=True).count()==0
    page.goto(inbox,wait_until='networkidle')
    assert page.locator('[data-thread-form]').count()==1
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(safety,wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    button=page.get_by_role('button',name='Block communication',exact=True)
    button.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert button.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none" || getComputedStyle(e).boxShadow!="none"')
    browser.close()
print('PASS: inbox entry, required block confirmation, preserved history, stale-tab API denial, report snapshot, real staff-only evidence view, native unblock, restored composer, keyboard, RTL/LTR and four widths.')
