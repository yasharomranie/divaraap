import base64
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 ctx=b.new_context(viewport={'width':390,'height':844},geolocation={'latitude':35.689234,'longitude':51.389876},permissions=['geolocation'])
 tile=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXioAAAAASUVORK5CYII=')
 ctx.route('https://tile.openstreetmap.org/**',lambda r:r.fulfill(body=tile,content_type='image/png'))
 page=ctx.new_page(); errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto('http://localhost:8000/admin/login',wait_until='networkidle')
 page.get_by_role('button',name='Admin (a@a.com)',exact=True).click();page.wait_for_url('http://localhost:8000/admin')
 page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
 page.goto('http://localhost:8000/panel/create-listing',wait_until='networkidle')
 cid=page.locator('.quick-create').evaluate('el=>{while(el&&!el.hasAttribute("wire:id"))el=el.parentElement;return el.getAttribute("wire:id");}')
 def step(n):
  page.evaluate('async ({id,n})=>await Livewire.find(id).call("goToStep",n)',{'id':cid,'n':n})
 step(3)
 details=page.locator('[data-create-location]')
 assert not details.evaluate('el=>el.open')
 details.locator('summary').click()
 page.locator('[data-create-location-canvas].leaflet-container').wait_for()
 page.locator('[data-create-location-canvas]').click(position={'x':100,'y':100})
 assert page.locator('#create-latitude').input_value()!=''
 lat=page.locator('#create-latitude').input_value()
 step(4)
 assert page.evaluate('id=>Livewire.find(id).get("latitude")',cid)==lat
 step(3);page.locator('[data-create-location] summary').click()
 page.locator('[data-create-location-canvas].leaflet-container').wait_for()
 assert page.locator('.leaflet-marker-icon').count()==1
 page.locator('[data-create-location-current]').click()
 page.wait_for_function('document.querySelector("#create-latitude").value === "35.689"')
 assert page.locator('#create-longitude').input_value()=='51.390'
 for w in [360,390,768,1440]:
  page.set_viewport_size({'width':w,'height':900})
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
 page.set_viewport_size({'width':390,'height':844})
 page.screenshot(path='/tmp/create-location-mobile.png',full_page=True)
 page.locator('[data-create-location-clear]').click()
 assert page.locator('#create-latitude').input_value()==''
 assert page.locator('.leaflet-marker-icon').count()==0
 step(4);step(3)
 assert page.locator('#create-latitude').input_value()==''
 page.locator('[data-create-location] summary').click()
 page.locator('[data-create-location-canvas].leaflet-container').wait_for()
 ctx.clear_permissions()
 page.locator('[data-create-location-current]').click()
 page.wait_for_function('document.querySelector("[data-create-location-status]").textContent.includes("دریافت نشد")')
 assert page.locator('#create-latitude').input_value()==''
 step(1)
 assert not errors,errors
 b.close()
print('PASS: optional disclosure, Livewire click selection persisted across steps, approximate geolocation, clear, denied permission, 4 responsive widths, cleanup; map tiles mocked.')
