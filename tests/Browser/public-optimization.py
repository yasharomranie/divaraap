import json
from playwright.sync_api import sync_playwright
base='http://localhost:8000'
results=[]
errors=[]
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
 for locale in ['fa','en']:
  page=browser.new_context(viewport={'width':390,'height':844}).new_page()
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.goto(base+'/lang/'+locale,wait_until='networkidle')
  for path in ['/','/listings','/login']:
   response=page.goto(base+path,wait_until='networkidle')
   assert response.status==200,(locale,path,response.status)
   assert page.locator('html').get_attribute('dir')==('rtl' if locale=='fa' else 'ltr')
   for width in [360,390,768,1440]:
    page.set_viewport_size({'width':width,'height':900})
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),(locale,path,width)
   page.evaluate('async()=>{await document.fonts.ready}')
   assert page.evaluate('(locale)=>document.fonts.check("16px Vazirmatn", locale==="fa"?"سلام":"Sample")',locale),(locale,path,page.evaluate('Array.from(document.fonts).map(f=>({family:f.family,status:f.status}))'))
   timings=page.evaluate('''()=>{
    const n=performance.getEntriesByType('navigation')[0];
    return {ttfb_ms:Math.round(n.responseStart-n.requestStart),dom_ready_ms:Math.round(n.domContentLoadedEventEnd-n.startTime),transfer_bytes:n.transferSize};
   }''')
   results.append({'locale':locale,'path':path,**timings})
  page.close()
 assert not errors,errors
 browser.close()
print(json.dumps({'status':'PASS','pages':results,'viewport_checks':24,'javascript_errors':errors},ensure_ascii=False))
