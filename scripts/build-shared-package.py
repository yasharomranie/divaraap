import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
output = Path(args.output).resolve()
if output == root or root in output.parents:
    raise SystemExit('Output must be outside the checkout.')
output.mkdir(parents=True, exist_ok=True)
build = output / 'build'
if build.exists():
    raise SystemExit('Choose a clean output directory; existing build preserved.')
private = build / 'divaraap'
public = build / 'public_html'
private.mkdir(parents=True)
public.mkdir()
tracked = subprocess.check_output(['git', 'ls-files', '-z'], cwd=root).decode().split('\0')
prefixes = ('app/', 'bootstrap/', 'config/', 'database/', 'lang/', 'Modules/', 'resources/', 'routes/')
files = {'artisan', 'composer.json', 'composer.lock', 'modules_statuses.json', '.env.shared.example'}
for name in tracked:
    if not name or not (name.startswith(prefixes) or name in files):
        continue
    if name.startswith('bootstrap/cache/'):
        continue
    source = root / name
    if not source.is_file():
        continue
    target = private / name
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(source, target)
shutil.copytree(root / 'vendor', private / 'vendor', ignore=shutil.ignore_patterns('.git', '.github', '.env', '.env.*'), symlinks=False)
for folder in ['assets', 'build', 'css', 'js', 'fonts', 'vendor', 'img', 'images']:
    if (root / 'public' / folder).is_dir():
        shutil.copytree(root / 'public' / folder, public / folder, symlinks=False)
for name in ['.htaccess', 'favicon.ico', 'favicon.svg', 'robots.txt']:
    if (root / 'public' / name).is_file():
        shutil.copy2(root / 'public' / name, public / name)
for name in ['index.php', 'install.php']:
    shutil.copy2(root / 'distribution' / name, public / name)
shutil.copy2(root / 'distribution/private.htaccess', private / '.htaccess')
shutil.copy2(root / 'distribution/INSTALL_FA.md', build / 'INSTALL_FA.md')
for name in ['storage/app/public', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache']:
    (private / name).mkdir(parents=True, exist_ok=True)
for module in (private / 'Modules').iterdir():
    if module.is_dir():
        (module / 'resources/views').mkdir(parents=True, exist_ok=True)
(private / 'bootstrap/hosting-public.php').write_text("<?php\n\ndeclare(strict_types=1);\n\nreturn dirname(__DIR__, 2).'/public_html';\n")
token = secrets.token_urlsafe(32)
(private / 'install.token').write_text(hashlib.sha256(token.encode()).hexdigest() + '\n')
(build / 'INSTALLATION-CODE.txt').write_text(token + '\n')
os.chmod(private / 'install.token', 0o600)
os.chmod(build / 'INSTALLATION-CODE.txt', 0o600)
revision = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=root).decode().strip()
metadata = {'source_revision': revision, 'source_dirty': bool(subprocess.check_output(['git', 'status', '--porcelain'], cwd=root).strip()), 'php': '8.4+', 'database': 'MySQL 8 or MariaDB 11.4', 'profile': 'shared', 'layout': ['divaraap', 'public_html'], 'installer': True}
(private / 'release.json').write_text(json.dumps(metadata, indent=2) + '\n')
print('Clean package staged. Run Composer --no-dev in build/divaraap before finalizing.')
