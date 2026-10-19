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
    page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
    page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
    page.wait_for_url('http://localhost:8000/admin')
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    page.goto(f'http://localhost:8000/panel/my-listings/{id}/edit',wait_until='networkidle')
    category = page.locator('[data-listing-text-suggestions]').get_attribute('data-category-id')
    original = 'گوشی آزمایشی ۱۲۸'
    description = 'این دستگاه همراه با جعبه ارائه می‌شود.\nلطفاً جزئیات آگهی را مطالعه کنید.'
    page.locator('#title').fill(original)
    page.locator('#description').fill(description)
    page.locator('#city').fill('تهران')
    tools = page.locator('[data-listing-text-suggestions]')
    tools.locator('summary').first.click()
    tools.locator('[data-suggest-generate]').click()
    tools.locator('[data-suggest-apply="title"]').first.wait_for()
    assert page.locator('#title').input_value() == original
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    page.set_viewport_size({'width':390,'height':844})
    page.locator('#title').fill('عنوان تازه و دستی')
    tools.locator('[data-suggest-apply="title"]').last.click()
    assert page.locator('#title').input_value() == 'عنوان تازه و دستی'
    assert 'ورودی فرم تغییر' in tools.locator('[data-suggest-status]').inner_text()
    page.locator('#title').fill(original)
    tools.locator('[data-suggest-generate]').click()
    tools.locator('[data-suggest-apply="title"]').first.wait_for()
    tools.locator('[data-suggest-apply="title"]').last.click()
    assert page.locator('#title').input_value() != original
    tools.locator('[data-suggest-undo]').click()
    assert page.locator('#title').input_value() == original
    page.route('**/panel/listing-text-suggestions',lambda route:route.abort())
    tools.locator('[data-suggest-generate]').click()
    page.wait_for_function('document.querySelector("[data-suggest-status]").textContent.includes("دریافت نشد")')
    assert page.locator('#title').input_value() == original
    page.unroute('**/panel/listing-text-suggestions')
    tools.locator('[data-suggest-generate]').click()
    tools.locator('[data-suggest-apply="title"]').first.wait_for()
    groups = tools.locator('[data-suggest-results] > details')
    groups.last.locator('summary').click()
    proposed = groups.last.locator('textarea').last.input_value()
    tools.locator('[data-suggest-apply="description"]').last.click()
    assert page.locator('#description').input_value() == proposed
    page.locator('form.listing-edit button[type="submit"]').click()
    page.wait_for_load_state('networkidle')
    assert page.locator('#description').input_value() == proposed
    assert page.locator('#status').input_value() == 'pending'
    native = browser.new_context(java_script_enabled=False, storage_state=context.storage_state())
    plain = native.new_page()
    plain.goto(f'http://localhost:8000/panel/my-listings/{id}/edit',wait_until='networkidle')
    plain.locator('#listing-text-suggestions summary').click()
    plain.locator('#listing-text-suggestions noscript').wait_for(state='visible')
    assert 'ساخت پیشنهاد به جاوااسکریپت نیاز دارد' in plain.locator('#listing-text-suggestions noscript').inner_text()
    assert not plain.locator('[data-suggest-generate]').is_visible()
    page.goto('http://localhost:8000/panel/create-listing',wait_until='networkidle')
    cid = page.locator('.quick-create').evaluate('el=>{while(el&&!el.hasAttribute("wire:id"))el=el.parentElement;return el.getAttribute("wire:id");}')
    page.evaluate('async ({id,category})=>{const c=Livewire.find(id);await c.set("selectedCategoryId",Number(category));await c.call("goToStep",3);}',{'id':cid,'category':category})
    page.locator('#listing-title').fill(original)
    page.locator('#listing-description').fill(description)
    wizard = page.locator('[data-listing-text-suggestions]')
    wizard.locator('summary').first.click()
    wizard.locator('[data-suggest-generate]').click()
    wizard.locator('[data-suggest-apply="title"]').last.wait_for()
    expected = wizard.locator('[data-suggest-results] > details').first.locator('textarea').last.input_value()
    wizard.locator('[data-suggest-apply="title"]').last.click()
    page.wait_for_function('({id,value})=>Livewire.find(id).get("listingTitle")===value',arg={'id':cid,'value':expected})
    wizard.locator('[data-suggest-undo]').click()
    page.wait_for_function('({id,value})=>Livewire.find(id).get("listingTitle")===value',arg={'id':cid,'value':original})
    page.evaluate('async id=>{const c=Livewire.find(id);await c.set("listingTitle","");await c.set("description","");await c.set("selectedCategoryId",null);await c.call("goToStep",1);}',cid)
    assert not errors, errors
    browser.close()
print('PASS: preview without mutation, stale-input protection, apply/undo, network failure preserves text, native save/moderation, no-JS fallback, Livewire synchronization and 4 responsive widths.')
