from pathlib import Path
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 page=b.new_page(viewport={'width':390,'height':844})
 errors=[]
 page.on('pageerror',lambda e: errors.append(str(e)))
 page.goto('http://127.0.0.1:8000/admin/login',wait_until='networkidle')
 page.get_by_role('button',name='Admin (a@a.com)',exact=True).click()
 page.wait_for_url('http://127.0.0.1:8000/admin')
 page.goto('http://127.0.0.1:8000/lang/fa',wait_until='networkidle')
 r=page.goto('http://127.0.0.1:8000/panel/create-listing',wait_until='networkidle')
 assert r.status==200
 root=page.locator('.quick-create')
 cid=root.evaluate('el=>{while(el&&!el.hasAttribute("wire:id"))el=el.parentElement;return el.getAttribute("wire:id");}')
 def step(n):
  page.evaluate('async ({id,n})=>await Livewire.find(id).call("goToStep",n)',{'id':cid,'n':n})
  page.wait_for_timeout(250)
 step(1)
 for w in [360,390,768]:
  page.set_viewport_size({'width':w,'height':844})
  assert not page.locator('.site-header').is_visible()
  assert not page.locator('.site-footer').is_visible()
  assert not page.locator('.mobile-tabs').is_visible()
  assert page.locator('.quick-create__topbar').is_visible()
  assert page.locator('.quick-create__steps button').count()==4
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
  for icon in page.locator('.quick-create__add > svg').all(): assert icon.bounding_box()['height']==26
  assert page.locator('.quick-create__actions').bounding_box()['y']<844
 page.set_viewport_size({'width':390,'height':844})
 page.screenshot(path='/tmp/adorax-quick-create-empty.png')
 page.locator('#quick-photos').set_input_files(str(Path(__file__).resolve().parents[2] / 'public/img/category/car.png'))
 page.locator('.quick-create__cover').wait_for()
 page.wait_for_function('()=>{const el=document.querySelector(".quick-create__thumbnail img");return el && el.complete && el.naturalWidth>0;}')
 page.screenshot(path='/tmp/adorax-quick-create-model-one.png')
 page.locator('.quick-create__remove').first.click()
 page.locator('.quick-create__cover').wait_for(state='detached')
 page.locator('#quick-photos').set_input_files(str(Path(__file__).resolve().parents[2] / 'public/img/category/car.png'))
 page.locator('.quick-create__cover').wait_for()
 page.locator('[wire\\:click="goToCategoryStep"]').click()
 page.locator('#category-search').wait_for()
 page.locator('#category-search').fill('موبایل')
 page.wait_for_timeout(600)
 assert page.locator('#category-search').input_value()=='موبایل'
 step(3)
 for name in ['listing-title','listing-description','listing-price','listing-country','listing-city']: assert page.locator('#'+name).is_visible()
 page.locator('#listing-title').fill('آگهی آزمایشی مرورگر')
 page.locator('#listing-title').blur()
 page.screenshot(path='/tmp/adorax-quick-create-details.png')
 step(4)
 assert page.locator('[wire\\:click="publishListing"]').is_visible()
 assert 'آگهی آزمایشی مرورگر' in root.inner_text()
 page.set_viewport_size({'width':1440,'height':1000})
 assert page.locator('.site-header').is_visible()
 assert page.locator('.site-footer').is_visible()
 assert not page.locator('.quick-create__topbar').is_visible()
 step(1)
 page.locator('.quick-create__remove').first.click()
 page.locator('.quick-create__cover').wait_for(state='detached')
 page.evaluate('async id=>{const c=Livewire.find(id);await c.set("listingTitle","");await c.set("categorySearch","");}',cid)
 assert not errors,errors
 print('PASS: compact upload/remove, category search, four steps, details/preview, three mobile widths, desktop shell and no JS errors; no ad published.')
 b.close()
