<?php

declare(strict_types=1);

return [
    'daily_cap' => 'Daily viewable impressions per browser',
    'daily_cap_hint' => 'Empty means unlimited. Counts all placements; campaign limits are shared across its banners. Resets on application-calendar days. Counting starts when enabled. Requires telemetry; capped ads are hidden when telemetry is off. Applies to subsequent requests; already open tabs can still display banners. Clearing cookies or using another browser starts a separate counter.',
    'conversations' => 'New conversations',
    'conversion_definition' => 'Conversation attribution: last eligible click in this browser within 24 hours, only for the same internal listing targeted by the banner. At most one new conversation per click; existing conversations are excluded. Cookie loss, blocked click tracking and network failures can undercount. Store visits and service requests are not yet measured.',
    'notes' => 'About these metrics',
    'title' => 'Advertising statistics',
    'all' => 'All campaigns and independent banners',
    'dimension' => 'Group by',
    'event_date' => 'Day',
    'banner_id' => 'Banner',
    'placement' => 'Placement',
    'device' => 'Device',
    'impressions' => 'Viewable impressions',
    'clicks' => 'Clicks',
    'ctr' => 'CTR',
    'empty' => 'No events recorded in this period.',
    'period' => 'Last 30 days; up to 100 rows. Dates use the application timezone.',
    'definition' => 'An impression requires a loaded image with at least 50% of the banner visible for one second in an active tab. Each served banner counts at most one impression and click. CTR = clicks / impressions × 100.',
    'limitations' => 'These are not unique people. Reloading creates a new exposure. A quick click may precede an impression, so CTR can exceed 100%. Blockers and network failures undercount. No personal details or IP addresses are stored.',
    'tracking_toggle' => 'Disabling telemetry in operations settings also disables advertising tracking.',
];
