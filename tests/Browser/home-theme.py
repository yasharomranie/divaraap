import os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

base = os.environ.get('HOME_THEME_TEST_URL', 'http://127.0.0.1:8000').rstrip('/')
assert urlparse(base).hostname in ['127.0.0.1', 'localhost']
changes = {
    'colors.accent': '#c52648',
    'colors.page': '#fff5ef',
    'colors.surface': '#fffdf9',
    'colors.hero': '#fff0dc',
    'colors.header': '#ffeddf',
    'colors.price': '#8f1733',
    'typography.hero_title.mobile': 26,
    'typography.hero_title.desktop': 36,
    'typography.listing_title.mobile': 19,
    'typography.listing_title.desktop': 21,
    'typography.search.mobile': 17,
    'typography.search.desktop': 18,
}
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    admin = browser.new_page(viewport={'width': 1440, 'height': 1000})
    admin.goto(base + '/admin/login', wait_until='networkidle')
    admin.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    admin.wait_for_url(base + '/admin')
    admin.goto(base + '/lang/fa', wait_until='networkidle')
    admin.goto(base + '/admin/manage-appearance-settings', wait_until='networkidle')
    component_id = admin.locator('[id="form.colors.accent"]').evaluate("el => { while (el && !el.hasAttribute('wire:id')) el = el.parentElement; return el.getAttribute('wire:id'); }")
    original = admin.evaluate('id => JSON.parse(JSON.stringify(Livewire.find(id).get("data")))', component_id)
    def save(data):
        admin.evaluate('async ({id, values}) => { const component=Livewire.find(id); for (const [key,value] of Object.entries(values)) await component.set("data."+key,value); }', {'id': component_id, 'values': data})
        admin.get_by_role('button', name='ذخیره', exact=True).click()
        admin.wait_for_timeout(1200)
    try:
        save(changes)
        page = browser.new_page(viewport={'width': 390, 'height': 844})
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(base + '/lang/fa', wait_until='networkidle')
        page.goto(base + '/', wait_until='networkidle')
        def css(selector, property):
            return page.locator(selector).first.evaluate('(el, property) => getComputedStyle(el).getPropertyValue(property)', property)
        assert css('body', 'background-color') == 'rgb(255, 245, 239)'
        assert css('.site-header', 'background-color') == 'rgb(255, 237, 223)'
        assert css('.listing-card__price', 'color') == 'rgb(143, 23, 51)'
        assert css('.listing-card__title', 'font-size') == '19px'
        assert css('#hero-search', 'font-size') == '17px'
        assert not page.locator('[data-home-details]').evaluate('(el) => el.open')
        assert page.locator('.mobile-tabs').is_visible()
        assert not page.locator('.home-sidebar').is_visible()
        assert page.locator('[data-home-next]').count() == 1
        page.locator('.home-showcase__summary').click()
        assert css('.hero__title', 'font-size') == '26px'
        heading = page.locator('[data-home-slide]:visible h2').inner_text()
        page.locator('[data-home-next]').click()
        assert page.locator('[data-home-slide]:visible h2').inner_text() != heading
        page.locator('[data-home-position="0"]').focus()
        page.keyboard.press('ArrowLeft')
        assert page.locator('[data-home-position="2"]').get_attribute('aria-current') == 'true'
        page.locator('.home-showcase__summary').click()
        for width in [360, 390, 768, 1024, 1440]:
            page.set_viewport_size({'width': width, 'height': 1000})
            page.wait_for_timeout(100)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), width
        assert page.locator('.home-sidebar').is_visible()
        assert not page.locator('.mobile-tabs').is_visible()
        assert css('.hero__title', 'font-size') == '36px'
        assert css('.listing-card__title', 'font-size') == '21px'
        assert css('#hero-search', 'font-size') == '18px'
        for card in page.locator('.listing-card').all():
            assert card.evaluate('(el)=>el.scrollWidth <= el.clientWidth + 1')
        page.screenshot(path='/tmp/home-theme-desktop.png', full_page=True)
        page.set_viewport_size({'width': 390, 'height': 844})
        page.screenshot(path='/tmp/home-theme-mobile.png', full_page=True)
        page.locator('#hero-search').fill('گوشی')
        page.locator('.hero__search button[type=submit]').click()
        page.wait_for_url('**/listings?**')
        assert 'search=' in page.url
        assert not errors, errors
        print('Saved admin colors and mobile/desktop fonts verified in computed styles; showcase click/keyboard, compact mobile, sidebar, 5 viewport sizes and search passed.')
    finally:
        restored = {key: original[key.split('.')[0]][key.split('.')[1]] if len(key.split('.')) == 2 else original[key.split('.')[0]][key.split('.')[1]][key.split('.')[2]] for key in changes}
        save(restored)
        browser.close()
