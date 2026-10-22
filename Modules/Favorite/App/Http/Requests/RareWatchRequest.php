<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Favorite\App\Models\RareItemWatch;

final class RareWatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return $this->routeIs('rare-watches.store') ? RareItemWatch::rules() : ['action' => ['required', Rule::in(['pause', 'resume', 'renew', 'remove'])]];
    }

    public function attributes(): array
    {
        return collect(['search', 'category', 'city', 'max_price'])->mapWithKeys(fn ($field): array => [$field => __('favorite::rare.'.$field)])->all();
    }
}
