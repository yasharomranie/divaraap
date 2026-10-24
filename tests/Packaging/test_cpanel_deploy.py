import os
from pathlib import Path
import shutil
import subprocess
import tempfile
import unittest


class CpanelDeployTest(unittest.TestCase):
    def test_download_failure_never_starts_release(self):
        self.exercise(False)

    def test_success_runs_prepare_before_activation(self):
        self.exercise(True)

    def test_prepare_failure_stops_activation_and_reports_stage(self):
        self.exercise(True, prepare_failure=True)

    def exercise(self, succeeds, prepare_failure=False):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            checkout = base / 'source'
            (checkout / 'scripts').mkdir(parents=True)
            source = Path(__file__).resolve().parents[2]
            shutil.copy(source / 'scripts/cpanel-deploy.sh', checkout / 'scripts')
            subprocess.run(['git', 'init', '-q', str(checkout)], check=True)
            subprocess.run(['git', '-C', str(checkout), '-c', 'user.name=Test', '-c', 'user.email=test@example.test', 'commit', '--allow-empty', '-qm', 'test'], check=True)
            private = base / 'divaraap'
            private.mkdir()
            (private / '.env').write_text('APP_KEY=preserved')
            (private / 'installed.lock').touch()
            (base / 'public_html').mkdir()
            commands = base / 'commands'
            commands.mkdir()
            curl = commands / 'curl'
            curl.write_text('#!/bin/sh\nexit ' + ('0' if succeeds else '22') + '\n')
            php = commands / 'php'
            calls = base / 'calls'
            php.write_text('#!/bin/sh\nprintf "%s\\n" "$2" >> "$CALLS"\n')
            curl.chmod(0o700)
            if prepare_failure:
                php.write_text(php.read_text() + 'exit 9\n')
            php.chmod(0o700)
            environment = {**os.environ, 'PATH': str(commands) + ':' + os.environ['PATH'], 'DIVARAAP_PHP': str(php), 'CALLS': str(calls)}
            result = subprocess.run(['/bin/bash', str(checkout / 'scripts/cpanel-deploy.sh')], env=environment, capture_output=True)
            self.assertEqual(result.returncode == 0, succeeds and not prepare_failure)
            self.assertIn(b'Deployment timing:', result.stdout)
            self.assertIn(b'exit=' + (b'9' if prepare_failure else (b'0' if succeeds else b'22')), result.stdout)
            if succeeds and not prepare_failure:
                self.assertIn(b'stage=prepare seconds=', result.stdout)
            self.assertEqual(calls.read_text().splitlines() if calls.exists() else [], (['prepare'] if prepare_failure else ['prepare', 'activate']) if succeeds else [])
            self.assertEqual((private / '.env').read_text(), 'APP_KEY=preserved')
            self.assertEqual(list(private.glob('cpanel-stage.*')), [])


if __name__ == '__main__':
    unittest.main()
