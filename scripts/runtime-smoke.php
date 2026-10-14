<?php

declare(strict_types=1);

use Illuminate\Contracts\Broadcasting\Factory;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $result = match ($argv[1] ?? '') {
        'broadcast' => $app->make(Factory::class)->connection('reverb')->getPusher()
            ->trigger('phase1-smoke', 'phase1.ready', ['ok' => true]),
        'queue' => ['queue_id' => Mail::to('baseline@example.test')->queue(
            (new Mailable)->mailer('log')->subject('Phase 1 queue smoke')->html('Phase 1 queue smoke')
        )],
        default => throw new InvalidArgumentException,
    };

    echo json_encode($result, JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Runtime smoke failed: '.get_class($exception).PHP_EOL);
    exit(1);
}
