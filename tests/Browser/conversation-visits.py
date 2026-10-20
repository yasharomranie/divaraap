import json
from datetime import datetime, timedelta
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/chat-visits-review.json').read_text())
base = 'http://localhost:8000'
inbox = f'{base}/panel/inbox?conversation={state["thread"]}'
visits = f'{base}/conversations/{state["thread"]}/visits'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    buyer = browser.new_context(viewport={'width':390,'height':844})
    page = buyer.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(inbox, wait_until='networkidle')
    page.get_by_role('link', name='هماهنگی بازدید', exact=True).click()
    page.wait_for_url(visits)
    assert page.locator('html').get_attribute('dir') == 'rtl'
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert page.locator('#visit-place').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('#visit-place').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.locator('#visit-place').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    time = (datetime.fromisoformat(page.locator('#visit-time').get_attribute('min'))+timedelta(days=1)).strftime('%Y-%m-%dT%H:%M')
    page.get_by_label('تاریخ و ساعت بازدید', exact=True).fill(time)
    page.get_by_label('محل دیدار', exact=True).fill('محل دیدار آزمایشی در ورودی نمایشگاه')
    page.get_by_label('توضیحات تکمیلی (اختیاری)', exact=True).fill('توضیح خصوصی بازدید')
    page.get_by_role('button', name='ارسال پیشنهاد بازدید', exact=True).click()
    assert page.locator('input[name=visit_confirmed]').evaluate('(e)=>!e.validity.valid')
    page.locator('input[name=visit_confirmed]').check()
    page.get_by_role('button', name='ارسال پیشنهاد بازدید', exact=True).click()
    page.wait_for_load_state('networkidle')
    card = page.locator('[data-visit]').first
    visit_id = card.get_attribute('data-visit')
    assert card.get_attribute('data-visit-status') == 'pending'
    assert card.locator('button[value=accepted]').count() == 0
    assert page.locator('#visit-time').count() == 0
    page.evaluate('window.scrollTo(0,0)')
    page.screenshot(path='/tmp/chat-visits-mobile.png', full_page=True)
    seller = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844})
    peer = seller.new_page()
    peer.goto(base+'/login', wait_until='networkidle')
    peer.locator('#email').fill(state['email'])
    peer.locator('#password').fill('visit-fixture-password')
    peer.locator('button[type=submit]').click()
    peer.wait_for_load_state('networkidle')
    peer.goto(base+'/lang/fa', wait_until='networkidle')
    peer.goto(visits, wait_until='networkidle')
    incoming = peer.locator(f'[data-visit="{visit_id}"]')
    incoming.locator('button[value=accepted]').click()
    assert incoming.locator('input[name=visit_confirmed]').evaluate('(e)=>!e.validity.valid')
    incoming.locator('input[name=visit_confirmed]').check()
    incoming.locator('button[value=accepted]').click()
    peer.wait_for_load_state('networkidle')
    assert peer.locator(f'[data-visit="{visit_id}"]').get_attribute('data-visit-status') == 'accepted'
    page.get_by_role('link', name='تازه‌سازی وضعیت', exact=True).click()
    page.wait_for_load_state('networkidle')
    assert page.locator(f'[data-visit="{visit_id}"]').get_attribute('data-visit-status') == 'accepted'
    native = browser.new_context(java_script_enabled=False, storage_state=buyer.storage_state(), viewport={'width':390,'height':844})
    plain = native.new_page()
    plain.goto(visits, wait_until='networkidle')
    outgoing = plain.locator(f'[data-visit="{visit_id}"]')
    outgoing.locator('input[name=visit_confirmed]').check()
    outgoing.locator('button[value=cancelled]').click()
    plain.wait_for_load_state('networkidle')
    assert plain.locator(f'[data-visit="{visit_id}"]').get_attribute('data-visit-status') == 'cancelled'
    assert plain.locator('#visit-time').is_visible()
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(visits, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.get_by_label('Viewing date and time', exact=True).is_visible()
    assert page.get_by_text('Tehran time (UTC+03:30)', exact=True).is_visible()
    page.goto(inbox, wait_until='networkidle')
    assert page.locator('[data-message-id]').count() == 1
    assert not errors, errors
    browser.close()
print('PASS: inbox entry, Persian proposal, native confirmations and acceptance, status refresh, native cancellation and restored form, history, private details, unchanged messages, RTL/LTR, Vazirmatn, keyboard focus and four widths.')
