<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Listing\Models\WantedRequest;

final class WantedFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return [...WantedRequest::rules(), ...($this->routeIs('wanted.update') ? ['revision' => ['required', 'integer', 'min:1']] : ['request_key' => ['required', 'uuid']])];
    }

    public function attributes(): array
    {
        return collect(['title', 'description', 'category_id', 'city_id', 'budget'])->mapWithKeys(fn ($key) => [$key => __('listing::wanted.'.($key === 'title' ? 'title_label' : $key))])->all();
    }
}
