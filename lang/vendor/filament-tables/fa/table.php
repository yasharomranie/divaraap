<?php

declare(strict_types=1);

return [
    'columns' => [
        'editable' => [
            'saving' => 'در حال ذخیره...',
            'saved' => 'تغییر ذخیره شد.',
            'failure' => 'ذخیره تأیید نشد. صفحه را تازه کنید و دوباره تلاش کنید.',
            'unavailable' => 'این مورد دیگر قابل ویرایش نیست.',
        ],
        'icon' => [
            'boolean' => [
                'true' => 'بله',
                'false' => 'خیر',
            ],
        ],
        'select' => [
            'loading_message' => 'در حال بارگذاری...',
            'no_options_message' => 'گزینه‌ای وجود ندارد.',
            'no_search_results_message' => 'گزینه‌ای با جست‌وجوی شما مطابقت ندارد.',
        ],
    ],
    'loading' => 'در حال بارگذاری...',
    'result_count' => '{0} بدون نتیجه|{1} :count نتیجه|[2,*] :count نتیجه',
    'column_manager' => [
        'position' => 'جایگاه :position از :count.',
        'position_in_group' => 'جایگاه :position از :count در :group.',
        'reordered' => ':column به جایگاه :position از :count منتقل شد.',
        'reordered_deferred' => ':column به جایگاه :position از :count منتقل شد. برای ذخیرهٔ تغییرات، ستون‌ها را اعمال کنید.',
        'reordered_in_group' => ':column به جایگاه :position از :count در :group منتقل شد.',
        'reordered_in_group_deferred' => ':column به جایگاه :position از :count در :group منتقل شد. برای ذخیرهٔ تغییرات، ستون‌ها را اعمال کنید.',
        'actions' => [
            'move_up' => [
                'label' => 'انتقال :column به بالا',
            ],
            'move_down' => [
                'label' => 'انتقال :column به پایین',
            ],
            'reorder' => [
                'label' => 'تغییر ترتیب ستون',
            ],
        ],
    ],
    'actions' => [
        'reorder_record' => [
            'label' => 'تغییر ترتیب مورد :key',
        ],
        'move_record_up' => [
            'label' => 'انتقال :label به بالا',
        ],
        'move_record_down' => [
            'label' => 'انتقال :label به پایین',
        ],
        'toggle_record_content' => [
            'label' => 'باز یا جمع کردن مورد :key',
        ],
    ],
    'reordering' => [
        'position' => ':label، جایگاه :position از :count.',
        'moved' => ':label به جایگاه :position از :count منتقل شد.',
    ],
];
