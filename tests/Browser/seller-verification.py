import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/verification-review.json').read_text())
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
    page.goto(f'http://localhost:8000/admin/users/{state["user"]}/edit',wait_until='networkidle')
    page.get_by_role('button',name='مدیریت نشان تأیید',exact=True).click()
    dialog = page.get_by_role('dialog')
    dialog.locator('select').wait_for()
    dialog.locator('select').select_option('grant')
    dialog.locator('textarea').fill('Reviewed in browser fixture')
    dialog.locator('button[type=submit]').click()
    dialog.locator('select').wait_for(state='hidden')
    for path in [f'/sellers/{state["user"]}',f'/listings/{state["listing"]}']:
        page.goto('http://localhost:8000'+path,wait_until='networkidle')
        assert page.locator('[data-verification=staff]').is_visible()
        assert page.locator('[data-verification=email]').is_visible()
        assert 'Reviewed in browser fixture' not in page.locator('body').inner_text()
        for width in [360,390,768,1440]:
            page.set_viewport_size({'width':width,'height':900})
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(path,width)
        page.set_viewport_size({'width':390,'height':844})
        if path.startswith('/sellers/'):
            page.evaluate('scrollTo(0,0)')
            page.screenshot(path='/tmp/verification-seller-mobile.png')
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(f'http://localhost:8000/sellers/{state["user"]}',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert page.locator('[data-verification=staff]').text_content().strip()=='Reviewed by staff'
    page.goto(f'http://localhost:8000/admin/users/{state["user"]}/edit',wait_until='networkidle')
    action=page.get_by_role('button',name='Manage verification badge',exact=True)
    action.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert action.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none" || getComputedStyle(e).boxShadow!="none"')
    action.click()
    dialog=page.get_by_role('dialog')
    dialog.locator('select').wait_for()
    dialog.locator('select').select_option('revoke')
    dialog.locator('textarea').fill('Browser fixture review revoked')
    dialog.locator('button[type=submit]').click()
    dialog.locator('select').wait_for(state='hidden')
    page.goto(f'http://localhost:8000/sellers/{state["user"]}',wait_until='networkidle')
    assert page.locator('[data-verification=staff]').count()==0
    assert page.locator('[data-verification=email]').is_visible()
    assert not errors,errors
    browser.close()
print('PASS: actual Filament grant/revoke, independent email badge, private audit, seller/listing display, keyboard, RTL/LTR and four widths.')
