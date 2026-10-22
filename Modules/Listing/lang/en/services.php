<?php

declare(strict_types=1);

return array_replace(require __DIR__.'/wanted.php', [
    'title' => 'Local service requests',
    'mine' => 'My service requests',
    'create' => 'Request a local service',
    'edit' => 'Edit service request',
    'intro' => 'Describe your service need and city. Providers can suggest their matching listings.',
    'title_field' => 'What service do you need?',
    'category_id' => 'Service category',
    'city_id' => 'Service city',
    'choose_city' => 'Choose a city',
    'hint' => 'Requests appear for up to 30 days after review. Keep phone numbers and exact addresses private; arrange details in site messages.',
    'preferred_time' => 'Preferred time (optional)',
    'time_hint' => 'For example, weekend afternoons. A preference only, not a booking or time commitment.',
    'browse' => 'Browse service requests',
    'suggest' => 'Suggest my service',
    'choose_listing' => 'Your service listing',
    'suggest_hint' => 'Choose from your 50 newest active listings in this exact service category and city. One suggestion per provider per revision, visible only to the request owner.',
    'no_choices' => 'No eligible service listing, or communication with this user is unavailable.',
    'fulfilled' => 'Completed (your report)',
    'fulfill' => 'Service completed',
    'contact' => 'Message the provider',
    'closed_hint' => 'Closing is your report only; it does not verify service completion, provider qualifications or payment.',
    'budget_hint' => 'A target budget only. Agree the final cost and timing with the provider.',
    'back' => 'Back to service requests',
    'kind' => 'Request type',
    'product' => 'Product wanted',
    'service' => 'Local service',
    'moderation' => 'Review buyer and service requests',
]);
