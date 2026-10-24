<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Listing\Support\PriceGuide;

final class PriceGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return PriceGuide::rules($this->query());
    }

    public function attributes(): array
    {
        return collect(['category_id', 'city_id', 'search', 'currency', 'condition'])->mapWithKeys(fn (string $key): array => [$key => __('listing::prices.'.$key)])->all();
    }
}
