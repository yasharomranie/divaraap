<?php

declare(strict_types=1);

return [
    'navigation' => 'Cron deployment',
    'title' => 'Production deployment',
    'description' => 'This button only records a deployment request. The cPanel cron processes it on its next scheduled run.',
    'run' => 'Run deployment',
    'requesting' => 'Requesting deployment…',
    'pending' => 'Pending execution',
    'last_log' => 'Latest deployment output',
    'last_deploy' => 'Log last modified',
    'no_log' => 'No deployment log has been recorded yet.',
    'requested' => 'Deployment requested',
    'requested_body' => 'The cron will process the request on its next run.',
    'failed' => 'The deployment request could not be saved. Check private storage write access.',
];
