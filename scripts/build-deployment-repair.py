import argparse
import base64
import json
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
paths = {
    'app/Support/Deployment/DeploymentManager.php': 'app/Support/Deployment/DeploymentManager.php',
    'Modules/Admin/Filament/Pages/ManageDeployment.php': 'Modules/Admin/Filament/Pages/ManageDeployment.php',
    'resources/views/filament/manage-deployment.blade.php': 'resources/views/filament/manage-deployment.blade.php',
    '@public/assets/deployment.js': 'public/assets/deployment.js',
}
payload = base64.b64encode(json.dumps({name: (root / source).read_text() for name, source in paths.items()}, ensure_ascii=False).encode()).decode()
template = (root / 'distribution/deployment-repair.template.php').read_text()
output = Path(args.output)
output.mkdir(parents=True, exist_ok=True)
(output / 'deployment-repair-v1.php').write_text(template.replace('__DEPLOYMENT_REPAIR_PAYLOAD__', payload))
print('Recovery-code protected deployment-repair-v1.php generated.')
