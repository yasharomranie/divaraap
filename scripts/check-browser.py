import argparse
import json
from datetime import datetime, timezone
from pathlib import Path
import shutil
import sys

from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', default='http://127.0.0.1:8000')
parser.add_argument('--axe-path', required=True)
parser.add_argument('--browser', default=shutil.which('chromium'))
parser.add_argument('--listing-id', type=int)
parser.add_argument('--output')
args = parser.parse_args()
if not Path(args.axe_path).is_file():
    parser.error('--axe-path must point to the pinned axe-core JavaScript file')
paths = ['/', '/listings', '/pages/about', '/login', '/register', '/promotions']
if args.listing_id:
    paths.append('/listings/' + str(args.listing_id))
results = []
axe_version = None
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path=args.browser, args=['--no-sandbox', '--disable-dev-shm-usage'])
    for locale in ['fa', 'en']:
        for width in [390, 768, 1440]:
            page = browser.new_page(viewport={'width': width, 'height': 1000})
            page.goto(args.base_url.rstrip('/') + '/lang/' + locale, wait_until='networkidle')
            for path in paths:
                response = page.goto(args.base_url.rstrip('/') + path, wait_until='networkidle')
                page.add_script_tag(path=args.axe_path)
                axe_version = page.evaluate('axe.version')
                violations = page.evaluate("""async () => {
                    const result = await axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']}});
                    return result.violations.map(item => ({id: item.id, impact: item.impact, targets: item.nodes.map(node => node.target)}));
                }""")
                result = {
                    'locale': locale, 'width': width, 'path': path,
                    'status': response.status,
                    'html_locale': page.locator('html').get_attribute('lang'),
                    'direction': page.locator('html').get_attribute('dir'),
                    'overflow': page.evaluate('document.documentElement.scrollWidth > window.innerWidth'),
                    'font_available': page.evaluate("(locale) => document.fonts.check('16px Vazirmatn', locale === 'fa' ? 'سلام' : 'Sample') && [...document.fonts].some(font => font.family.includes('Vazirmatn') && font.status === 'loaded')", locale),
                    'violations': violations,
                }
                result['passed'] = result['status'] == 200 and result['html_locale'] == locale and result['direction'] == ('rtl' if locale == 'fa' else 'ltr') and not result['overflow'] and result['font_available'] and not violations
                results.append(result)
                print(locale, width, path, 'PASS' if result['passed'] else 'FAIL', flush=True)
            page.close()
    browser.close()
report = {'checked_at': datetime.now(timezone.utc).isoformat(), 'axe_version': axe_version, 'cases': len(results), 'failed': sum(not result['passed'] for result in results), 'results': results}
if args.output:
    Path(args.output).parent.mkdir(parents=True, exist_ok=True)
    Path(args.output).write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
else:
    print(json.dumps(report, ensure_ascii=False, indent=2))
sys.exit(1 if report['failed'] else 0)
