import json,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

base='http://localhost:8000'
state=json.loads(Path('storage/framework/price-guide-review.json').read_text())
errors=[]
def fixture(action):
    result=subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/price-guide-fixtures.php',action],check=True,capture_output=True,text=True)
    assert 'changed.' in result.stdout or 'hidden.' in result.stdout,result.stdout
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    page=browser.new_context(viewport={'width':390,'height':844}).new_page()
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base+'/lang/fa',wait_until='networkidle')
    page.goto(base+'/price-guide',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='rtl'
    page.locator('#guide-category_id').select_option(str(state['category']))
    page.locator('#guide-city_id').select_option(str(state['city']))
    page.locator('#guide-search').fill('قیمت آزمایش 128')
    submit=page.get_by_role('button',name='نمایش راهنمای قیمت',exact=True)
    submit.focus()
    assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
    submit.click()
    page.wait_for_load_state('networkidle')
    assert '۵٬۵۰۰٬۰۰۰' in page.locator('[data-price-median]').inner_text()
    assert page.locator('.price-guide').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+1'),width
    page.set_viewport_size({'width':390,'height':844})
    page.screenshot(path='/tmp/price-guide-mobile.png',full_page=True)
    page.locator('#guide-condition').select_option('New')
    page.get_by_role('button',name='نمایش راهنمای قیمت',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert page.locator('[data-price-insufficient]').count()==1
    assert page.locator('[data-price-median]').count()==0
    page.locator('#guide-condition').select_option('')
    page.get_by_role('button',name='نمایش راهنمای قیمت',exact=True).click()
    page.wait_for_load_state('networkidle')
    fixture('change-price')
    page.reload(wait_until='networkidle')
    assert '۶٬۵۰۰٬۰۰۰' in page.locator('[data-price-median]').inner_text()
    page.locator('.price-guide details summary').click()
    assert 'صدک' in page.locator('.price-guide details').inner_text()
    fixture('hide-listing')
    page.reload(wait_until='networkidle')
    assert page.locator('[data-price-insufficient]').count()==1
    page.goto(base+'/lang/en',wait_until='networkidle')
    page.goto(base+f'/price-guide?category_id={state["category"]}&search=128&currency=IRR',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert 'Price guide' in page.locator('h1').inner_text()
    assert 'Not enough data' in page.locator('[data-price-insufficient]').inner_text()
    assert not errors,errors
    browser.close()
print('PASS real price guide category/city/Persian search/condition/median/live price/visibility/low sample/method, four widths, RTL/LTR, font and focus.')
