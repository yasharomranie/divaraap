import json,subprocess
from pathlib import Path
command=['docker','exec','-w','/app','divaraap-php','php','tests/Browser/availability-fixtures.php']
r=subprocess.run(command+['prepare-race'],check=True,capture_output=True,text=True)
assert 'Race prepared.' in r.stdout,r.stdout
state=json.loads(Path('storage/framework/availability-review.json').read_text())
processes=[subprocess.Popen(command+['race-'+reason],stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True) for reason in ['limited','break']]
results=[]
for process in processes:
 out,error=process.communicate(timeout=30)
 assert process.returncode==0,error
 results.append(json.loads(out)['result'])
assert sorted(results)==['saved','stale'],results
r=subprocess.run(command+['state'],check=True,capture_output=True,text=True)
current=json.loads(r.stdout)
assert current['settings']['revision']==state['race_revision']+1,current
print('PASS concurrent settings writes: one saved, one rejected as stale, exactly one revision increment.')
