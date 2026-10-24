import argparse
import base64
import json
import mimetypes
from pathlib import Path
from urllib.parse import urlparse
import zipfile

from playwright.sync_api import sync_playwright

parser = argparse.ArgumentParser()
parser.add_argument('--archive')
parser.add_argument('--expect-legacy-failure', action='store_true')
parser.add_argument('--fail-tiles', action='store_true')
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]
archive = zipfile.ZipFile(args.archive) if args.archive else None


def read_asset(relative):
    if archive:
        return archive.read('public_html/build/' + relative)
    return (root / 'public/build' / relative).read_bytes()


manifest = json.loads(read_asset('manifest.json'))
entry = manifest['resources/ts/app.ts']['file']
css = manifest['resources/css/app.css']['file']
prefix = '/_releases/map-regression/build/'
canvas = '<div class="listing-map-canvas" data-create-location-canvas data-location-canvas data-map-canvas></div>'
fixtures = {
    'create': '<details data-create-location open data-ready="Ready" data-failed="Failed"><summary>Location</summary>' + canvas + '<p data-create-location-status></p><button data-create-location-current hidden>Current</button><button data-create-location-clear hidden>Clear</button><input id="create-latitude"><input id="create-longitude"></details>',
    'owner': '<div data-listing-location data-ready="Ready" data-failed="Failed">' + canvas + '<p data-location-status></p><input id="map-latitude"><input id="map-longitude"></div>',
    'browse': '<div data-listing-map data-ready="Ready" data-failed="Failed">' + canvas + '<p data-map-status></p><button data-map-retry hidden>Retry</button><a data-map-search-area href="/listings/map">Area</a></div>',
}
tile = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aXioAAAAASUVORK5CYII=')
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    for name, body in fixtures.items():
        context = browser.new_context(viewport={'width':390, 'height':844})
        requests = []
        errors = []

        def serve(route):
            path = urlparse(route.request.url).path
            requests.append(path)
            if path == '/fixture':
                html = f'<!doctype html><html><head><link rel="stylesheet" href="{prefix}{css}"><script type="module" src="{prefix}{entry}"></script></head><body>{body}</body></html>'
                route.fulfill(body=html, content_type='text/html')
            elif path.startswith(prefix):
                relative = path[len(prefix):]
                try:
                    route.fulfill(body=read_asset(relative), content_type=mimetypes.guess_type(relative)[0] or 'application/octet-stream')
                except (FileNotFoundError, KeyError):
                    route.fulfill(status=404)
            else:
                route.fulfill(status=404)

        context.route('http://localhost/**', serve)
        context.route('https://tile.openstreetmap.org/**', lambda route: route.abort() if args.fail_tiles else route.fulfill(body=tile, content_type='image/png'))
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto('http://localhost/fixture', wait_until='networkidle')
        if args.expect_legacy_failure:
            assert any(path.startswith('/build/') for path in requests), requests
            assert page.locator('.leaflet-container').count() == 0
            print(f'Legacy failure reproduced: {name} requests CSS outside its release.')
        else:
            page.locator('.leaflet-container').wait_for()
            assert page.locator('.leaflet-control-zoom-in').is_visible()
            assert page.locator('.leaflet-pane').first.evaluate('el=>getComputedStyle(el).position') == 'absolute'
            assert any(path.startswith(prefix) and 'leaflet-' in path and path.endswith('.css') for path in requests), requests
            assert not any(path.startswith('/build/') for path in requests), requests
            assert not errors, errors
            if args.fail_tiles:
                page.get_by_text('Failed', exact=True).wait_for()
            if name == 'create':
                page.locator('[data-create-location-canvas]').click(position={'x':100, 'y':100})
                assert page.locator('#create-latitude').input_value() != ''
                page.locator('[data-create-location-clear]').click()
                assert page.locator('#create-latitude').input_value() == ''
            print(f'PASS: {name} map JS/CSS load from release prefix; no root build requests.')
        context.close()
    browser.close()
if archive:
    archive.close()
