<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advertising\Models\Campaign;

final class AdvertisingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active' && (bool) $this->user()?->can('advertising.manage');
    }

    public static function filterRules(): array
    {
        return [
            'campaignId' => ['nullable', 'integer', 'min:1', Rule::exists(Campaign::class, 'id')],
            'dimension' => ['required', Rule::in(['banner_id', 'placement', 'device', 'event_date'])],
            'fromDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDays(89)->toDateString(), 'before_or_equal:toDate'],
            'toDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:fromDate', 'before_or_equal:'.now()->toDateString()],
        ];
    }

    public function rules(): array
    {
        return self::filterRules();
    }
}
