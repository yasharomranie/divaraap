<?php

declare(strict_types=1);

$source = dirname(__DIR__, 2);
$directory = sys_get_temp_dir().'/native-status-'.bin2hex(random_bytes(8));
$root = $directory.'/divaraap';
$public = $directory.'/public_html';
mkdir($root.'/app/Support/Deployment', 0700, true);
mkdir($public, 0700, true);
copy($source.'/app/Support/Deployment/ReleaseLayout.php', $root.'/app/Support/Deployment/ReleaseLayout.php');
copy($source.'/distribution/deployment-runner.php', $public.'/deployment-runner.php');
file_put_contents($root.'/installed.lock', 'installed');
file_put_contents($root.'/deployment-maintenance.json', '{}');
$token = str_repeat('b', 64);
$job = ['id' => str_repeat('a', 32), 'state' => 'running', 'expires' => time() + 600, 'step' => 'database_backup', 'status_token_hash' => hash('sha256', $token), 'error' => 'private-database-password'];
file_put_contents($root.'/deployment-job.json', json_encode($job));
$socket = stream_socket_server('tcp://127.0.0.1:0');
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([PHP_BINARY, '-d', 'disable_functions=exec,shell_exec,proc_open,system,passthru,popen', '-S', $address, '-t', $public], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/server.log', 'a'], 2 => ['file', $directory.'/server.log', 'a']], $pipes);
$lock = fopen($root.'/deployment.mutex', 'c');
flock($lock, LOCK_EX);
$assertions = 0;
$check = static function (bool $condition) use (&$assertions): void {
    if (! $condition) {
        throw new RuntimeException('Native deployment status assertion failed.');
    }
    $assertions++;
};
$request = static function (array $input) use ($address): array {
    $curl = curl_init('http://'.$address.'/deployment-runner.php');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($input), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5, CURLOPT_HTTPHEADER => ['Content-Type: application/json']]);
    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    return [$status, $body];
};
try {
    for ($i = 0; $i < 50; $i++) {
        $connection = @stream_socket_client('tcp://'.$address, $errno, $error, 0.1);
        if ($connection) {
            fclose($connection);
            break;
        }
        usleep(20000);
    }
    $input = ['action' => 'status', 'id' => $job['id'], 'token' => $token];
    [$status, $body] = $request($input);
    $check($status === 200);
    $check(json_decode($body, true)['state'] === 'running');
    $check(str_contains($body, 'پشتیبان'));
    $check(! str_contains($body, 'private-database-password'));
    $check(json_decode(file_get_contents($root.'/deployment-job.json'), true) === $job);
    [$status] = $request(array_replace($input, ['token' => 'wrong']));
    $check($status === 403);
    [$status] = $request(['id' => $job['id'], 'token' => $token]);
    $check($status === 500);
    $check(json_decode(file_get_contents($root.'/deployment-job.json'), true) === $job);
    flock($lock, LOCK_UN);
    [$status] = $request(['id' => $job['id'], 'token' => $token]);
    $check($status === 403);
    $job['state'] = 'completed';
    file_put_contents($root.'/deployment-job.json', json_encode($job));
    [$status, $body] = $request($input);
    $check($status === 200 && json_decode($body, true)['state'] === 'completed');
    $job['expires'] = time() - 4000;
    file_put_contents($root.'/deployment-job.json', json_encode($job));
    [$status] = $request($input);
    $check($status === 403);
    echo 'Native deployment status: '.$assertions.' assertions passed with maintenance, held install lock, disabled shell functions, rejected replay, and no Laravel bootstrap.'.PHP_EOL;
} finally {
    fclose($lock);
    proc_terminate($process);
    proc_close($process);
    $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($entries as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($directory);
}
