import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
base='http://localhost:8000'
state=json.loads(Path('storage/framework/shortlist-review.json').read_text())
errors=[]
def fixture(action):
 result=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/shortlist-sharing-fixtures.php',action],check=True,text=True,capture_output=True)
 assert any(word in result.stdout for word in ['added.','removed.','changed.','expired.']),result.stdout
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 owner=browser.new_context(viewport={'width':390,'height':844}).new_page()
 guest=browser.new_context(viewport={'width':390,'height':844}).new_page()
 for page in [owner,guest]:
  page.on('pageerror',lambda e:errors.append(str(e)))
 owner.goto(base+'/login',wait_until='networkidle')
 owner.locator('#email').fill(state['email'])
 owner.locator('#password').fill('Browser-test-123!')
 owner.locator('button[type="submit"]').click()
 owner.wait_for_url(lambda u:'/login' not in u)
 owner.goto(base+'/lang/fa',wait_until='networkidle')
 owner.goto(base+'/collections',wait_until='networkidle')
 owner.get_by_role('link',name='اشتراک این مجموعه',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert 'نام خصوصی مجموعه آزمایش' in owner.locator('main').inner_text()
 owner.locator('#shortlist-consent').check()
 submit=owner.get_by_role('button',name='تایید انتخاب‌ها و ساخت لینک',exact=True)
 owner.keyboard.press('Tab')
 submit.focus()
 assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
 assert owner.locator('.dossier').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
 submit.click()
 owner.wait_for_load_state('networkidle')
 url=owner.locator('#shortlist-url').input_value()
 guest.goto(base+'/lang/fa',wait_until='networkidle')
 assert guest.goto(url,wait_until='networkidle').status==200
 assert guest.locator('[data-shared-choice]').count()==2
 assert 'نام خصوصی مجموعه آزمایش' not in guest.locator('main').inner_text()
 assert guest.locator('main form').count()==0
 for width in [360,390,768,1440]:
  for page in [owner,guest]:
   page.set_viewport_size({'width':width,'height':1000})
   assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
 guest.set_viewport_size({'width':390,'height':844})
 guest.screenshot(path='/tmp/shared-shortlist-mobile.png',full_page=True)
 guest.locator('[data-shared-choice]').first.get_by_role('link',name='مشاهدهٔ آگهی',exact=True).click()
 guest.wait_for_url(base+f'/listings/{state["listings"][0]}')
 fixture('add-third')
 guest.goto(url,wait_until='networkidle')
 assert guest.locator('[data-shared-choice]').count()==2
 owner.reload(wait_until='networkidle')
 owner.locator('#shortlist-consent').check()
 owner.get_by_role('button',name='جایگزینی انتخاب‌ها و ساخت لینک تازه',exact=True).click()
 owner.wait_for_load_state('networkidle')
 updated=owner.locator('#shortlist-url').input_value()
 assert updated!=url
 assert guest.goto(url,wait_until='networkidle').status==404
 guest.goto(updated,wait_until='networkidle')
 assert guest.locator('[data-shared-choice]').count()==3
 fixture('remove-first')
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-shared-choice]').count()==2
 fixture('change-price')
 guest.reload(wait_until='networkidle')
 assert 'انتخاب با قیمت تازه' in guest.locator('main').inner_text()
 owner.get_by_role('button',name='لغو لینک',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert guest.goto(updated,wait_until='networkidle').status==404
 owner.get_by_role('button',name='لینک تازه با همان انتخاب‌ها',exact=True).click()
 owner.wait_for_load_state('networkidle')
 rotated=owner.locator('#shortlist-url').input_value()
 assert guest.goto(rotated,wait_until='networkidle').status==200
 fixture('expire')
 assert guest.goto(rotated,wait_until='networkidle').status==404
 owner.reload(wait_until='networkidle')
 owner.get_by_role('button',name='لینک تازه با همان انتخاب‌ها',exact=True).click()
 owner.wait_for_load_state('networkidle')
 renewed=owner.locator('#shortlist-url').input_value()
 guest.goto(base+'/lang/en',wait_until='networkidle')
 guest.goto(renewed,wait_until='networkidle')
 assert guest.locator('html').get_attribute('dir')=='ltr'
 assert 'Shared shortlist' in guest.locator('h1').inner_text()
 owner.get_by_role('button',name='حذف لینک؛ نگه‌داشتن مجموعه',exact=True).click()
 owner.wait_for_load_state('networkidle')
 assert owner.locator('#shortlist-url').count()==0
 assert guest.goto(renewed,wait_until='networkidle').status==404
 owner.goto(base+'/collections',wait_until='networkidle')
 assert owner.locator(f'#collection-{state["collection"]}').input_value()=='نام خصوصی مجموعه آزمایش'
 assert not errors,errors
 browser.close()
print('PASS real collection share/guest read-only/privacy/live price/add requires approval/remove immediate/revoke/rotate/expiry/clear, four widths, RTL/LTR, font and focus.')
