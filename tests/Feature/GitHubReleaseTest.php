<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\Deployment\GitHubRelease;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
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

    public function test_public_download_survives_api_connection_failure_without_leaking_token(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'public-release');
        $bytes = 'public-production-package';
        Http::fake(function (Request $request, array $options) use ($bytes) {
            if (str_contains($request->url(), 'api.github.com')) {
                return Http::failedConnection();
            }
            $contents = str_contains($request->url(), 'UPDATE-SHA256SUMS') ? hash('sha256', $bytes).'  divaraap-update.zip' : $bytes;
            file_put_contents($options['sink'], $contents);

            return Http::response($contents);
        });
        try {
            $result = (new GitHubRelease)->download('owner/project', 'main', 'private-secret', $path);
            $this->assertSame('public_https', $result['transport']);
            $this->assertSame($bytes, file_get_contents($path));
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'github.com/owner/project/releases/download/') && ! $request->hasHeader('Authorization'));
            $this->assertFileDoesNotExist($path.'.sha256');
        } finally {
            unlink($path);
        }
    }

    public function test_public_fallback_rejects_a_tampered_zip(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'public-release');
        Http::fake(function (Request $request, array $options) {
            if (str_contains($request->url(), 'api.github.com')) {
                return Http::response('', 403);
            }
            $contents = str_contains($request->url(), 'UPDATE-SHA256SUMS') ? str_repeat('a', 64).'  divaraap-update.zip' : 'tampered';
            file_put_contents($options['sink'], $contents);

            return Http::response($contents);
        });
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('checksum verification failed');
            (new GitHubRelease)->download('owner/project', 'main', '', $path);
        } finally {
            unlink($path);
        }
    }

    public function test_transport_http_exception_still_uses_verified_public_release(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'github-release');
        $bytes = 'verified-public-production-zip';
        Http::fake(function (Request $request, array $options) use ($bytes) {
            if (str_contains($request->url(), 'api.github.com')) {
                throw new RequestException(new \Illuminate\Http\Client\Response(new Response(403)));
            }
            $body = str_contains($request->url(), 'UPDATE-SHA256SUMS.txt') ? hash('sha256', $bytes).'  divaraap-update.zip' : $bytes;
            file_put_contents($options['sink'], $body);

            return Http::response($body);
        });
        try {
            $result = (new GitHubRelease)->download('owner/project', 'main', '', $path);
            $this->assertSame('public_https', $result['transport']);
            $this->assertSame($bytes, file_get_contents($path));
            Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'request=') && $request->hasHeader('Cache-Control', 'no-cache'));
        } finally {
            unlink($path);
        }
    }
}
