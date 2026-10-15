import argparse
import hashlib
import json
from pathlib import Path
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
output = Path(args.output).resolve()
build = output / 'build'
private = build / 'divaraap'
public = build / 'public_html'
installed = json.loads((private / 'vendor/composer/installed.json').read_text())
if installed.get('dev', True):
    raise SystemExit('Production dependencies must be installed with --no-dev.')
for name in ['.env', 'installed.lock', 'installation.pending']:
    if (private / name).exists():
        raise SystemExit('Refusing to package a configured installation.')
for folder in ['storage', 'bootstrap/cache']:
    if any(p.is_file() for p in (private / folder).rglob('*')):
        raise SystemExit('Runtime files must not be packaged.')
if not (public / 'build/manifest.json').is_file():
    raise SystemExit('Frontend build is missing.')
if not (public / 'js/filament').is_dir():
    raise SystemExit('Filament assets missing.')
manifest = {}
for p in sorted(build.rglob('*')):
    if not p.is_file():
        continue
    name = p.relative_to(build).as_posix()
    if p.name in ['auth.json', '.env', '.git'] or '/.git/' in name or '/node_modules/' in name or '/tests/' in name and name.startswith('divaraap/tests/'):
        raise SystemExit('Unexpected file: ' + name)
    manifest[name] = hashlib.sha256(p.read_bytes()).hexdigest()
(output / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')
archive = output / 'divaraap-shared-install.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for p in sorted(build.rglob('*')):
        if p.is_dir():
            z.write(p, p.relative_to(build).as_posix() + '/')
        else:
            z.write(p, p.relative_to(build).as_posix())
with zipfile.ZipFile(archive) as z:
    if z.testzip() is not None:
        raise SystemExit('Archive integrity check failed.')
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(output / 'SHA256SUMS.txt').write_text(digest + '  ' + archive.name + '\n')
print(json.dumps({'archive': str(archive), 'bytes': archive.stat().st_size, 'files': len(manifest), 'sha256': digest}))
