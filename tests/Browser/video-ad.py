import json
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright
root = Path(__file__).resolve().parents[2]
state = json.loads((root/'storage/framework/video-ad-review.json').read_text())
base = 'http://localhost:8000'
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox','--disable-dev-shm-usage'])
    context = browser.new_context(viewport={'width':390,'height':844})
    page = context.new_page()
    errors, clips, clicks = [], [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    def request(req):
        if '.mp4' in req.url: clips.append(req.url)
        if '/advertising/events' in req.url and req.method == 'POST' and req.post_data_json.get('kind') == 'click': clicks.append(req.post_data_json)
    page.on('request', request)
    page.goto(base+'/lang/fa', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    card = page.locator(f'[data-banner="{state["banner"]}"]').first
    video = card.locator('video')
    card.scroll_into_view_if_needed()
    assert video.get_attribute('preload') == 'none'
    assert video.get_attribute('autoplay') is None and video.get_attribute('loop') is None
    assert video.evaluate('(v)=>v.paused') and not clips
    assert card.locator('a').count() == 1
    assert card.locator('a video').count() == 0
    assert 'PRIVATE VIDEO OWNER' not in card.inner_text()
    assert video.locator('track').get_attribute('srclang') == 'fa'
    for width in [360,390,768,1440]:
        page.set_viewport_size({'width':width,'height':900})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert video.is_visible()
    page.set_viewport_size({'width':390,'height':844})
    card.locator('summary').click()
    assert card.get_by_text('این ویدئوی آزمایشی تصویر یک صفحه آبی را نشان می‌دهد و گفتار ندارد.', exact=True).is_visible()
    card.locator('summary').focus()
    page.keyboard.press('Tab')
    page.keyboard.press('Shift+Tab')
    assert card.locator('summary').evaluate('(e)=>getComputedStyle(e).outlineStyle != "none"')
    assert card.evaluate('(e)=>getComputedStyle(e).fontFamily.includes("Vazirmatn")')
    card.screenshot(path='/tmp/video-ad-mobile.png')
    video.focus()
    page.keyboard.press('Space')
    page.wait_for_function('(()=>{const v=document.querySelector("video[data-ad-video]");return !v.paused && v.currentTime>0})()')
    page.wait_for_function('(()=>{const v=document.querySelector("video[data-ad-video]");return v.textTracks.length>0 && v.textTracks[0].cues && v.textTracks[0].cues.length>0})()')
    assert clips and not clicks
    page.mouse.wheel(0,2500)
    page.wait_for_function('document.querySelector("video[data-ad-video]").paused')
    assert not video.evaluate('(v)=>v.ended')
    card.scroll_into_view_if_needed()
    card.locator('a').click()
    page.wait_for_url(base+f'/listings/{state["listing"]}')
    assert clicks
    page.goto(base+'/admin/login', wait_until='networkidle')
    page.get_by_role('button', name='Admin (a@a.com)', exact=True).click()
    page.wait_for_url(base+'/admin')
    page.goto(base+f'/admin/banners/{state["banner"]}/edit', wait_until='networkidle')
    assert page.get_by_text('تبلیغ ویدئویی اختیاری', exact=True).count() > 0
    page.get_by_label('متن و توضیح کامل محتوای ویدئو', exact=False).fill('متن تازه آزمایش ویدئو؛ نمایش صفحه آبی بدون گفتار.')
    page.get_by_role('button', name='ذخیره', exact=True).click()
    page.get_by_text('ذخیره شد', exact=True).wait_for()
    page.goto(base+f'/admin/advertising-render/{state["banner"]}?device=mobile', wait_until='networkidle')
    assert page.locator('[data-ad-video-card]').count() == 1
    assert page.locator('video').count() == 0 and page.locator('[data-event-token]').count() == 0
    assert page.locator('[data-banner] a').count() == 0
    native = browser.new_context(java_script_enabled=False, viewport={'width':390,'height':844}).new_page()
    native.goto(base, wait_until='networkidle')
    native_card = native.locator(f'[data-banner="{state["banner"]}"]')
    assert native_card.locator('video').get_attribute('controls') is not None
    native_card.locator('summary').click()
    assert native_card.get_by_text('متن تازه آزمایش ویدئو؛ نمایش صفحه آبی بدون گفتار.', exact=True).is_visible()
    native_card.locator('a').click()
    native.wait_for_url(base+f'/listings/{state["listing"]}')
    failure = browser.new_context(viewport={'width':390,'height':844}).new_page()
    failure.route('**/*.mp4', lambda route: route.abort())
    failure.goto(base, wait_until='networkidle')
    broken_card = failure.locator(f'[data-banner="{state["banner"]}"]')
    broken_card.scroll_into_view_if_needed()
    broken_card.locator('video').focus()
    failure.keyboard.press('Space')
    broken_card.locator('[data-ad-video-fallback]').wait_for(state='visible')
    assert broken_card.locator('a').is_visible()
    page.goto(base+'/lang/en', wait_until='networkidle')
    page.goto(base, wait_until='networkidle')
    assert page.locator('html').get_attribute('dir') == 'ltr'
    assert card.get_by_text('Read video transcript', exact=True).is_visible()
    assert card.locator('track').get_attribute('srclang') == 'fa'
    subprocess.run(['docker','exec','-w','/app','divaraap-php','php','tests/Browser/video-ad-fixtures.php','change-video'], check=True)
    page.goto(base, wait_until='networkidle')
    assert page.locator(f'[data-banner="{state["banner"]}"]').count() == 0
    assert not errors, errors
    browser.close()
print('PASS: no MP4 request before trusted keyboard playback, actual playback and offscreen pause, separate tracked listing CTA, captions/transcript, real admin edit/save, poster-only preview, native text/link without JavaScript, network-error fallback, source-change suppression, RTL/LTR, font, focus and four widths.')
