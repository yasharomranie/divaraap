import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
base='http://localhost:8000'
state=json.loads(Path('storage/framework/neighborhood-review.json').read_text())
errors=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 ap=browser.new_context(viewport={'width':1440,'height':1000}).new_page()
 guest=browser.new_context(viewport={'width':390,'height':844}).new_page()
 for page in [ap,guest]:
  page.on('pageerror',lambda e:errors.append(str(e)))
 ap.goto(base+'/admin/login',wait_until='networkidle')
 ap.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
 ap.wait_for_url(base+'/admin')
 ap.goto(base+'/lang/fa',wait_until='networkidle')
 ap.goto(base+'/admin/neighborhood-shops',wait_until='networkidle')
 ap.get_by_role('button',name='افزودن فروشگاه منتخب',exact=True).click()
 dialog=ap.locator('[role="dialog"]').filter(has=ap.locator('input[id$=".neighborhood"]'))
 dialog.locator('button[aria-haspopup="listbox"]').nth(0).click()
 ap.locator('input[role="combobox"][aria-expanded="true"]').fill('فروشگاه منتخب آزمایش')
 ap.get_by_role('option').filter(has_text='فروشگاه منتخب آزمایش').first.click()
 expect(dialog.locator('[data-shop-selection-preview]')).to_contain_text('معرفی عمومی فروشگاه')
 dialog.locator('button[aria-haspopup="listbox"]').nth(1).click()
 ap.get_by_role('option').first.click()
 dialog.locator('input[id$=".neighborhood"]').fill('كوی آفتاب')
 dialog.locator('input[id$=".priority"]').fill('3')
 dialog.get_by_role('button',name='ثبت',exact=True).click()
 ap.locator('input[id$=".neighborhood"]').wait_for(state='hidden')
 row=ap.locator('tr').filter(has_text='كوی آفتاب')
 row.wait_for()
 guest.goto(base+'/lang/fa',wait_until='networkidle')
 url=base+f'/neighborhood-shops?city_id={state["city"]}'
 assert guest.goto(url,wait_until='networkidle').status==200
 card=guest.locator('[data-local-shop]')
 assert 'فروشگاه منتخب آزمایش' in card.inner_text()
 assert 'PRIVATE_PHONE' not in guest.locator('main').inner_text()
 assert 'PRIVATE_CITY' not in guest.locator('main').inner_text()
 for width in [360,390,768,1440]:
  guest.set_viewport_size({'width':width,'height':1000})
  assert guest.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
 guest.set_viewport_size({'width':390,'height':844})
 assert guest.locator('.local-shops').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
 guest.locator('#shops-search').fill('کوی افتاب')
 submit=guest.get_by_role('button',name='نمایش فروشگاه‌ها',exact=True)
 submit.focus()
 assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
 submit.click()
 guest.wait_for_load_state('networkidle')
 assert guest.locator('[data-local-shop]').count()==1
 guest.screenshot(path='/tmp/neighborhood-shops-mobile.png',full_page=True)
 guest.locator('[data-local-shop]').get_by_role('link',name='مشاهدهٔ فروشگاه و آگهی‌ها',exact=True).click()
 guest.wait_for_url(base+f'/sellers/{state["seller"]}')
 assert 'فروشگاه منتخب آزمایش' in guest.locator('main').inner_text()
 result=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/neighborhood-shop-fixtures.php','change-shop'],check=True,text=True,capture_output=True)
 assert 'changed.' in result.stdout,result.stdout
 guest.goto(url,wait_until='networkidle')
 assert guest.locator('[data-local-shop]').count()==0
 ap.reload(wait_until='networkidle')
 row=ap.locator('tr').filter(has_text='كوی آفتاب')
 assert 'نیازمند بررسی دوباره' in row.inner_text()
 row.get_by_role('button',name='بررسی و ویرایش',exact=True).click()
 dialog=ap.locator('[role="dialog"]').filter(has=ap.locator('input[id$=".neighborhood"]'))
 expect(dialog.locator('[data-shop-selection-preview]')).to_contain_text('فروشگاه بازبینی‌شده')
 dialog.get_by_role('button',name='ثبت',exact=True).click()
 ap.locator('input[id$=".neighborhood"]').wait_for(state='hidden')
 guest.reload(wait_until='networkidle')
 assert 'فروشگاه بازبینی‌شده' in guest.locator('[data-local-shop]').inner_text()
 ap.screenshot(path='/tmp/neighborhood-shops-admin.png',full_page=True)
 row=ap.locator('tr').filter(has_text='كوی آفتاب')
 row.get_by_role('button',name='توقف نمایش',exact=True).click()
 ap.get_by_role('button',name='تایید',exact=True).click()
 expect(row).to_contain_text('متوقف')
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-local-shop]').count()==0
 row=ap.locator('tr').filter(has_text='كوی آفتاب')
 row.get_by_role('button',name='بررسی و ویرایش',exact=True).click()
 dialog=ap.locator('[role="dialog"]').filter(has=ap.locator('input[id$=".neighborhood"]'))
 dialog.get_by_role('switch').click()
 dialog.get_by_role('button',name='ثبت',exact=True).click()
 ap.locator('input[id$=".neighborhood"]').wait_for(state='hidden')
 guest.goto(base+'/lang/en',wait_until='networkidle')
 guest.goto(url,wait_until='networkidle')
 assert guest.locator('html').get_attribute('dir')=='ltr'
 assert 'Neighborhood shop picks' in guest.locator('h1').inner_text()
 assert guest.locator('[data-local-shop]').count()==1
 row=ap.locator('tr').filter(has_text='كوی آفتاب')
 row.get_by_role('button',name='حذف از منتخب‌ها',exact=True).click()
 ap.get_by_role('button',name='تایید',exact=True).click()
 row.wait_for(state='hidden')
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-local-shop]').count()==0
 assert not errors,errors
 browser.close()
print('PASS admin shop selection/public city/Persian neighborhood search/existing destination/content invalidation/review/pause/resume/remove, four widths, RTL/LTR, font and focus.')
