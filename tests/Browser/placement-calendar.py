import json
from pathlib import Path
from playwright.sync_api import sync_playwright
state=json.loads((Path(__file__).resolve().parents[2]/'storage/framework/reservation-review.json').read_text())
base='http://localhost:8000'
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    context=browser.new_context(viewport={'width':1440,'height':1000})
    page=context.new_page()
    errors=[]
    page.on('pageerror',lambda e:errors.append(str(e)))
    page.goto(base+'/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+'/lang/fa',wait_until='networkidle')
    page.goto(base+'/admin/placement-reservations/create',wait_until='networkidle')
    title=state['prefix']+' browser'
    page.get_by_label('عنوان رزرو (قابل نمایش به مشتری)',exact=False).fill(title)
    page.get_by_label('جایگاه',exact=False).select_option(str(state['placement']))
    page.get_by_label('شروع رزرو',exact=False).fill(state['local_start'].replace(' ','T'))
    page.get_by_label('پایان رزرو',exact=False).fill(state['local_end'].replace(' ','T'))
    page.get_by_role('button',name='ایجاد',exact=True).click()
    page.wait_for_url(base+'/admin/placement-reservations')
    assert page.get_by_text(title,exact=True).is_visible()
    page.goto(base+'/admin/placement-calendar?month='+state['month'],wait_until='networkidle')
    assert page.get_by_text(title,exact=True).is_visible()
    assert page.locator('html').get_attribute('dir')=='rtl'
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    overlay=page.locator('.fi-sidebar-overlay')
    if overlay.count() and overlay.is_visible(): overlay.click(position={'x':10,'y':10})
    page.locator('#reservation-month').focus()
    assert page.locator('#reservation-month').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert page.locator('.placement-calendar').evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    page.screenshot(path='/tmp/placement-calendar-mobile.png')
    page.locator('#reservation-placement').select_option(str(state['placement']))
    page.wait_for_load_state('networkidle')
    assert page.get_by_text(title,exact=True).is_visible()
    page.goto(base+'/lang/en',wait_until='networkidle')
    page.goto(base+'/admin/placement-calendar?month='+state['month'],wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert page.get_by_text(title,exact=True).is_visible()
    page.goto(base+'/lang/fa',wait_until='networkidle')
    page.goto(base+'/admin/placement-reservations',wait_until='networkidle')
    row=page.locator('tr').filter(has_text=title)
    row.get_by_role('button',name='لغو رزرو',exact=True).click()
    page.get_by_label('دلیل لغو رزرو',exact=False).fill('آزمایش لغو تقویم')
    page.get_by_role('button',name='تایید',exact=True).click()
    row.get_by_text('لغوشده',exact=True).wait_for()
    page.goto(base+'/admin/placement-calendar?month='+state['month'],wait_until='networkidle')
    assert page.get_by_text(title,exact=True).count()==0
    assert not errors,errors
    browser.close()
print('PASS: real reservation creation, Tehran times, monthly calendar/filter, cancellation release, four widths, RTL/LTR, focus and font')
