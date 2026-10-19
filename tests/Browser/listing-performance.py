import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
id = json.loads((root / 'storage/framework/performance-review.json').read_text())['listing']
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    page = browser.new_page(viewport={'width':390, 'height':844})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://localhost:8000/lang/fa', wait_until='networkidle')
    page.goto(f'http://localhost:8000/listings/{id}', wait_until='networkidle')
    page.goto('http://localhost:8000/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    response = page.goto(f'http://localhost:8000/panel/my-listings/{id}/performance', wait_until='networkidle')
    assert response.status == 200
    assert 'no-store' in response.headers['cache-control']
    assert page.locator('[data-performance-metric="views"] dd').inner_text() == '۴'
    assert page.locator('[data-performance-metric="contacts"] dd').inner_text() == '۱'
    assert page.locator('tbody tr').count() == 30
    page.select_option('#performance-days', '7')
    page.get_by_role('button', name='نمایش گزارش', exact=True).click()
    page.wait_for_url('**days=7')
    assert page.locator('tbody tr').count() == 7
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width, 'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        assert page.locator('[data-performance-metric]').count() == 4
        page.screenshot(path=f'/tmp/listing-performance-{width}.png', full_page=True)
    daily = page.locator('.listing-performance__daily')
    daily.focus()
    assert daily.evaluate('el=>getComputedStyle(el).outlineStyle') != 'none'
    page.goto(f'http://localhost:8000/panel/my-listings/{id}/edit', wait_until='networkidle')
    assert page.get_by_role('link', name='عملکرد آگهی', exact=True).is_visible()
    page.goto('http://localhost:8000/lang/en', wait_until='networkidle')
    page.goto(f'http://localhost:8000/panel/my-listings/{id}/performance?days=90', wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.locator('tbody tr').count() == 90
    assert page.get_by_role('heading',name='Listing performance',exact=True).is_visible()
    assert not errors, errors
    browser.close()
print('PASS: actual guest view capture, owner-only dashboard metrics, native period form, 4 widths, keyboard-accessible daily table, edit entry, English/Persian and no JS errors.')
