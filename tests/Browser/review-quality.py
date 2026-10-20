import json
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/review-quality.json').read_text())
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    seller_url=f'http://localhost:8000/sellers/{state["seller"]}'
    page.goto(seller_url,wait_until='networkidle')
    form=page.locator(f'form[action$="/sellers/{state["seller"]}/reviews"]')
    form.locator('[name=rating]').select_option('4')
    form.locator('[name=title]').fill('آزمون نظر معتبر مرورگر')
    form.locator('[name=body]').fill('این نظر یک آزمون واقعی رابط کاربری است.')
    form.locator('button[type=submit]').click()
    page.wait_for_url('http://localhost:8000/panel/my-reviews')
    row=page.locator('article').filter(has=page.get_by_text('آزمون نظر معتبر مرورگر',exact=True))
    assert row.get_by_text('در انتظار بررسی',exact=True).is_visible()
    assert row.locator('[data-review-interaction]').count()==1
    assert 'PRIVATE REVIEW CONVERSATION' not in page.locator('body').inner_text()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.evaluate('scrollTo(0,0)')
    page.screenshot(path='/tmp/review-quality-mobile.png')
    guest=browser.new_context()
    guest_page=guest.new_page()
    guest_page.goto(seller_url,wait_until='networkidle')
    assert guest_page.get_by_text('آزمون نظر معتبر مرورگر',exact=True).count()==0
    delete_url=row.locator('form').get_attribute('action')
    review_id=delete_url.rstrip('/').split('/')[-1]
    page.goto(f'http://localhost:8000/admin/reviews/{review_id}/edit',wait_until='networkidle')
    toggle=page.locator('button[role=switch]')
    if toggle.get_attribute('aria-checked')!='true': toggle.click()
    page.get_by_role('button',name='ذخیره',exact=True).click()
    page.get_by_text('ذخیره شد',exact=True).wait_for()
    guest_page.reload(wait_until='networkidle')
    assert guest_page.get_by_text('آزمون نظر معتبر مرورگر',exact=True).is_visible()
    assert guest_page.locator('[data-review-interaction]').count()==1
    assert 'PRIVATE REVIEW CONVERSATION' not in guest_page.locator('body').inner_text()
    for width in [360,390,768,1440]:
        guest_page.set_viewport_size({'width':width,'height':900})
        assert guest_page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto('http://localhost:8000/panel/my-reviews',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    row=page.locator('article').filter(has=page.get_by_text('آزمون نظر معتبر مرورگر',exact=True))
    assert row.get_by_text('Published',exact=True).is_visible()
    button=row.get_by_role('button',name='Delete review',exact=True)
    button.focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert button.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none" || getComputedStyle(e).boxShadow!="none"')
    page.once('dialog',lambda dialog:dialog.accept())
    button.click()
    page.wait_for_load_state('networkidle')
    assert page.get_by_text('آزمون نظر معتبر مرورگر',exact=True).count()==0
    browser.close()
print('PASS: native review submission, pending privacy, evidence label, real Filament approval, public display, private body exclusion, owner deletion, keyboard, RTL/LTR and four widths.')
