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
        return [...WantedRequest::rules($this->routeIs('service-requests.*') ? 'service' : 'product'), ...($this->routeIs('wanted.update', 'service-requests.update') ? ['revision' => ['required', 'integer', 'min:1']] : ['request_key' => ['required', 'uuid']])];
    }

    public function attributes(): array
    {
        return collect(['title', 'description', 'category_id', 'city_id', 'budget', 'preferred_time'])->mapWithKeys(fn ($key) => [$key => __(($this->routeIs('service-requests.*') ? 'listing::services.' : 'listing::wanted.').($key === 'title' ? 'title_label' : $key))])->all();
    }
}
