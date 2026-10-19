import json
from pathlib import Path
from playwright.sync_api import sync_playwright

root = Path(__file__).resolve().parents[2]
ids = json.loads((root / 'storage/framework/recommendation-review.json').read_text())['listings']
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    page = browser.new_page(viewport={'width':390, 'height':844})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto('http://localhost:8000/lang/fa', wait_until='networkidle')
    page.goto(f'http://localhost:8000/listings/{ids[0]}', wait_until='networkidle')
    section = page.locator('[data-related-listings]')
    assert section.locator('[data-related-id]').evaluate_all('els=>els.map(el=>Number(el.dataset.relatedId))') == ids[1:]
    assert 'هم‌شهر' in section.locator('.related-listing__reason').first.inner_text()
    assert 'قیمت نزدیک' in section.locator('.related-listing__reason').first.inner_text()
    assert 'هم‌شهر' not in section.locator('.related-listing__reason').last.inner_text()
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width, 'height':900})
        section.scroll_into_view_if_needed()
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        assert section.locator('.related-listing__reason').first.evaluate('el=>getComputedStyle(el).fontSize') == '12px'
        assert section.locator('.listing-card__title a').first.get_attribute('href').endswith('/'+str(ids[1]))
        page.screenshot(path=f'/tmp/recommendations-{width}.png', full_page=True)
    assert page.locator('html').get_attribute('dir') == 'rtl'
    page.set_viewport_size({'width':390,'height':844})
    link = section.locator('.listing-card__title a').first
    link.focus()
    page.keyboard.press('Enter')
    page.wait_for_url(f'http://localhost:8000/listings/{ids[1]}')
    assert page.locator('h1').inner_text() == 'گوشی پیشنهادی نزدیک با قیمت مناسب'
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(f'http://localhost:8000/listings/{ids[0]}',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert page.locator('[data-related-listings]').get_by_role('heading',name='Related listings').is_visible()
    assert 'Same category' in page.locator('.related-listing__reason').first.inner_text()
    assert not errors, errors
    browser.close()
print('PASS: relevant order, reason labels, native keyboard navigation, Persian RTL/English LTR, 4 widths and no browser errors.')
