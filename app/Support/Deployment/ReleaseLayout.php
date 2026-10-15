<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use RuntimeException;

final class ReleaseLayout
{
    public function __construct(public readonly string $root, public readonly string $public) {}

    public function read(string $name): array
    {
        $path = $this->root.'/'.$name;

        return is_file($path) ? json_decode(file_get_contents($path), true, 32, JSON_THROW_ON_ERROR) : [];
    }

    public function write(string $name, array $data): void
    {
        $target = $this->root.'/'.$name;
        $temporary = $target.'.'.bin2hex(random_bytes(8)).'.tmp';
        if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false) {
            throw new RuntimeException('Cannot write deployment state.');
        }
        chmod($temporary, 0600);
        if (! rename($temporary, $target)) {
            throw new RuntimeException('Cannot activate deployment state.');
        }
    }

    public function code(?string $id): string
    {
        if ($id === null) {
            return $this->root;
        }
        if (! preg_match('/\A[a-f0-9]{32}\z/', $id)) {
            throw new RuntimeException('Invalid release identifier.');
        }
        $path = $this->root.'/releases/'.$id;
        if (! is_file($path.'/vendor/autoload.php') || ! is_file($path.'/bootstrap/app.php')) {
            throw new RuntimeException('Release is incomplete.');
        }

        return $path;
    }

    public function activate(string $id): void
    {
        $this->code($id);
        $state = $this->read('active-release.json');
        $this->write('active-release.json', ['current' => $id, 'previous' => $state['current'] ?? null, 'can_rollback' => true]);
    }

    public function rollback(): void
    {
        $state = $this->read('active-release.json');
        if (! ($state['can_rollback'] ?? false)) {
            throw new RuntimeException('No previous release is available.');
        }
        $this->code($state['previous'] ?? null);
        $this->write('active-release.json', ['current' => $state['previous'] ?? null, 'previous' => $state['current'] ?? null, 'can_rollback' => true]);
    }

    public function pruneUnused(): void
    {
        $state = $this->read('active-release.json');
        $keep = [$state['current'] ?? null, $state['previous'] ?? null];
        $job = $this->read('deployment-job.json');
        if (($job['state'] ?? null) === 'prepared' && ($job['expires'] ?? 0) > time()) {
            $keep[] = $job['id'];
        }
        foreach (glob($this->root.'/releases/*', GLOB_ONLYDIR) as $directory) {
            $id = basename($directory);
            if (! preg_match('/\A[a-f0-9]{32}\z/', $id) || in_array($id, $keep, true) || is_link($directory)) {
                continue;
            }
            foreach ([$directory, $this->public.'/_releases/'.$id] as $path) {
                if (! is_dir($path) || is_link($path)) {
                    continue;
                }
                $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($entries as $entry) {
                    $entry->isDir() && ! $entry->isLink() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
                }
                rmdir($path);
            }
        }
    }

    public function lock()
    {
        $handle = fopen($this->root.'/deployment.mutex', 'c');
        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another deployment is running.');
        }

        return $handle;
    }
}
