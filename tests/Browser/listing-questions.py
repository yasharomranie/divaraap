import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
base='http://localhost:8000'
state=json.loads(Path('storage/framework/listing-questions-review.json').read_text())
question='آیا باتری گوشی تعویض شده و ضمانت دارد؟'
answer='باتری اصلی است و تا پایان سال ضمانت دارد.'
errors=[]
def records():
 r=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/listing-question-fixtures.php','state'],check=True,capture_output=True,text=True)
 return json.loads(r.stdout)
def login(page,email):
 page.goto(base+'/login',wait_until='networkidle')
 page.locator('#email').fill(email)
 page.locator('#password').fill('Browser-test-123!')
 page.locator('button[type=submit]').click()
 page.wait_for_url(lambda u:'/login' not in u)
 page.goto(base+'/lang/fa',wait_until='networkidle')
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 buyer=browser.new_context(java_script_enabled=False,viewport={'width':390,'height':844}).new_page()
 seller=browser.new_context(viewport={'width':390,'height':844}).new_page()
 guest=browser.new_context(viewport={'width':390,'height':844}).new_page()
 admin=browser.new_context(viewport={'width':1440,'height':1000}).new_page()
 for page in [buyer,seller,guest,admin]:page.on('pageerror',lambda e:errors.append(str(e)))
 url=base+f'/listings/{state["listing"]}/questions'
 login(buyer,state['buyer_email'])
 buyer.goto(base+f'/listings/{state["listing"]}',wait_until='networkidle')
 buyer.get_by_role('link',name='مشاهده و ثبت پرسش',exact=True).click()
 field=buyer.get_by_label('سؤال شما',exact=True)
 field.fill(question)
 assert not buyer.locator('input[name=public_consent]').evaluate('(e)=>e.validity.valid')
 buyer.locator('input[name=public_consent]').check()
 buyer.get_by_role('button',name='ارسال سؤال',exact=True).click()
 buyer.wait_for_url(base+'/panel/listing-questions')
 assert question in buyer.locator('[data-owned-question]').inner_text()
 assert records()[0]['state']=='pending'
 guest.goto(base+'/lang/fa',wait_until='networkidle')
 guest.goto(url,wait_until='networkidle')
 assert guest.locator('[data-public-question]').count()==0
 login(seller,state['seller_email'])
 seller.goto(base+'/panel/listing-questions?role=received',wait_until='networkidle')
 seller.get_by_label('پاسخ فروشنده',exact=True).fill(answer)
 seller.locator('input[name=public_consent]').check()
 seller.get_by_role('button',name='ارسال پاسخ برای بررسی',exact=True).click()
 seller.wait_for_load_state('networkidle')
 assert records()[0]['state']=='answered'
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-public-question]').count()==0
 admin.goto(base+'/admin/login',wait_until='networkidle')
 admin.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
 admin.wait_for_url(base+'/admin')
 admin.goto(base+'/lang/fa',wait_until='networkidle')
 admin.goto(base+'/admin/listing-questions',wait_until='networkidle')
 row=admin.locator('.fi-ta-row').filter(has_text=question)
 row.get_by_role('button',name='تأیید انتشار',exact=True).click()
 admin.get_by_role('button',name='تایید',exact=True).click()
 expect(row).to_contain_text('منتشرشده')
 guest.reload(wait_until='networkidle')
 expect(guest.locator('[data-public-question]')).to_have_count(1)
 assert answer in guest.locator('[data-public-question]').inner_text()
 assert 'PRIVATE BUYER FIXTURE' not in guest.locator('main').inner_text()
 assert state['buyer_email'] not in guest.content()
 for page in [guest,buyer,seller]:
  for width in [360,390,768,1440]:
   page.set_viewport_size({'width':width,'height':1000})
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(page.url,width)
  assert page.locator('html').get_attribute('dir')=='rtl'
  assert page.locator('.listing-questions').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
 guest.set_viewport_size({'width':390,'height':844})
 guest.locator('.listing-questions').screenshot(path='/tmp/listing-questions-mobile.png')
 seller.reload(wait_until='networkidle')
 seller.get_by_label('پاسخ فروشنده',exact=True).fill('پاسخ ویرایش‌شده و بررسی‌نشده')
 seller.locator('input[name=public_consent]').check()
 seller.get_by_role('button',name='ارسال پاسخ برای بررسی',exact=True).click()
 seller.wait_for_load_state('networkidle')
 assert records()[0]['state']=='answered'
 guest.reload(wait_until='networkidle')
 assert guest.locator('[data-public-question]').count()==0
 seller.set_viewport_size({'width':390,'height':844})
 field=seller.get_by_label('پاسخ فروشنده',exact=True)
 field.focus()
 assert field.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none"')
 seller.goto(base+'/lang/en',wait_until='networkidle')
 seller.goto(base+'/panel/listing-questions?role=received',wait_until='networkidle')
 assert seller.locator('html').get_attribute('dir')=='ltr'
 assert seller.get_by_label('Seller answer',exact=True).is_visible()
 buyer.reload(wait_until='networkidle')
 buyer.locator('input[name=confirm_withdraw]').check()
 buyer.get_by_role('button',name='پس‌گرفتن سؤال',exact=True).click()
 buyer.wait_for_load_state('networkidle')
 assert records()[0]['deleted_at'] is not None
 assert buyer.locator('[data-owned-question]').count()==0
 guest.goto(base+'/lang/en',wait_until='networkidle')
 guest.goto(url,wait_until='networkidle')
 assert guest.locator('html').get_attribute('dir')=='ltr'
 assert guest.locator('[data-public-question]').count()==0
 assert not errors,errors
 browser.close()
print('PASS native buyer consent/question, private seller answer, actual Filament publish, public anonymous pair, re-review hiding, withdrawal, four widths, RTL/LTR, font and focus.')
