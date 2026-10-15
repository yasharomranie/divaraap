from pathlib import Path
import re
import shutil

root = Path(__file__).resolve().parent.parent
source = root / 'vendor/ysfkaya/filament-phone-input/images/vendor/intl-tel-input/build'
target = root / 'public/vendor/filament-phone-input'
target.mkdir(parents=True, exist_ok=True)
for name in ['flags.webp', 'flags@2x.webp', 'globe.webp', 'globe@2x.webp']:
    shutil.copy2(source / name, target / name)
css = root / 'public/css/ysfkaya/filament-phone-input/filament-phone-input.css'
if not css.is_file():
    raise SystemExit('Publish Filament assets before preparing local assets.')
css.write_text(css.read_text().replace('url(/vendor/filament-phone-input/', 'url(../../../vendor/filament-phone-input/'))
for path in (root / 'public').rglob('*.css'):
    if re.search(r'(?:url\(\s*[\'\"]?|@import\s*[\'\"])(?:https?:)?//', path.read_text()):
        raise SystemExit('External CSS dependency: ' + str(path.relative_to(root)))
print('Local phone images published; CSS dependencies use local version-relative URLs.')
