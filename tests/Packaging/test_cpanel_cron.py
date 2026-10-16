import os
from pathlib import Path
import shlex
import subprocess
import tempfile
import unittest


class CpanelCronTest(unittest.TestCase):
    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.root = Path(self.temporary.name)
        self.storage = self.root / 'storage/app/private'
        self.storage.mkdir(parents=True)
        (self.root / '.git').mkdir()
        self.bin = self.root / 'bin'
        self.bin.mkdir()
        self.php = self.bin / 'php84'
        self.php.write_text('#!/bin/sh\nprintf "php %s\\n" "$*"\nprintf "called\\n" >> php-called\n')
        self.php.chmod(0o700)
        self.git = self.bin / 'git'
        self.git.write_text('#!/bin/sh\n[ ! -f storage/app/private/deploy.trigger ] || exit 99\nprintf "called\\n" >> git-called\nprintf "git fixture completed\\n"\n')
        self.git.chmod(0o700)
        command = (Path(__file__).resolve().parents[2] / 'scripts/cpanel-cron.txt').read_text()
        self.command = command.replace('/home/yas/adorax/divaraap', shlex.quote(str(self.root)))
        for candidate in ['/opt/cloudlinux/alt-php84/root/usr/bin/php', '/opt/alt/php84/usr/bin/php', '/opt/cpanel/ea-php84/root/usr/bin/php']:
            self.command = self.command.replace(candidate, shlex.quote(str(self.php)))

    def tearDown(self):
        self.temporary.cleanup()

    def run_cron(self):
        environment = dict(os.environ, PATH=str(self.bin) + os.pathsep + os.environ['PATH'])
        return subprocess.run(['/bin/sh', '-c', self.command], env=environment, cwd=self.root, capture_output=True)

    def signal(self):
        (self.storage / 'deploy.trigger').write_text('requested')

    def test_no_signal_is_a_noop(self):
        self.run_cron()
        self.assertFalse((self.root / 'git-called').exists())
        self.assertFalse((self.storage / 'deploy_log.txt').exists())

    def test_signal_is_consumed_before_git_and_commands_run_once(self):
        self.signal()
        (self.storage / 'deploy_log.txt').write_text('previous deployment output')
        result = self.run_cron()
        self.assertEqual(0, result.returncode, result.stderr)
        self.assertFalse((self.storage / 'deploy.trigger').exists())
        log = (self.storage / 'deploy_log.txt').read_text()
        self.assertNotIn('previous deployment output', log)
        self.assertIn('git fixture completed', log)
        self.assertIn('migrate --force', log)
        self.assertIn('migrate --path=database/settings --force', log)
        self.assertIn('optimize:clear', log)
        self.assertIn('optimize\n', log)
        self.run_cron()
        self.assertEqual('called\n', (self.root / 'git-called').read_text())
        self.assertEqual(4, len((self.root / 'php-called').read_text().splitlines()))

    def test_git_failure_stops_before_migrations(self):
        self.signal()
        self.git.write_text('#!/bin/sh\nprintf "git fixture failure\\n"\nexit 1\n')
        result = self.run_cron()
        self.assertNotEqual(0, result.returncode)
        self.assertFalse((self.root / 'php-called').exists())
        self.assertFalse((self.storage / 'deploy.trigger').exists())
        self.assertIn('git fixture failure', (self.storage / 'deploy_log.txt').read_text())

    def test_zip_or_versioned_installation_keeps_signal_without_running_git(self):
        self.signal()
        (self.root / '.git').rmdir()
        self.run_cron()
        self.assertTrue((self.storage / 'deploy.trigger').exists())
        self.assertFalse((self.root / 'git-called').exists())
        (self.root / '.git').mkdir()
        (self.root / 'active-release.json').write_text('{}')
        self.run_cron()
        self.assertTrue((self.storage / 'deploy.trigger').exists())
        self.assertIn('active Git checkout', (self.storage / 'deploy_log.txt').read_text())
        self.assertFalse((self.root / 'git-called').exists())

    def test_missing_php_keeps_signal_and_records_an_error(self):
        self.signal()
        self.php.unlink()
        self.run_cron()
        self.assertTrue((self.storage / 'deploy.trigger').exists())
        self.assertIn('PHP 8.4 CLI was not found', (self.storage / 'deploy_log.txt').read_text())
        self.assertFalse((self.root / 'git-called').exists())


if __name__ == '__main__':
    unittest.main()
