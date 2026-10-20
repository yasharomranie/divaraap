<?php

declare(strict_types=1);

return [
    'content' => 'Listing text for review (up to 8,000 characters)',
    'title' => 'Listing risk review',
    'hint' => 'These automatic indicators support human review. A signal is not proof of wrongdoing. This page never automatically rejects, blocks or publishes a listing.',
    'scope' => ':count listings scanned: up to 200 active or pending listings from the last 30 days. Older listings and records outside this window are not covered.',
    'refresh' => 'Refresh analysis',
    'moderation' => 'Open moderation queue',
    'inspect' => 'Inspect and edit listing',
    'status' => 'Status',
    'matches' => 'Matching listings (up to 5)',
    'repeated_title' => 'Repeated title in the same category and account',
    'repeated_title_hint' => 'The normalized title repeats. It may represent different inventory or another item.',
    'copied_content' => 'Identical title and text across different accounts',
    'copied_content_hint' => 'The same category contains an identical title and description of at least 80 characters. Review the content ownership.',
    'sensitive_request' => 'Possible request for a sensitive code or password',
    'sensitive_request_hint' => 'A password or login-code phrase appears near a request verb. Review its context.',
    'advance_request' => 'Possible advance payment before viewing',
    'advance_request_hint' => 'A deposit or advance-payment phrase appears near viewing or reservation wording. Review the terms.',
    'empty' => 'No indicators found in the scan window. This does not mean every listing is safe.',
    'active_hint' => 'This listing is already published. Use the existing permissions and moderation process for further action.',
];
