import json
from pathlib import Path
from playwright.sync_api import sync_playwright

script = (Path(__file__).resolve().parents[2] / 'public/assets/deployment.js').read_text()
job = {'id': 'a' * 32, 'token': 'b' * 64}
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path='/usr/bin/chromium', args=['--no-sandbox', '--disable-dev-shm-usage'])
    for scenario in ['dom_without_event', 'event_before_dom', 'html_error', 'network_error']:
        page = browser.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        calls = []
        page.route('**/handoff-test', lambda route: route.fulfill(body='<html><body><main></main></body></html>', content_type='text/html'))
        def runner(route):
            calls.append(json.loads(route.request.post_data))
            if scenario == 'network_error':
                route.abort()
            elif scenario == 'html_error':
                route.fulfill(status=503, body='<html>private gateway details</html>', content_type='text/html')
            else:
                route.fulfill(json={'ok': True, 'message': 'installed successfully', 'steps': ['backup complete']})
        page.route('**/deployment-runner.php', runner)
        page.goto('http://127.0.0.1:8000/handoff-test')
        page.evaluate("window.legacyCalls=0;window.addEventListener('deployment-ready',()=>window.legacyCalls++)")
        page.add_script_tag(content=script)
        if scenario == 'event_before_dom':
            page.evaluate('(job) => document.body.dispatchEvent(new CustomEvent("deployment-ready", {detail:job,bubbles:true}))', job)
        page.evaluate('''(job) => {
            const status=document.createElement('div');status.id='deployment-status';document.body.append(status);
            const marker=document.createElement('span');marker.setAttribute('data-deployment-job',JSON.stringify(job));document.body.append(marker);
        }''', job)
        expected = 'HTTP 503' if scenario == 'html_error' else 'ارتباط مرورگر' if scenario == 'network_error' else 'installed successfully'
        page.wait_for_function('(text) => document.getElementById("deployment-status").textContent.includes(text)', arg=expected)
        page.evaluate('(job) => document.body.dispatchEvent(new CustomEvent("deployment-ready", {detail:job,bubbles:true}))', job)
        page.add_script_tag(content=script)
        page.wait_for_timeout(100)
        assert page.evaluate('window.legacyCalls') == 0
        assert calls == [job], (scenario, calls)
        assert 'private gateway details' not in page.locator('#deployment-status').inner_text()
        assert not errors, errors
        print(scenario + ': exactly one install request; result visible; no browser errors')
        page.close()
    browser.close()
