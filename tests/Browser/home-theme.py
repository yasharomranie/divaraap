import os
from urllib.parse import urlparse
from playwright.sync_api import sync_playwright

base = os.environ.get('HOME_THEME_TEST_URL', 'http://127.0.0.1:8000').rstrip('/')
assert urlparse(base).hostname in ['127.0.0.1', 'localhost']
changes = {
    'colors.accent': '#234bde',
    'colors.page': '#fafaf8',
    'colors.surface': '#ffffff',
    'colors.hero': '#f0f1ec',
    'colors.header': '#ffffff',
    'colors.price': '#17221b',
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
        response = page.goto(base + '/', wait_until='networkidle')
        assert response and response.status == 200
        def css(selector, property):
            return page.locator(selector).first.evaluate('(el, property) => getComputedStyle(el).getPropertyValue(property)', property)
        assert css('body', 'background-color') == 'rgb(250, 250, 248)'
        assert css('.site-header', 'background-color') == 'rgb(255, 255, 255)'
        assert css('.home-collection--featured .listing-card__price', 'color') == 'rgb(23, 34, 27)'
        assert css('.home-collection--recent .listing-card__price', 'background-color') == 'rgb(35, 75, 222)'
        assert css('.home-collection--recent .listing-card__price', 'color') == 'rgb(255, 255, 255)'
        assert css('.listing-card__title', 'font-size') == '16.15px'
        assert css('#mobile-market-query', 'font-size') == '16px'
        assert page.locator('[data-home-details], .home-sidebar, .home-workspace, .home-collage').count() == 0
        assert page.locator('.home-directory a').count() > 1
        assert page.locator('.home-collection--recent .home-product-grid').evaluate('(el) => getComputedStyle(el).gridTemplateColumns.split(" ").length') == 1
        recent = page.locator('.home-collection--recent .listing-card').first
        assert recent.locator('.listing-card__media').bounding_box()['width'] == 96
        assert recent.bounding_box()['height'] < 180
        assert page.locator('.home-collection--featured .home-product-grid').evaluate('(el) => getComputedStyle(el).gridTemplateColumns.split(" ").length') == 2
        assert page.locator('.mobile-tabs').is_visible()
        assert page.locator('[data-home-showcase], [data-home-slide], [data-home-controls]').count() == 0
        assert css('.home-intro .title-hero', 'font-size') == '26px'
        for width in [360, 390, 768, 1024, 1440]:
            page.set_viewport_size({'width': width, 'height': 1000})
            page.wait_for_timeout(100)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), width
        assert page.locator('.home-product-grid').last.evaluate('(el) => getComputedStyle(el).gridTemplateColumns.split(" ").length') == 4
        assert not page.locator('.mobile-tabs').is_visible()
        assert css('.home-intro .title-hero', 'font-size') == '36px'
        assert css('.listing-card__title', 'font-size') == '21px'
        assert css('#hero-search', 'font-size') == '18px'
        for favorite in page.locator('.listing-card__favorite').all():
            box = favorite.bounding_box()
            assert box and 44 <= box['width'] <= 48 and 44 <= box['height'] <= 48, box
        for card in page.locator('.listing-card').all():
            assert card.evaluate('(el)=>el.scrollWidth <= el.clientWidth + 1')
        page.locator('#hero-search').focus()
        page.evaluate('document.activeElement?.blur(); window.scrollTo(0,0)')
        page.screenshot(path='/tmp/home-theme-desktop.png', full_page=True)
        page.screenshot(path='/tmp/adorax-home-desktop.png')
        page.set_viewport_size({'width': 390, 'height': 844})
        page.evaluate('document.activeElement?.blur(); window.scrollTo(0,0)')
        page.screenshot(path='/tmp/home-theme-mobile.png', full_page=True)
        page.screenshot(path='/tmp/adorax-home-mobile.png')
        page.screenshot(path='/tmp/adorax-mobile-option-2.png')
        assert page.locator('.mobile-market-profile').is_visible()
        assert page.locator('.mobile-market-notifications').is_visible()
        assert page.locator('.home-directory__start').is_visible()
        assert page.locator('.home-collection--featured .listing-card__time-badge').first.is_visible()
        page.locator('.app-location > summary').click()
        assert page.locator('#header-country').is_visible()
        page.locator('.app-location > summary').click()
        page.locator('#mobile-market-query').fill('گوشی')
        page.locator('.mobile-market-filter > summary').click()
        page.locator('#mobile-filter-sort').select_option('price_asc')
        page.locator('.mobile-market-filter button[type=submit]').click()
        page.wait_for_url('**/listings?**')
        assert 'search=' in page.url and 'sort=price_asc' in page.url
        assert not errors, errors
        print('Admin colors and responsive typography, compact horizontal latest cards, retired showcase absent, 2/4-column featured grids, five viewport sizes and search passed.')
    finally:
        restored = {key: original[key.split('.')[0]][key.split('.')[1]] if len(key.split('.')) == 2 else original[key.split('.')[0]][key.split('.')[1]][key.split('.')[2]] for key in changes}
        save(restored)
        browser.close()
