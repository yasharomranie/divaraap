import argparse
import hashlib
from pathlib import Path
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
output = Path(args.output).resolve()
build = output / 'build'
archive = output / 'divaraap-update.zip'
private_roots = {'app', 'bootstrap', 'config', 'database', 'lang', 'Modules', 'resources', 'routes', 'vendor', 'artisan', 'composer.json', 'composer.lock', 'modules_statuses.json', 'release.json'}
static_extensions = {'.js', '.mjs', '.css', '.map', '.json', '.wasm', '.woff', '.woff2', '.ttf', '.otf', '.eot', '.svg', '.png', '.jpg', '.jpeg', '.gif', '.webp', '.avif', '.ico', '.txt', '.webmanifest'}
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for p in sorted(build.rglob('*')):
        if not p.is_file():
            continue
        name = p.relative_to(build).as_posix()
        if name.startswith('divaraap/'):
            relative = p.relative_to(build / 'divaraap')
            if relative.parts[0] not in private_roots or name.startswith('divaraap/bootstrap/cache/') or str(relative) in ['bootstrap/hosting-public.php', 'bootstrap/hosting-runtime.php']:
                continue
            if p.name in ['.env', '.user.ini', 'auth.json']:
                raise SystemExit('Private runtime file in production update: ' + name)
        elif name.startswith('public_html/'):
            if p.suffix.lower() not in static_extensions or '.php' in name:
                continue
        else:
            continue
        z.write(p, name)
with zipfile.ZipFile(archive) as z:
    if z.testzip() is not None:
        raise SystemExit('Update archive integrity check failed.')
digest = hashlib.sha256(archive.read_bytes()).hexdigest()
(output / 'UPDATE-SHA256SUMS.txt').write_text(digest + '  ' + archive.name + '\n')
print(f'{archive}: {archive.stat().st_size} bytes; sha256 {digest}')
