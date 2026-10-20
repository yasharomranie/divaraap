<?php

declare(strict_types=1);

return [
    'title' => 'Trade checklist',
    'admin_hint' => 'Subcategories inherit their parent settings. Default mode uses a category guide or the general guide.',
    'mode' => 'Checklist mode',
    'inherit' => 'Inherit or use default guide',
    'custom' => 'Custom items',
    'disabled' => 'Disable for this branch',
    'items_label' => 'Items to check (up to 20)',
    'persian' => 'Persian text',
    'english' => 'English text',
    'hint' => 'This is your personal review list. Checking items does not verify the listing, seller or transaction.',
    'open' => 'Review before making a deal',
    'back' => 'Back to listing',
    'save' => 'Save my checklist',
    'saved' => 'Your checklist progress was saved.',
    'login' => 'Sign in to save your checklist',
    'progress' => ':done of :total items saved',
    'changed' => 'The checklist has changed. Refresh and review the items again.',
    'stale' => 'Your checklist was updated in another tab. Refresh this page.',
    'invalid' => 'Select only items from this checklist.',
    'refresh' => 'Refresh checklist',
    'private' => 'Your progress is visible only to you.',
    'link_hint' => 'Review the guide for this listing category and save your own progress.',
    'templates' => [
        'general' => [
            'Inspect the item or proposed service before paying.',
            'Check the other party’s identity and authority for this deal.',
            'Clarify the final price, payment method and handover terms.',
        ],
        'phones' => [
            'Match the device serial and identifier with its documentation.',
            'Check device locks and the previous owner’s account status.',
            'Test calls, cameras, screen, battery and charging port.',
        ],
        'electronics' => [
            'Match the model and serial with the listing.',
            'Test operation, battery and connections during inspection.',
            'Clarify repairs, included accessories and warranty terms.',
        ],
        'vehicles' => [
            'Match ownership documents with vehicle identifiers.',
            'Have an independent expert inspect bodywork, mechanics and repair history.',
            'Clarify outstanding obligations and transfer steps before paying.',
        ],
        'property' => [
            'Visit the property and compare it with the listing.',
            'Check ownership documents or authority to lease.',
            'Review costs, obligations and contract terms before signing.',
        ],
        'fashion' => [
            'Check actual size, material and condition.',
            'Clarify exchange terms and any authenticity claims.',
        ],
        'home' => [
            'Measure dimensions and the delivery route before buying.',
            'Inspect surfaces, parts and operation.',
            'Clarify delivery costs and installation responsibilities.',
        ],
        'sports' => [
            'Check sizing, fit and the condition of essential parts.',
            'Inspect wear and any required safety equipment.',
        ],
        'jobs' => [
            'Check employer identity and the exact job description.',
            'Clarify pay, working hours and terms in writing.',
            'Do not send money or sensitive banking details for a job promise.',
        ],
        'services' => [
            'Check relevant experience and examples of completed work.',
            'Clarify scope, timing and total cost in writing.',
            'Agree on corrections and staged payment terms.',
        ],
    ],
];
