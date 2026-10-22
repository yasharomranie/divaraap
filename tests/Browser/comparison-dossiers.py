import json, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

base='http://localhost:8000'
state=json.loads(Path('storage/framework/dossier-review.json').read_text())
errors=[]
def fixture(action):
    result=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/dossier-fixtures.php',action],check=True,capture_output=True,text=True)
    assert 'expired.' in result.stdout or 'changed.' in result.stdout,result.stdout
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
    for listing in state['listings']:
        owner.goto(base+f'/listings/{listing}',wait_until='networkidle')
        owner.locator(f'form[action="{base}/compare/{listing}"]').first.get_by_role('button').click()
        owner.wait_for_url(base+'/compare')
    owner.get_by_role('link',name='ذخیره و اشتراک مقایسه',exact=True).click()
    owner.wait_for_load_state('networkidle')
    assert owner.locator('html').get_attribute('dir')=='rtl'
    for width in [360,390,768,1440]:
        owner.set_viewport_size({'width':width,'height':1000})
        assert owner.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
    owner.set_viewport_size({'width':390,'height':844})
    owner.locator('#dossier-label').fill('نام خصوصی خانوادگی')
    owner.locator('#dossier-consent').check()
    submit=owner.get_by_role('button',name='ذخیره و ساخت لینک',exact=True)
    owner.keyboard.press('Tab')
    submit.focus()
    assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
    assert owner.locator('.dossier').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    owner.screenshot(path='/tmp/dossier-create-mobile.png',full_page=True)
    submit.click()
    owner.wait_for_url(base+'/panel/comparison-dossiers')
    record=owner.locator('[data-dossier]').first
    url=record.locator('.dossier-link').input_value()
    assert 'نام خصوصی خانوادگی' in record.inner_text()
    guest.goto(base+'/lang/fa',wait_until='networkidle')
    assert guest.goto(url,wait_until='networkidle').status==200
    assert guest.locator('.comparison-table thead th').count()==4
    assert 'نام خصوصی خانوادگی' not in guest.locator('main').inner_text()
    assert guest.locator('.comparison-table form').count()==0
    for width in [360,390,768,1440]:
        guest.set_viewport_size({'width':width,'height':1000})
        assert guest.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
    guest.set_viewport_size({'width':390,'height':844})
    region=guest.locator('.comparison-scroll')
    guest.keyboard.press('Tab')
    region.focus()
    assert region.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    guest.screenshot(path='/tmp/dossier-shared-mobile.png',full_page=True)
    guest.get_by_role('checkbox').check()
    guest.get_by_role('button',name='نمایش',exact=True).click()
    guest.wait_for_load_state('networkidle')
    assert guest.locator('.comparison-table tbody tr').count()>=2
    fixture('change-listing')
    guest.reload(wait_until='networkidle')
    assert 'گوشی به‌روز شده' in guest.locator('.comparison-table').inner_text()
    record.get_by_role('button',name='لغو لینک',exact=True).click()
    owner.wait_for_load_state('networkidle')
    assert guest.goto(url,wait_until='networkidle').status==404
    record=owner.locator('[data-dossier]').first
    record.get_by_role('button',name='ساخت لینک تازه؛ لغو قبلی',exact=True).click()
    owner.wait_for_load_state('networkidle')
    new_url=owner.locator('[data-dossier]').first.locator('.dossier-link').input_value()
    assert new_url!=url
    assert guest.goto(new_url,wait_until='networkidle').status==200
    fixture('expire')
    assert guest.goto(new_url,wait_until='networkidle').status==404
    owner.reload(wait_until='networkidle')
    owner.locator('[data-dossier]').first.get_by_role('button',name='ساخت لینک تازه؛ لغو قبلی',exact=True).click()
    owner.wait_for_load_state('networkidle')
    renewed=owner.locator('[data-dossier]').first.locator('.dossier-link').input_value()
    assert guest.goto(renewed,wait_until='networkidle').status==200
    guest.goto(base+'/lang/en',wait_until='networkidle')
    guest.goto(renewed,wait_until='networkidle')
    assert guest.locator('html').get_attribute('dir')=='ltr'
    assert 'Shared listing comparison' in guest.locator('h1').inner_text()
    owner.locator('[data-dossier]').first.get_by_role('button',name='حذف پرونده و لغو لینک',exact=True).click()
    owner.wait_for_load_state('networkidle')
    assert owner.locator('[data-dossier]').count()==0
    assert guest.goto(renewed,wait_until='networkidle').status==404
    assert not errors,errors
    browser.close()
print('PASS comparison save/share/private label/live values/differences/revoke/rotate/expire/remove, four widths, RTL/LTR, font and focus.')
