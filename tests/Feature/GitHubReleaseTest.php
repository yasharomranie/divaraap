<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\GitHubRelease;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class GitHubReleaseTest extends TestCase
{
    public function test_verified_asset_downloads_without_forwarding_token_to_asset_host(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'github-release');
        $bytes = 'verified-production-zip';
        Http::fake(function (Request $request, array $options) use ($bytes) {
            if (str_contains($request->url(), '/releases/tags/')) {
                return Http::response(['tag_name' => 'deploy-main', 'assets' => [['name' => 'divaraap-update.zip', 'id' => 42, 'digest' => 'sha256:'.hash('sha256', $bytes), 'size' => strlen($bytes)]]]);
            }
            if (str_contains($request->url(), 'api.github.com')) {
                return Http::response('', 302, ['Location' => 'https://release-assets.githubusercontent.com/production.zip']);
            }
            file_put_contents($options['sink'], $bytes);

            return Http::response($bytes);
        });
        try {
            $result = (new GitHubRelease)->download('owner/project', 'main', 'private-token', $path);
            $this->assertSame(hash('sha256', $bytes), $result['sha256']);
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'api.github.com') && $request->hasHeader('Authorization', 'Bearer private-token'));
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'release-assets.githubusercontent.com') && ! $request->hasHeader('Authorization'));
        } finally {
            unlink($path);
        }
    }

    public function test_redirect_to_untrusted_destination_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'github-release');
        Http::fake([
            '*releases/tags/*' => Http::response(['assets' => [['name' => 'divaraap-update.zip', 'id' => 42, 'digest' => 'sha256:'.str_repeat('a', 64), 'size' => 10]]]),
            '*releases/assets/*' => Http::response('', 302, ['Location' => 'https://untrusted.test/steal']),
        ]);
        try {
            $this->expectException(RuntimeException::class);
            (new GitHubRelease)->download('owner/project', 'main', 'private-token', $path);
        } finally {
            unlink($path);
            Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'untrusted.test'));
        }
    }

    public function test_incorrect_digest_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'github-release');
        Http::fake(function (Request $request, array $options) {
            if (str_contains($request->url(), '/tags/')) {
                return Http::response(['assets' => [['name' => 'divaraap-update.zip', 'id' => 42, 'digest' => 'sha256:'.str_repeat('a', 64), 'size' => 10]]]);
            }
            file_put_contents($options['sink'], 'tampered');

            return Http::response('tampered');
        });
        try {
            $this->expectException(RuntimeException::class);
            (new GitHubRelease)->download('owner/project', 'main', '', $path);
        } finally {
            unlink($path);
        }
    }
}
