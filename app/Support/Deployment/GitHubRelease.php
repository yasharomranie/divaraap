<?php

declare(strict_types=1);

namespace App\Support\Deployment;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GitHubRelease
{
    public function download(string $repository, string $ref, string $token, string $target): array
    {
        if (! preg_match('~\A[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+\z~', $repository) || ! preg_match('/\A[A-Za-z0-9_.-]{1,100}\z/', $ref)) {
            throw new RuntimeException('Repository or branch is invalid.');
        }
        $request = Http::connectTimeout(15)->timeout(30)->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28', 'User-Agent' => 'Divaraap-Deploy']);
        if ($token !== '') {
            $request->withToken($token);
        }
        $response = $request->withOptions(['allow_redirects' => false])->get('https://api.github.com/repos/'.$repository.'/releases/tags/deploy-'.$ref);
        if (! $response->successful()) {
            throw new RuntimeException('GitHub release unavailable (HTTP '.$response->status().').');
        }
        $asset = collect($response->json('assets', []))->firstWhere('name', 'divaraap-update.zip');
        if (! $asset || ! preg_match('/\Asha256:([a-f0-9]{64})\z/', $asset['digest'] ?? '', $digest) || ($asset['size'] ?? 0) > 268435456 || ! is_int($asset['id'] ?? null)) {
            throw new RuntimeException('Verified production update asset is missing.');
        }
        $url = 'https://api.github.com/repos/'.$repository.'/releases/assets/'.$asset['id'];
        for ($redirect = 0; $redirect < 4; $redirect++) {
            $host = parse_url($url, PHP_URL_HOST);
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || ! in_array($host, ['api.github.com', 'release-assets.githubusercontent.com'], true) || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_PORT)) {
                throw new RuntimeException('Untrusted release download destination.');
            }
            $download = Http::connectTimeout(15)->timeout(180)->withHeaders(['Accept' => 'application/octet-stream', 'User-Agent' => 'Divaraap-Deploy']);
            if ($host === 'api.github.com' && $token !== '') {
                $download->withToken($token);
            }
            $result = $download->withOptions(['allow_redirects' => false, 'sink' => $target, 'progress' => static function ($total, $downloaded): void {
                if ($total > 268435456 || $downloaded > 268435456) {
                    throw new RuntimeException('Update download is too large.');
                }
            }])->get($url);
            if ($result->redirect()) {
                $url = $result->header('Location');

                continue;
            }
            if (! $result->successful() || ! is_file($target) || ! hash_equals($digest[1], hash_file('sha256', $target))) {
                throw new RuntimeException('Update download or checksum verification failed.');
            }

            return ['sha256' => $digest[1], 'release' => $response->json('tag_name')];
        }
        throw new RuntimeException('Too many download redirects.');
    }
}
