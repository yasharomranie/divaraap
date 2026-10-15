import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest
import zipfile


class PublicInstallerTest(unittest.TestCase):
    def test_public_archive_excludes_installation_secrets_and_retains_activation_helper(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            build = root / 'build'
            for name in ['divaraap/storage', 'divaraap/bootstrap/cache', 'divaraap/vendor/composer', 'public_html/js/filament', 'public_html/build']:
                (build / name).mkdir(parents=True)
            files = {
                'divaraap/vendor/composer/installed.json': '{"dev": false}',
                'public_html/build/manifest.json': '{}',
                'INSTALLATION-CODE.txt': 'private-installation-code',
                'divaraap/install.token': 'private-installation-code-hash',
                'CREATE-INSTALLATION-KEY.html': '<html>Activation helper</html>',
            }
            for name, content in files.items():
                (build / name).write_text(content)
            script = Path(__file__).resolve().parents[2] / 'scripts/finalize-shared-package.py'
            subprocess.run([sys.executable, str(script), '--output', directory, '--public-release'], check=True, capture_output=True)
            with zipfile.ZipFile(root / 'divaraap-shared-install.zip') as archive:
                self.assertNotIn('INSTALLATION-CODE.txt', archive.namelist())
                self.assertNotIn('divaraap/install.token', archive.namelist())
                self.assertIn('CREATE-INSTALLATION-KEY.html', archive.namelist())
                self.assertIn('public_html/build/manifest.json', archive.namelist())
            manifest = json.loads((root / 'manifest.json').read_text())
            self.assertNotIn('INSTALLATION-CODE.txt', manifest)
            self.assertNotIn('divaraap/install.token', manifest)
            subprocess.run([sys.executable, str(script), '--output', directory], check=True, capture_output=True)
            with zipfile.ZipFile(root / 'divaraap-shared-install.zip') as archive:
                self.assertEqual(b'private-installation-code', archive.read('INSTALLATION-CODE.txt'))
                self.assertIn('divaraap/install.token', archive.namelist())


if __name__ == '__main__':
    unittest.main()
