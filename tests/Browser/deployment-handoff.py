import json
import hashlib
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
    page = browser.new_page()
    installs = []
    checks = []
    page.route('**/handoff-status', lambda route: route.fulfill(body='<html><body><div id="deployment-status"></div><button data-deployment-refresh>status</button></body></html>', content_type='text/html'))
    def status_runner(route):
        body = json.loads(route.request.post_data)
        if body.get('action') == 'status':
            checks.append(body)
            route.fulfill(json={'ok':True,'state':'running','message':'database backup in progress'})
        else:
            installs.append(body)
            route.abort()
    page.route('**/deployment-runner.php', status_runner)
    page.goto('http://127.0.0.1:8000/handoff-status')
    page.add_script_tag(content=script)
    page.evaluate('(job)=>document.body.dispatchEvent(new CustomEvent("deployment-ready",{bubbles:true,detail:job}))', job)
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('ارتباط مرورگر')")
    for _ in range(2):
        page.locator('[data-deployment-refresh]').click()
        page.wait_for_function("document.getElementById('deployment-status').textContent.includes('database backup in progress')")
    assert len(installs)==1 and len(checks)==2
    page.evaluate("document.getElementById('deployment-status').textContent='stale browser text';document.body.dispatchEvent(new CustomEvent('deployment-status-updated',{bubbles:true,detail:{state:'prepared',message:'same server state refreshed'}}))")
    assert page.locator('#deployment-status').inner_text()=='same server state refreshed'
    print('Status refresh: repeated reads after lost install response, no extra install, unchanged server state updates browser text')
    page.close()
    page = browser.new_page()
    pending = []
    page.route('**/handoff-running', lambda route: route.fulfill(body='<html><body><div id="deployment-status"></div><button data-deployment-refresh>status</button></body></html>', content_type='text/html'))
    def running_runner(route):
        body = json.loads(route.request.post_data)
        if body.get('action') == 'status':
            route.fulfill(json={'ok':True,'state':'running','message':'migration still running'})
        else:
            pending.append(route)
    page.route('**/deployment-runner.php', running_runner)
    page.goto('http://127.0.0.1:8000/handoff-running')
    page.add_script_tag(content=script)
    page.evaluate('(job)=>document.body.dispatchEvent(new CustomEvent("deployment-ready",{bubbles:true,detail:job}))', job)
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('درخواست نصب ارسال شد')")
    page.locator('[data-deployment-refresh]').click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('migration still running')")
    assert len(pending)==1
    pending[0].fulfill(json={'ok':True,'message':'installation completed'})
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('installation completed')")
    print('Status while install is pending: independent request succeeds and the original install completes once')
    page.close()
    page = browser.new_page()
    calls = []
    page.route('**/handoff-upload', lambda route: route.fulfill(body='''<html><body><div wire:id="upload-fixture"><div id="deployment-status"></div><div data-deployment-upload><input type="file" data-deployment-package><input type="file" data-deployment-checksum><button data-deployment-upload-start>upload</button></div></div></body></html>''', content_type='text/html'))
    page.route('**/deployment-runner.php', lambda route: (calls.append(json.loads(route.request.post_data)), route.fulfill(json={'ok': True, 'message': 'upload installed'}))[-1])
    page.goto('http://127.0.0.1:8000/handoff-upload')
    page.evaluate('''() => {
        window.uploadCalls=[];
        window.Livewire={find:()=>({$call:async(method,...args)=>{
            window.uploadCalls.push([method,...args]);
            if(method==='startPackageUpload')return 'a'.repeat(32);
            if(method==='uploadPackageChunk')return args[1]+atob(args[2]).length;
            if(method==='finishPackageUpload')document.body.dispatchEvent(new CustomEvent('deployment-ready',{bubbles:true,detail:{id:'a'.repeat(32),token:'b'.repeat(64)}}));
        }})};
    }''')
    page.add_script_tag(content=script)
    data=b'x'*600000
    digest=hashlib.sha256(data).hexdigest()
    page.locator('[data-deployment-package]').set_input_files({'name':'divaraap-update.zip','mimeType':'application/zip','buffer':data})
    page.locator('[data-deployment-checksum]').set_input_files({'name':'UPDATE-SHA256SUMS.txt','mimeType':'text/plain','buffer':(digest+'  divaraap-update.zip').encode()})
    page.locator('[data-deployment-upload-start]').click()
    page.wait_for_function("document.getElementById('deployment-status').textContent.includes('upload installed')")
    recorded=page.evaluate('window.uploadCalls')
    assert recorded[0]==['startPackageUpload',len(data),digest]
    assert [call[2] for call in recorded if call[0]=='uploadPackageChunk']==[0,262144,524288]
    assert recorded[-1][0]=='finishPackageUpload' and calls==[job]
    print('Offline package upload: three bounded chunks, verified checksum input, one final install request')
    page.close()
    browser.close()
