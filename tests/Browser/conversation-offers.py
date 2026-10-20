import json
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/chat-offers-review.json').read_text())
base = 'http://localhost:8000'
inbox = f'{base}/panel/inbox?conversation={state["thread"]}'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    buyer = browser.new_context(viewport={'width': 390, 'height': 844})
    page = buyer.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(inbox, wait_until='networkidle')
    page.locator('.chat-offers > summary').click()
    page.get_by_label('قیمت پیشنهادی شما (ریال)', exact=True).fill('۹۰۰۰۰۰')
    page.get_by_label('توضیح برای فروشنده (اختیاری)', exact=True).fill('قیمت پیشنهادی آزمایش مرورگر')
    page.get_by_role('button', name='ارسال پیشنهاد', exact=True).click()
    page.wait_for_load_state('networkidle')
    page.locator('.chat-offers > summary').click()
    card = page.locator('[data-chat-offer]').first
    offer_id = card.get_attribute('data-chat-offer')
    assert card.get_attribute('data-offer-status') == 'pending'
    assert page.locator('[data-message-id]').count() == 1
    for width in [360, 390, 768, 1440]:
        page.set_viewport_size({'width': width, 'height': 900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert page.locator('#chat-offer-amount').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    page.set_viewport_size({'width':390, 'height':844})
    page.locator('#chat-offer-amount').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert page.locator('#chat-offer-amount').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    page.locator('#chat-offer-amount').fill('777000')
    seller = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844})
    peer = seller.new_page()
    peer.goto(base+'/login', wait_until='networkidle')
    peer.locator('#email').fill(state['email'])
    peer.locator('#password').fill('chat-offer-fixture-password')
    peer.locator('button[type=submit]').click()
    peer.wait_for_load_state('networkidle')
    peer.goto(base+'/lang/fa', wait_until='networkidle')
    peer.goto(inbox, wait_until='networkidle')
    peer.locator('.chat-offers > summary').click()
    assert peer.locator('#chat-offer-amount').count() == 0
    incoming = peer.locator(f'[data-chat-offer="{offer_id}"]')
    incoming.locator('button[value=accepted]').click()
    assert incoming.locator('[name=confirmed]').evaluate('(e)=>!e.validity.valid')
    incoming.locator('[name=confirmed]').check()
    incoming.locator('button[value=accepted]').click()
    peer.wait_for_load_state('networkidle')
    peer.locator('.chat-offers > summary').click()
    assert peer.locator(f'[data-chat-offer="{offer_id}"]').get_attribute('data-offer-status') == 'accepted'
    page.locator('.chat-offers > summary').focus()
    page.wait_for_function('(id)=>document.querySelector(`[data-chat-offer="${id}"]`)?.dataset.offerStatus === "accepted"', arg=offer_id, timeout=35000)
    assert page.locator('#chat-offer-amount').input_value() == '777000'
    page.screenshot(path='/tmp/chat-offers-mobile.png', full_page=True)
    page.get_by_role('button', name='ارسال پیشنهاد', exact=True).click()
    page.wait_for_load_state('networkidle')
    page.locator('.chat-offers > summary').click()
    native = browser.new_context(java_script_enabled=False, storage_state=buyer.storage_state(), viewport={'width':390,'height':844})
    plain = native.new_page()
    plain.goto(inbox, wait_until='networkidle')
    plain.locator('.chat-offers > summary').click()
    outgoing = plain.locator('[data-chat-offer]').first
    outgoing.locator('[name=confirmed]').check()
    outgoing.locator('button[value=withdrawn]').click()
    plain.wait_for_load_state('networkidle')
    plain.locator('.chat-offers > summary').click()
    assert plain.locator('[data-chat-offer]').first.get_attribute('data-offer-status') == 'withdrawn'
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(inbox, wait_until='networkidle')
    page.locator('.chat-offers > summary').click()
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.get_by_label('Your offer (IRR)', exact=True).is_visible()
    assert page.locator('[data-message-id]').count() == 1
    assert not errors, errors
    browser.close()
print('PASS: actual buyer submission, seller native acceptance and required confirmation, live status with draft preserved, native buyer withdrawal, unchanged human messages, RTL/LTR, Vazirmatn, focus and four widths.')
