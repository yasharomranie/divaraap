<?php

declare(strict_types=1);

return [
    'stopped_impression_limit' => 'Stopped: impression limit',
    'stopped_click_limit' => 'Stopped: click limit',
    'stopped_cost_limit' => 'Stopped: cost limit',
    'impression_limit' => 'Total impression limit',
    'click_limit' => 'Total click limit',
    'cost_limit' => 'Estimated cost limit (IRR)',
    'impression_cost' => 'Estimated cost per impression (IRR)',
    'click_cost' => 'Estimated cost per click (IRR)',
    'count_hint' => 'Empty means unlimited. Campaign total, not daily browser frequency. Delivery stops on subsequent requests once any limit is reached; raising the limit can resume it. Already served exposures can still be recorded. Requires telemetry.',
    'cost_hint' => 'Internal estimate only, without billing or payment. Positive prices are needed to accrue cost. Delivery stops when remaining funds cannot cover an impression or click. Already served exposures may accrue cost after the stop; this is not a hard financial guarantee. Planned budget remains separate.',
    'state' => 'Delivery state',
    'running' => 'Running',
    'paused' => 'Paused by manager',
    'scheduled' => 'Scheduled',
    'ended' => 'Ended',
    'telemetry_off' => 'Stopped: telemetry disabled',
    'impressions' => 'Total recorded impressions',
    'clicks' => 'Total recorded clicks',
    'recorded_cost' => 'Accrued estimated cost (IRR)',
];
