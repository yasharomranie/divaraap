import json
from pathlib import Path
from playwright.sync_api import sync_playwright

base = 'http://localhost:8000'
state = json.loads(Path('storage/framework/services-review.json').read_text())
errors = []
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    def account(email):
        context = browser.new_context(viewport={'width':390,'height':844})
        page = context.new_page()
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.goto(base+'/login',wait_until='networkidle')
        page.locator('#email').fill(email)
        page.locator('#password').fill('Browser-test-123!')
        page.locator('button[type="submit"]').click()
        page.wait_for_url(lambda u:'/login' not in u)
        page.goto(base+'/lang/fa',wait_until='networkidle')
        return page
    buyer = account(state['buyer_email'])
    buyer.goto(base+'/panel/service-requests/create',wait_until='networkidle')
    assert buyer.locator('html').get_attribute('dir') == 'rtl'
    form = buyer.locator('.wanted-form')
    for width in [360,390,768,1440]:
        buyer.set_viewport_size({'width':width,'height':1000})
        assert buyer.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
    buyer.set_viewport_size({'width':390,'height':844})
    assert form.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    buyer.locator('#wanted-title').fill('تعمیر وسایل خانه نیاز دارم')
    buyer.locator('#wanted-description').fill('تعمیر وسایل خانه را می‌خواهم؛ لطفا درباره هزینه و زمان گفتگو کنیم.')
    buyer.locator('#wanted-category_id').select_option(str(state['category']))
    assert buyer.locator('#wanted-city_id').get_attribute('required') is not None
    buyer.locator('#wanted-city_id').select_option(str(state['city']))
    buyer.locator('#wanted-preferred_time').fill('آخر هفته، عصرها')
    buyer.locator('#wanted-budget').fill('20000000')
    submit = buyer.get_by_role('button',name='ارسال برای بررسی',exact=True)
    submit.focus()
    assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
    buyer.screenshot(path='/tmp/services-create-mobile.png',full_page=True)
    submit.click()
    buyer.wait_for_url(lambda u:'/panel/service-requests/' in u and not u.endswith('create'))
    request_id = int(buyer.url.rstrip('/').split('/')[-1])
    assert 'در انتظار بررسی' in buyer.locator('[data-wanted-owner]').inner_text()
    admin = browser.new_context(viewport={'width':1440,'height':1000})
    ap = admin.new_page()
    ap.on('pageerror',lambda e:errors.append(str(e)))
    ap.goto(base+'/admin/login',wait_until='networkidle')
    ap.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    ap.wait_for_url(base+'/admin')
    ap.goto(base+'/lang/fa',wait_until='networkidle')
    ap.goto(base+'/admin/wanted-requests',wait_until='networkidle')
    row = ap.locator('tr').filter(has_text='تعمیر وسایل خانه نیاز دارم')
    row.get_by_role('button',name='تایید و انتشار',exact=True).click()
    review = ap.locator('[data-wanted-review]')
    review.wait_for()
    assert 'هزینه و زمان' in review.inner_text()
    ap.get_by_role('button',name='تایید',exact=True).click()
    review.wait_for(state='hidden')
    seller = account(state['seller_email'])
    seller.goto(base+'/local-services?search=تعمیر',wait_until='networkidle')
    seller.locator(f'[data-wanted="{request_id}"]').get_by_role('link',name='دیدن درخواست',exact=True).click()
    seller.wait_for_load_state('networkidle')
    assert 'آخر هفته، عصرها' in seller.locator('main').inner_text()
    assert '۲۰٬۰۰۰٬۰۰۰' in seller.locator('.wanted-budget').inner_text()
    for width in [360,390,768,1440]:
        seller.set_viewport_size({'width':width,'height':1000})
        assert seller.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
    seller.set_viewport_size({'width':390,'height':844})
    seller.screenshot(path='/tmp/services-public-mobile.png',full_page=True)
    seller.locator('#wanted-listing_id').select_option(str(state['listings'][0]))
    seller.get_by_role('button',name='پیشنهاد خدمت من',exact=True).click()
    seller.wait_for_load_state('networkidle')
    assert 'پیشنهادت برای این نسخه ثبت شده است.' in seller.locator('main').inner_text()
    buyer.reload(wait_until='networkidle')
    response = buyer.locator('[data-wanted-response]')
    response.wait_for()
    assert 'خدمت پیشنهادی آزمایش ۱' in response.inner_text() or 'خدمت پیشنهادی آزمایش 1' in response.inner_text()
    response.get_by_role('button',name='گفتگو با ارائه‌دهنده',exact=True).click()
    buyer.wait_for_url(lambda u:'/panel/inbox' in u)
    assert 'conversation=' in buyer.url
    buyer.goto(base+f'/panel/service-requests/{request_id}',wait_until='networkidle')
    buyer.get_by_role('button',name='خدمت انجام شد',exact=True).click()
    buyer.wait_for_load_state('networkidle')
    assert 'انجام شد (اعلام شما)' in buyer.locator('[data-wanted-owner]').inner_text()
    assert seller.goto(base+f'/local-services/{request_id}',wait_until='networkidle').status == 404
    buyer.goto(base+'/lang/en',wait_until='networkidle')
    buyer.goto(base+'/panel/service-requests',wait_until='networkidle')
    assert buyer.locator('html').get_attribute('dir') == 'ltr'
    assert 'My service requests' in buyer.locator('.wanted').inner_text()
    assert not errors,errors
    browser.close()
print('PASS local service request creation/review/public filtering/private suggestion/existing conversation/fulfillment, four widths, RTL/LTR, font and focus.')
