import json
from pathlib import Path
from urllib.parse import urljoin
from playwright.sync_api import sync_playwright, expect
state=json.loads(Path('storage/framework/optimization-image-review.json').read_text())
for key in ['mobile','desktop']: state[key]=urljoin('http://localhost:8000',state[key])
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 page=browser.new_page(viewport={'width':390,'height':844},device_scale_factor=1)
 errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
 page.goto('http://localhost:8000/listings?search=Responsive+image+fixture',wait_until='networkidle')
 card=page.locator('.listing-card').filter(has_text='Responsive image fixture')
 image=card.locator('picture img')
 image.scroll_into_view_if_needed()
 expect(image).to_have_js_property('currentSrc',state['mobile'])
 expect(image).to_have_js_property('naturalWidth',720)
 assert image.get_attribute('loading')=='lazy'
 for width in [360,390,768,1440]:
  page.set_viewport_size({'width':width,'height':1000})
  assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),width
 expect(image).to_have_js_property('currentSrc',state['desktop'])
 expect(image).to_have_js_property('naturalWidth',1080)
 assert image.bounding_box()['width']<=card.bounding_box()['width']
 page.set_viewport_size({'width':390,'height':844})
 expect(image).to_have_js_property('currentSrc',state['mobile'])
 card.screenshot(path='/tmp/optimization-responsive-card.png')
 assert not errors,errors
 browser.close()
print(json.dumps({'status':'PASS','original_bytes':state['original_bytes'],'mobile_bytes':state['mobile_bytes'],'desktop_bytes':state['desktop_bytes'],'mobile_savings_against_desktop_percent':round(100*(1-state['mobile_bytes']/state['desktop_bytes']),1)}))
