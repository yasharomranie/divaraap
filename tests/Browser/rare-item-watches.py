import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

base='http://localhost:8000'
state=json.loads(Path('storage/framework/rare-watch-review.json').read_text())
cmd=['docker','exec','-w','/app','divaraap-php','php','tests/Browser/rare-watch-fixtures.php']
def fixture(action):
    return subprocess.check_output(cmd+[action],text=True).strip()
def counts():
    return json.loads(fixture('counts'))
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    page=browser.new_page(viewport={'width':390,'height':844})
    errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base+'/login',wait_until='networkidle')
    page.locator('#email').fill(state['email'])
    page.locator('#password').fill('Browser-test-123!')
    page.locator('button[type="submit"]').click()
    page.wait_for_url(lambda u:'/login' not in u)
    page.goto(base+'/lang/fa',wait_until='networkidle')
    page.goto(base+'/panel/rare-watches/create',wait_until='networkidle')
    page.locator('#rare-search').fill('نوكيا كلاسيك 123')
    page.locator('#rare-category').select_option(str(state['category']))
    page.locator('#rare-max_price').fill('2000000')
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
    page.set_viewport_size({'width':390,'height':844})
    form=page.locator('.rare-watches')
    assert form.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    submit=page.get_by_role('button',name='فعال‌کردن پیگیری',exact=True)
    submit.focus()
    assert submit.evaluate('(e)=>getComputedStyle(e).outlineStyle!="none" || getComputedStyle(e).boxShadow!="none"')
    page.screenshot(path='/tmp/rare-watch-create-mobile.png',full_page=True)
    submit.click()
    page.wait_for_url(base+'/panel/rare-watches')
    card=page.locator('[data-rare-watch]').first
    assert '۲٬۰۰۰٬۰۰۰' in card.inner_text()
    assert counts()['notifications']==0
    publication=fixture('publish')
    assert publication.startswith('Published '),publication
    listing_id=publication.split()[-1]
    page.goto(base+'/panel/notifications',wait_until='networkidle')
    assert 'کالای مطابق پیگیری شما آگهی شد' in page.locator('main').inner_text()
    assert counts()['notifications']==1
    page.reload(wait_until='networkidle')
    assert counts()['notifications']==1
    page.locator('article').filter(has_text='کالای مطابق پیگیری شما آگهی شد').get_by_role('button',name='مشاهده',exact=True).click()
    page.wait_for_url(base+'/listings/'+listing_id)
    page.goto(base+'/panel/rare-watches',wait_until='networkidle')
    card=page.locator('[data-rare-watch]').first
    card.get_by_role('button',name='توقف خبرها',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert 'خاموش' in card.inner_text()
    assert fixture('publish').startswith('Published ')
    page.reload(wait_until='networkidle')
    assert counts()['notifications']==1
    card.get_by_role('button',name='فعال‌سازی دوباره',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert counts()['notifications']==1
    assert fixture('expire')=='Expired.'
    page.reload(wait_until='networkidle')
    assert 'منقضی‌شده' in card.inner_text()
    card.get_by_role('button',name='تمدید ۳۰روزه و فعال‌سازی',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert card.get_by_role('button',name='توقف خبرها',exact=True).is_visible()
    assert fixture('publish').startswith('Published ')
    page.reload(wait_until='networkidle')
    assert counts()['notifications']==2
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
    page.set_viewport_size({'width':390,'height':844})
    page.screenshot(path='/tmp/rare-watch-mobile.png',full_page=True)
    page.goto(base+'/lang/en',wait_until='networkidle')
    page.goto(base+'/panel/rare-watches',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert 'Rare item watches' in page.locator('.rare-watches').inner_text()
    page.locator('[data-rare-watch]').first.get_by_role('button',name='Remove watch',exact=True).click()
    page.wait_for_load_state('networkidle')
    assert page.locator('[data-rare-watch]').count()==0
    assert not errors,errors
    browser.close()
print('PASS private rare watch creation, future-publication notification, real listing destination, pause/resume, expiry/renewal, delete, four widths, RTL/LTR, font and focus.')
