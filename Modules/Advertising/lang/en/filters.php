<?php

declare(strict_types=1);

return [
    'format' => 'Prepared filter banner', 'choices' => 'Prepared choices', 'label' => 'Choice label',
    'search' => 'Search phrase (optional)', 'category' => 'Category (optional)', 'city' => 'City (optional)',
    'sort' => 'Result order', 'min_price' => 'Minimum price (IRR)', 'max_price' => 'Maximum price (IRR)',
    'hint' => 'Define 2–4 distinct choices. Each requires a phrase, category, city or price bound. Prices use IRR. Country comes from the chosen city. Choices open the existing search with their filters; result counts or discounts are not guaranteed. An unavailable category or city hides the entire banner until corrected.',
    'invalid' => 'Define 2–4 distinct labeled choices with valid filters and minimum price no greater than maximum. Category, city and country must be active. Search allows up to 120 characters and eight terms.',
    'choose' => 'Choose an option to open results with the filters shown below.',
    'minimum' => 'From :price', 'maximum' => 'Up to :price',
];
