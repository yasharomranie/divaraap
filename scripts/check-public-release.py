import argparse
import json
from html.parser import HTMLParser
from pathlib import Path
import urllib.error
import urllib.request
from urllib.parse import urljoin, urlparse


class Assets(HTMLParser):
    def __init__(self):
        super().__init__()
        self.paths = []

    def handle_starttag(self, tag, attrs):
        values = dict(attrs)
        if tag == 'script' and values.get('src'):
            self.paths.append(values['src'])
        if tag == 'link' and values.get('rel') == 'stylesheet' and values.get('href'):
            self.paths.append(values['href'])


def check(base):
    origin = urlparse(base)
    if origin.scheme not in ['https', 'http'] or not origin.netloc or origin.username or origin.password:
        raise ValueError('A public HTTP(S) base URL without credentials is required.')
    results = []
    assets = set()
    for path in ['/', '/listings', '/login', '/register']:
        url = base.rstrip('/') + path
        try:
            with urllib.request.urlopen(url, timeout=25) as response:
                body = response.read(4 * 1024 * 1024).decode('utf-8', errors='replace')
                parser = Assets()
                parser.feed(body)
                own_assets = {urljoin(response.url, item) for item in parser.paths if urlparse(urljoin(response.url, item)).netloc == origin.netloc}
                assets.update(own_assets)
                passed = response.status == 200 and '<html' in body.lower() and bool(own_assets)
                results.append({'path': path, 'status': response.status, 'passed': passed})
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            results.append({'path': path, 'passed': False, 'error': str(error)[:200]})
    for asset in sorted(assets):
        try:
            with urllib.request.urlopen(asset, timeout=25) as response:
                content_type = response.headers.get('Content-Type', '')
                passed = response.status == 200 and 'text/html' not in content_type and bool(response.read(1))
                results.append({'asset': urlparse(asset).path, 'status': response.status, 'passed': passed})
        except (urllib.error.URLError, TimeoutError, OSError) as error:
            results.append({'asset': urlparse(asset).path, 'passed': False, 'error': str(error)[:200]})
    return {'base_url': base, 'passed': all(item['passed'] for item in results), 'checks': results, 'scope': 'Public pages and same-origin scripts/styles only; private admin, database and installed commit are not verified.'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--base-url', required=True)
    parser.add_argument('--output')
    args = parser.parse_args()
    report = check(args.base_url)
    payload = json.dumps(report, ensure_ascii=False, indent=2)
    if args.output:
        Path(args.output).write_text(payload)
    print(payload)
    raise SystemExit(0 if report['passed'] else 1)
