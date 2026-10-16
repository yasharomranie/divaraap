<?php

declare(strict_types=1);

namespace App\Domain\Deployment;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class DeploymentTriggerService
{
    public function requestDeploy(): void
    {
        if (! Storage::disk('local')->put('deploy.trigger', now()->toIso8601String())) {
            throw new RuntimeException('Cannot write deployment trigger.');
        }
    }

    public function isPending(): bool
    {
        return Storage::disk('local')->exists('deploy.trigger');
    }

    public function lastLog(): ?string
    {
        $disk = Storage::disk('local');

        return $disk->exists('deploy_log.txt') ? $disk->get('deploy_log.txt') : null;
    }

    public function lastDeployAt(): ?CarbonImmutable
    {
        $disk = Storage::disk('local');

        return $disk->exists('deploy_log.txt')
            ? CarbonImmutable::createFromTimestamp($disk->lastModified('deploy_log.txt'), config('app.timezone'))
            : null;
    }
}
