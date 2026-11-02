import json,subprocess
from pathlib import Path
from datetime import datetime, timezone
from playwright.sync_api import sync_playwright, expect
base='http://localhost:8000'
state=json.loads(Path('storage/framework/availability-review.json').read_text())
def status():
 result=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/availability-fixtures.php','state'],check=True,capture_output=True,text=True)
 return json.loads(result.stdout)
def login(page,email):
 page.goto(base+'/login',wait_until='networkidle')
 page.locator('#email').fill(email)
 page.locator('#password').fill('Browser-test-123!')
 page.locator('button[type=submit]').click()
 page.wait_for_url(lambda u:'/login' not in u)
 page.goto(base+'/lang/fa',wait_until='networkidle')
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 owner=browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844}).new_page()
 guest=browser.new_context(viewport={'width':390,'height':844}).new_page()
 buyer=browser.new_context(viewport={'width':390,'height':844}).new_page()
 errors=[]
 for page in [owner,guest,buyer]:page.on('pageerror',lambda e:errors.append(str(e)))
 login(owner,state['seller_email'])
 owner.goto(base+'/panel/availability',wait_until='networkidle')
 owner.locator('#availability-enabled').select_option('1')
 owner.locator('#availability-reason').select_option('limited')
 owner.locator('#availability-until').fill(state['until'])
 owner.get_by_role('button',name='ذخیرهٔ وضعیت',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert owner.get_by_role('alert').count()>0
 assert status()['settings']['revision']==0
 owner.locator('input[name=public_consent]').check()
 owner.get_by_role('button',name='ذخیرهٔ وضعیت',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert status()['settings']['active'] and status()['settings']['revision']==1
 assert status()['messages']==0
 for width in [360,390,768,1440]:
  owner.set_viewport_size({'width':width,'height':1000})
  assert owner.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
 owner.set_viewport_size({'width':390,'height':844})
 owner.locator('.availability-settings').screenshot(path='/tmp/seller-availability-settings.png')
 guest.clock.install()
 guest.goto(base+'/lang/fa',wait_until='networkidle')
 for path in [f'/listings/{state["listing"]}',f'/sellers/{state["seller"]}']:
  guest.goto(base+path,wait_until='networkidle')
  notice=guest.locator('[data-seller-availability]')
  expect(notice).to_have_count(1)
  assert 'دسترسی محدود' in notice.inner_text()
  assert 'به وقت تهران' in notice.inner_text()
  for width in [360,390,768,1440]:
   guest.set_viewport_size({'width':width,'height':1000})
   assert guest.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(path,width)
 guest.set_viewport_size({'width':390,'height':844})
 guest.locator('[data-seller-availability]').screenshot(path='/tmp/seller-availability-notice.png')
 login(buyer,state['buyer_email'])
 buyer.goto(base+f'/panel/inbox?conversation={state["thread"]}',wait_until='networkidle')
 expect(buyer.locator('[data-seller-availability]')).to_have_count(1)
 composer=buyer.locator('[data-thread-input]')
 assert composer.is_visible()
 assert composer.bounding_box()['y']+composer.bounding_box()['height']<=844
 composer.fill('برای هماهنگی پس از بازگشت پیام می‌فرستم.')
 buyer.locator('[data-thread-submit]').click()
 expect(buyer.locator('[data-thread-messages]')).to_contain_text('برای هماهنگی پس از بازگشت')
 assert status()['messages']==1
 for width,height in [(360,780),(390,844),(768,1000),(1440,1000)]:
  buyer.set_viewport_size({'width':width,'height':height})
  assert buyer.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
  if width<768:
   box=composer.bounding_box()
   assert box['y']>=0 and box['y']+box['height']<=height
   expect(buyer.locator('.mobile-tabs')).to_be_hidden()
  else:
   expect(buyer.locator('.site-header')).to_be_visible()
 buyer.set_viewport_size({'width':390,'height':844})
 buyer.locator('[data-thread-back]').click()
 expect(buyer.locator('.mobile-tabs')).to_be_visible()
 expect(buyer.locator('[data-thread-open]')).to_be_visible()
 expect(buyer.locator('[data-thread-open].is-active')).to_be_focused()
 buyer.locator('[data-thread-open]').first.click()
 buyer.wait_for_load_state('networkidle')
 expect(buyer.locator('[data-seller-availability]')).to_have_count(1)
 guest.clock.fast_forward(24*24*60*60*1000)
 guest.clock.fast_forward(6*24*60*60*1000)
 expect(guest.locator('[data-seller-availability]')).to_have_count(0)
 assert status()['settings']['active']
 guest.clock.set_system_time(datetime.now(timezone.utc))
 guest.goto(base+'/lang/en',wait_until='networkidle')
 guest.goto(base+f'/sellers/{state["seller"]}',wait_until='networkidle')
 assert guest.locator('html').get_attribute('dir')=='ltr'
 assert 'Limited availability' in guest.locator('[data-seller-availability]').inner_text()
 buyer.goto(base+'/panel/availability',wait_until='networkidle')
 buyer.locator('#availability-enabled').focus()
 assert buyer.locator('#availability-enabled').evaluate('(e)=>getComputedStyle(e).outlineStyle!="none"')
 assert buyer.locator('.availability-settings').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
 owner.locator('#availability-enabled').select_option('0')
 owner.get_by_role('button',name='ذخیرهٔ وضعیت',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert not status()['settings']['active']
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-seller-availability]').count()==0
 buyer.goto(base+f'/panel/inbox?conversation={state["thread"]}',wait_until='networkidle')
 assert buyer.locator('[data-seller-availability]').count()==0
 assert status()['messages']==1
 assert not errors,errors
 browser.close()
print('PASS native consent/save/off, listing and seller notices, real buyer chat without auto messages, 29-day timer cap/expiry in open page, RTL/LTR, four widths, font and focus.')
