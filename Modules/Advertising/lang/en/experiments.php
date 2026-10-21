<?php

declare(strict_types=1);

return [
    'blocked' => 'Suspended: designs or eligibility changed',
    'title' => 'Advertising A/B experiments',
    'experiment' => 'Advertising experiment',
    'arm_a' => 'Design A',
    'arm_b' => 'Design B',
    'setup_hint' => 'Two approved image banners assigned to this placement, for both devices, sharing a destination and campaign (or neither). Individual frequency caps are not supported. Configuration is immutable; create another draft to correct it.',
    'invalid' => 'Invalid experiment or transition. Check both approved image banners, assignment, same destination/campaign, both devices, no individual caps, and no other running experiment at this placement.',
    'draft' => 'Draft',
    'running' => 'Running',
    'stopped' => 'Stopped',
    'ended' => 'Ended',
    'start' => 'Start experiment',
    'stop' => 'Stop experiment',
    'report' => 'Compare designs',
    'close' => 'Close',
    'started' => 'Started (Tehran)',
    'ends' => 'Ends (Tehran)',
    'start_hint' => 'This placement is dedicated to the two designs during the experiment, replacing its normal rotation. Sessions are assigned equally in expectation, without an exact quota. Changes to either design or campaign suspend serving. Stopping is final; ordinary rotation resumes.',
    'split' => '50/50 session allocation',
    'limits' => 'Only signed, accepted events from this experiment are included. Impressions and clicks are events, not unique people. Data is retained for 90 days; blocked scripts or lost requests can undercount.',
    'expired_report' => 'Some events may have expired from the 90-day retention window. This report may be incomplete.',
    'impressions' => 'Measured impressions',
    'clicks' => 'Measured clicks',
    'conversations' => 'New conversations after click',
    'ctr' => 'Click-through rate',
    'no_winner' => 'Descriptive comparison only: no statistical significance or automatic winner is claimed. Compare after adequate exposure and the full planned period. Conversations use the existing 24-hour attribution, not purchases.',
];
