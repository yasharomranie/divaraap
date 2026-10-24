<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Requests;

use App\Support\PersianSearch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Location\Models\City;

final class NeighborhoodShopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'city_id' => ['nullable', 'required_with:search', 'integer', Rule::in(array_keys(City::wantedOptions()))],
            'search' => ['nullable', 'string', 'max:120', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! is_string($value) || PersianSearch::terms($value) === null) {
                    $fail(__('user::neighborhoods.search_error'));
                }
            }],
        ];
    }

    public function attributes(): array
    {
        return ['city_id' => __('user::neighborhoods.city_id'), 'search' => __('user::neighborhoods.search')];
    }
}
