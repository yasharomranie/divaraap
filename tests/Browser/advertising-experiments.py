import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

base = 'http://localhost:8000'
state = json.loads(Path('storage/framework/experiment-review.json').read_text())
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    admin = browser.new_context(viewport={'width':1440,'height':1000})
    page = admin.new_page()
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base+'/admin/advertising-experiments/create', wait_until='networkidle')
    page.get_by_label('عنوان داخلی*', exact=True).fill('Browser creative comparison')
    page.locator('select').filter(has=page.locator('option[value="'+str(state['placement'])+'"]')).first.select_option(str(state['placement']))
    for arm, banner in zip(['A','B'],state['banners']):
        page.get_by_role('button',name='طرح '+arm+'*',exact=True).click()
        page.locator('input[role="combobox"][aria-expanded="true"]').fill('Browser experiment '+arm)
        page.get_by_role('option',name='Browser experiment '+arm+' (#'+str(banner)+')',exact=True).click()
    page.get_by_role('button',name='ایجاد',exact=True).click()
    page.wait_for_url(base+'/admin/advertising-experiments')
    row = page.get_by_role('row').filter(has_text='Browser creative comparison')
    row.get_by_role('button',name='شروع آزمایش',exact=True).click()
    page.get_by_role('button',name='تایید',exact=True).click()
    page.wait_for_timeout(500)
    row.get_by_role('button',name='توقف آزمایش',exact=True).wait_for()
    customer = browser.new_context(viewport={'width':390,'height':844})
    public = customer.new_page()
    public.goto(base+'/',wait_until='networkidle')
    ad = public.locator('[data-event-token]').filter(has=public.locator('img[alt^="Browser experiment"]')).first
    if ad.count() == 0:
        ad = public.locator('img[alt^="Browser experiment"]').first.locator('xpath=ancestor::*[@data-event-token][1]')
    ad.scroll_into_view_if_needed()
    public.wait_for_timeout(2500)
    first = ad.locator('img').first.get_attribute('alt')
    public.reload(wait_until='networkidle')
    second = public.locator('img[alt^="Browser experiment"]').first.get_attribute('alt')
    assert first == second
    link = public.locator('img[alt^="Browser experiment"]').first.locator('xpath=ancestor::a[1]')
    link.click()
    public.wait_for_timeout(1000)
    data = subprocess.check_output(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/experiment-fixtures.php','metrics'],text=True)
    metrics = json.loads(data)
    assert sum(r['impressions'] for r in metrics['metrics'].values()) >= 1, metrics
    assert sum(r['clicks'] for r in metrics['metrics'].values()) >= 1, metrics
    row.get_by_role('button',name='مقایسه طرح‌ها',exact=True).click()
    report = page.locator('[data-experiment-report]')
    report.wait_for()
    assert 'برنده خودکار' in report.inner_text()
    assert page.locator('html').get_attribute('dir') == 'rtl'
    assert report.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    close_button = page.get_by_role('button',name='بستن',exact=True).last
    close_button.focus()
    assert close_button.evaluate('(e)=>getComputedStyle(e).outlineStyle != "none" || getComputedStyle(e).boxShadow != "none"')
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        page.wait_for_timeout(250)
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'), width
        assert report.evaluate('(e)=>e.scrollWidth <= e.clientWidth+1'), width
        if width == 390:
            page.screenshot(path='/tmp/advertising-experiment-report-mobile.png',full_page=True)
    page.screenshot(path='/tmp/advertising-experiment-report.png',full_page=True)
    page.get_by_role('button',name='بستن',exact=True).last.click()
    row.get_by_role('button',name='توقف آزمایش',exact=True).click()
    page.get_by_role('button',name='تایید',exact=True).click()
    page.wait_for_timeout(400)
    assert 'متوقف‌شده' in row.inner_text()
    page.goto(base+'/lang/en',wait_until='networkidle')
    page.goto(base+'/admin/advertising-experiments',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    row = page.get_by_role('row').filter(has_text='Browser creative comparison')
    row.get_by_role('button',name='Compare designs',exact=True).click()
    page.locator('[data-experiment-report]').wait_for()
    assert 'Descriptive comparison only' in page.locator('[data-experiment-report]').inner_text()
    browser.close()
print('Experiment creation/start, stable serving, real impression/click, report, stop, four widths and English passed.')
