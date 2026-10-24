import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
base='http://localhost:8000'
shop=json.loads(Path('storage/framework/shop-card-review.json').read_text())
service=json.loads(Path('storage/framework/service-card-review.json').read_text())
def fixture(action):
 r=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/post-click-actions-fixtures.php',action],check=True,text=True,capture_output=True)
 assert 'changed.' in r.stdout,r.stdout
def counts():
 r=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/post-click-actions-fixtures.php'],check=True,text=True,capture_output=True)
 return json.loads(r.stdout)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 context=browser.new_context(viewport={'width':390,'height':844})
 page=context.new_page()
 errors=[]
 page.on('pageerror',lambda e: errors.append(str(e)))
 fixture('reset')
 fixture('shop-first')
 page.goto(base+'/lang/fa',wait_until='networkidle')
 page.goto(base,wait_until='networkidle')
 card=page.locator(f'[data-banner="{shop["banner"]}"]').first
 card.locator('a').click()
 page.wait_for_url(base+f'/sellers/{shop["seller"]}')
 page.wait_for_load_state('networkidle')
 assert counts()['shop']['shop_view']==1,counts()
 cookie=next(c for c in context.cookies() if c['name']=='advertising_attribution')
 assert cookie['httpOnly'] and cookie['sameSite']=='Lax'
 page.reload(wait_until='networkidle')
 assert counts()['shop']['shop_view']==1
 page.goto(base+'/login',wait_until='networkidle')
 page.locator('#email').fill(service['email'])
 page.locator('#password').fill('service-fixture-password')
 page.locator('button[type=submit]').click()
 page.wait_for_url(lambda u:'/login' not in u)
 fixture('service-first')
 page.goto(base,wait_until='networkidle')
 page.locator(f'[data-banner="{service["banner"]}"]').first.locator('a').click()
 page.wait_for_url('**#service-request')
 page.wait_for_load_state('networkidle')
 assert counts()['service']['service']==0
 page.locator('#service-request-message').fill('هماهنگی زمان خدمت، آزمایش انتساب کلیک')
 page.locator('#service-request form').get_by_role('button',name='ارسال درخواست در پیام‌رسان',exact=True).click()
 page.wait_for_url('**/panel/inbox?conversation=*')
 page.wait_for_load_state('networkidle')
 result=counts()
 assert result['service']['service']==1 and result['service']['conversion']==1,result
 admin=browser.new_context(viewport={'width':390,'height':844}).new_page()
 admin.on('pageerror',lambda e:errors.append(str(e)))
 admin.goto(base+'/admin/login',wait_until='networkidle')
 admin.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
 admin.wait_for_url(base+'/admin')
 admin.goto(base+'/lang/fa',wait_until='networkidle')
 admin.goto(base+'/admin/advertising-statistics',wait_until='networkidle')
 expect(admin.get_by_role('columnheader',name='بازدید فروشگاه پس از کلیک',exact=True)).to_be_visible()
 assert admin.locator('.ad-statistics').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
 for width in [360,390,768,1440]:
  admin.set_viewport_size({'width':width,'height':1000})
  assert admin.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
  assert admin.locator('html').get_attribute('dir')=='rtl'
 region=admin.locator('.ad-statistics__scroll')
 region.focus()
 assert region.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none"')
 admin.set_viewport_size({'width':390,'height':844})
 overlay=admin.locator('.fi-sidebar-overlay')
 if overlay.count() and overlay.is_visible():
  overlay.click(position={'x':10,'y':10})
  expect(overlay).to_be_hidden()
 admin.locator('.ad-statistics').scroll_into_view_if_needed()
 admin.screenshot(path='/tmp/post-click-statistics-mobile.png')
 with admin.expect_download() as downloaded:
  admin.get_by_role('button',name='دانلود گزارش CSV',exact=True).click()
 csv=Path(downloaded.value.path()).read_text('utf-8-sig')
 assert 'بازدید فروشگاه پس از کلیک' in csv and 'درخواست خدمت ارسال‌شده' in csv
 assert 'هماهنگی زمان خدمت' not in csv and service['email'] not in csv
 admin.goto(base+'/admin/advertising-statistics',wait_until='networkidle')
 admin.goto(base+'/lang/en',wait_until='networkidle')
 admin.goto(base+'/admin/advertising-statistics',wait_until='networkidle')
 assert admin.locator('html').get_attribute('dir')=='ltr'
 assert admin.locator('thead th').count()==7
 fixture('shop-first')
 failed=browser.new_context().new_page()
 failed.route('**/advertising/events',lambda route:route.abort())
 failed.goto(base,wait_until='networkidle')
 failed.locator(f'[data-banner="{shop["banner"]}"]').first.locator('a').click()
 failed.wait_for_url(base+f'/sellers/{shop["seller"]}',timeout=5000)
 assert counts()['shop']['shop_view']==1
 native=browser.new_context(java_script_enabled=False).new_page()
 native.goto(base,wait_until='networkidle')
 native.locator(f'[data-banner="{shop["banner"]}"]').first.locator('a').click()
 native.wait_for_url(base+f'/sellers/{shop["seller"]}')
 assert counts()['shop']['shop_view']==1
 assert not errors,errors
 browser.close()
print('PASS actual shop navigation/cookie/deduplication, real successful service request, separate conversation, admin report/CSV privacy, four widths, RTL/LTR, font/focus, failed metrics and native navigation.')
