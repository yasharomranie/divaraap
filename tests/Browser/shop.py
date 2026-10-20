from playwright.sync_api import sync_playwright
import json
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 page=b.new_page(viewport={'width':390,'height':844})
 page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
 page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
 page.wait_for_url('http://localhost:8000/admin')
 page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
 page.goto('http://localhost:8000/panel/shop',wait_until='networkidle')
 old={key:page.locator('[name='+key+']').input_value() for key in ['shop_enabled','shop_name','shop_description','shop_hours']}
 link=page.locator('#shop-link').input_value()
 try:
  page.locator('#shop-enabled').select_option('1')
  page.locator('#shop_name').fill('فروشگاه آزمایش مرورگر')
  page.locator('#shop_description').fill('معرفی فروشگاه؛ تست نمایش موبایل و حفظ حریم خصوصی')
  page.locator('#shop_hours').fill('شنبه تا پنجشنبه ۹ تا ۱۸')
  page.get_by_role('button',name='ذخیرهٔ فروشگاه',exact=True).click()
  page.wait_for_load_state('networkidle')
  assert page.locator('#shop_name').input_value()=='فروشگاه آزمایش مرورگر'
  for path in ['/panel/shop',link]:
   page.goto(path if path.startswith('http') else 'http://localhost:8000'+path,wait_until='networkidle')
   for width in [360,390,768,1440]:
    page.set_viewport_size({'width':width,'height':900})
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),(path,width)
   page.set_viewport_size({'width':390,'height':844})
   page.screenshot(path='/tmp/shop-'+('settings' if 'panel' in path else 'public')+'.png',full_page=True)
  assert page.get_by_role('heading',name='فروشگاه آزمایش مرورگر',exact=True).count()==1
  page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
  page.goto('http://localhost:8000/panel/shop',wait_until='networkidle')
  assert page.locator('html').get_attribute('dir')=='ltr'
  page.locator('#shop_name').focus()
  assert page.locator('#shop_name').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
  print(json.dumps({'save':'passed','public_shop':'passed','widths':[360,390,768,1440],'rtl_ltr':'passed','focus':'passed'}))
 finally:
  page.goto('http://localhost:8000/panel/shop',wait_until='networkidle')
  page.locator('#shop-enabled').select_option(old['shop_enabled'])
  for key in ['shop_name','shop_description','shop_hours']: page.locator('#'+key).fill(old[key])
  page.get_by_role('button', name='Save shop', exact=True).click()
  page.wait_for_load_state('networkidle')
 b.close()
