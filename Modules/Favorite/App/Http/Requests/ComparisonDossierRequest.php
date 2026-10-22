<?php

declare(strict_types=1);

namespace Modules\Favorite\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ComparisonDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active';
    }

    public function rules(): array
    {
        return $this->routeIs('dossiers.change')
            ? ['action' => ['required', Rule::in(['revoke', 'rotate', 'remove'])], 'revision' => ['required', 'integer', 'min:1']]
            : ['label' => ['required', 'string', 'max:100', 'regex:/\S/u'], 'request_key' => ['required', 'uuid'], 'consent' => ['accepted']];
    }

    public function attributes(): array
    {
        return ['label' => __('favorite::dossier.label'), 'consent' => __('favorite::dossier.consent')];
    }
}
