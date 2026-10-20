import json
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/response-review.json').read_text())
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium',args=['--no-sandbox','--disable-dev-shm-usage'])
    page = browser.new_page(viewport={'width':390,'height':844})
    page.goto('http://localhost:8000/lang/fa',wait_until='networkidle')
    for path in [f'/sellers/{state["user"]}',f'/listings/{state["listings"][0]}']:
        page.goto('http://localhost:8000'+path,wait_until='networkidle')
        summary = page.locator('.seller-response')
        assert '۱۰ دقیقه' in summary.inner_text()
        assert '۸۳' in summary.inner_text()
        assert 'PRIVATE RESPONSE FIXTURE' not in page.locator('body').inner_text()
        for width in [360,390,768,1440]:
            page.set_viewport_size({'width':width,'height':900})
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(path,width)
    page.goto('http://localhost:8000/lang/en',wait_until='networkidle')
    page.goto(f'http://localhost:8000/sellers/{state["user"]}',wait_until='networkidle')
    assert page.locator('html').get_attribute('dir')=='ltr'
    assert 'About 10 minutes' in page.locator('.seller-response').inner_text()
    page.screenshot(path='/tmp/seller-response.png')
    browser.close()
print('PASS: real median and response rate, private messages excluded, public seller/listing rendering, Persian/English and four widths.')
