import socket,json,os,base64,struct,subprocess,hashlib
from pathlib import Path
import urllib.request,time
project_root=Path(__file__).resolve().parent.parent
for route in ['/', '/listings', '/login']:
 with urllib.request.urlopen('http://127.0.0.1:8000'+route,timeout=10) as response:
  body=response.read().decode()
  assert response.status==200 and '<html' in body and 'OpenClassify' in body
 print('HTTP '+route+': application response verified')
settings=dict(line.split('=',1) for line in (project_root/'.env').read_text().splitlines() if '=' in line)
key=settings['REVERB_APP_KEY']
s=socket.create_connection(('127.0.0.1',8080),timeout=10)
nonce=base64.b64encode(os.urandom(16)).decode()
s.sendall(f'GET /app/{key}?protocol=7&client=js&version=8.4.0&flash=false HTTP/1.1\r\nHost: localhost:8080\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {nonce}\r\nSec-WebSocket-Version: 13\r\n\r\n'.encode())
buf=b''
while b'\r\n\r\n' not in buf: buf+=s.recv(4096)
headers,buf=buf.split(b'\r\n\r\n',1)
assert headers.startswith(b'HTTP/1.1 101'), 'WebSocket upgrade failed'
accept=base64.b64encode(hashlib.sha1((nonce+'258EAFA5-E914-47DA-95CA-C5AB0DC85B11').encode()).digest())
assert accept in headers

def read(n):
 global buf
 while len(buf)<n: buf+=s.recv(4096)
 out,buf=buf[:n],buf[n:]
 return out

def receive():
 a,b=read(2);n=b&127
 if n==126:n=struct.unpack('!H',read(2))[0]
 if n==127:n=struct.unpack('!Q',read(8))[0]
 return json.loads(read(n))

def send(obj):
 payload=json.dumps(obj).encode();mask=os.urandom(4)
 header=bytes([129,128|len(payload)]) if len(payload)<126 else bytes([129,254])+struct.pack('!H',len(payload))
 s.sendall(header+mask+bytes(v^mask[i%4] for i,v in enumerate(payload)))
assert receive()['event']=='pusher:connection_established'
send({'event':'pusher:subscribe','data':{'channel':'phase1-smoke'}})
assert receive()['event']=='pusher_internal:subscription_succeeded'
result=subprocess.run(['docker','exec','divaraap-php','php','/app/scripts/runtime-smoke.php','broadcast'],check=True,capture_output=True)
json.loads(result.stdout)
event=receive()
assert event['event']=='phase1.ready'
data=event['data']; data=json.loads(data) if isinstance(data,str) else data
assert data['ok'] is True
s.close()
print('Reverb: WebSocket handshake, subscription and broadcast delivery passed.')

result=subprocess.run(['docker','exec','divaraap-php','php','/app/scripts/runtime-smoke.php','queue'],check=True,capture_output=True)
job_id=json.loads(result.stdout)['queue_id']
assert isinstance(job_id,(int,str)) and str(job_id).isdigit()
for attempt in range(20):
 result=subprocess.run(['docker','exec','divaraap-db','psql','-v','ON_ERROR_STOP=1','-U','openclassify','-d','openclassify','-tAc',f"SELECT count(*) FROM jobs WHERE id={int(job_id)}; SELECT count(*) FROM failed_jobs WHERE payload LIKE '%Phase 1 queue smoke%';"],check=True,capture_output=True,text=True)
 pending,failed=map(int,result.stdout.split())
 assert failed==0, 'Queue smoke job failed'
 if pending==0: break
 time.sleep(1)
else: raise AssertionError('Queue worker did not consume the smoke job')
print('Queue: log-only job consumed without a failed-job record.')
