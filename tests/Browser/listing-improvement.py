import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
id = json.loads((root / 'storage/framework/improvement-review.json').read_text())['listing']
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390, 'height':844})
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://localhost:8000/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa', wait_until='networkidle')
    response = page.goto(f'http://localhost:8000/panel/my-listings/{id}/improve', wait_until='networkidle')
    assert response.status == 200 and 'no-store' in response.headers['cache-control']
    assert '۰ از ۵' in page.locator('[data-guide-progress]').inner_text()
    assert page.locator('[data-guide-check][data-complete="false"]').count() == 5
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width, 'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        page.screenshot(path=f'/tmp/listing-improvement-{width}.png',full_page=True)
    page.set_viewport_size({'width':390, 'height':844})
    link = page.locator('[data-guide-check="title"] a')
    link.focus()
    page.keyboard.press('Enter')
    page.wait_for_url('**/edit#title')
    assert page.locator('#title').is_visible()
    page.locator('#title').fill('گوشی آزمایشی سالم با لوازم اصلی')
    page.locator('#description').fill('توضیح واقعی وضعیت کالا و لوازم همراه و شرایط معامله. ' * 5)
    page.locator('#price').fill('0')
    page.locator('#country').fill('Iran')
    page.locator('#city').fill('Tehran')
    page.locator('form.listing-edit button[type="submit"]').click()
    page.wait_for_load_state('networkidle')
    assert page.locator('#status').input_value() == 'pending'
    page.locator('#listing-photo').set_input_files(str(root / 'public/img/category/car.png'))
    page.get_by_role('button',name='افزودن عکس',exact=True).click()
    page.wait_for_url('**/improve')
    assert '۵ از ۵' in page.locator('[data-guide-progress]').inner_text()
    assert page.locator('[data-guide-check][data-complete="true"]').count() == 5
    native = browser.new_context(java_script_enabled=False,storage_state=context.storage_state())
    plain = native.new_page()
    plain.goto(f'http://localhost:8000/panel/my-listings/{id}/improve',wait_until='networkidle')
    assert plain.locator('[data-guide-check]').count() == 5
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(f'http://localhost:8000/panel/my-listings/{id}/improve',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert '5 of 5' in page.locator('[data-guide-progress]').inner_text()
    for path in ['/panel/my-listings','/panel']:
        page.goto('http://localhost:8000'+path,wait_until='networkidle')
        for width in [360,1024,1440]:
            page.set_viewport_size({'width':width,'height':900})
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(path,width)
    assert not errors, errors
    browser.close()
print('PASS: stored checklist, keyboard fix link, real edit/save/upload, free price, preserved pending state, no-JS guide, RTL/LTR and responsive guide/panel entry layouts.')
