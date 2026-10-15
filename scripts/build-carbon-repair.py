import argparse
import hashlib
from pathlib import Path
import zipfile

parser = argparse.ArgumentParser()
parser.add_argument('--output', required=True)
args = parser.parse_args()
output = Path(args.output).resolve()
private = output / 'build/divaraap'
source = private / 'vendor/nesbot/carbon'
if not (source / 'src/Carbon/Laravel/ServiceProvider.php').is_file():
    raise SystemExit('Carbon service provider missing; refusing incomplete repair archive.')
archive = output / 'carbon-repair.zip'
with zipfile.ZipFile(archive, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=6) as z:
    for path in sorted(source.rglob('*')):
        if path.is_file():
            z.write(path, path.relative_to(private).as_posix())
with zipfile.ZipFile(archive) as z:
    if z.testzip() is not None or 'vendor/nesbot/carbon/src/Carbon/Laravel/ServiceProvider.php' not in z.namelist():
        raise SystemExit('Carbon repair archive verification failed.')
(output / 'CARBON-SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest() + '  carbon-repair.zip\n')
print('Carbon-only repair archive verified; no application settings or database files included.')
