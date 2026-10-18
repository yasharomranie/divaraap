<?php

declare(strict_types=1);

namespace Modules\Advertising\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Advertising\Models\BannerPlacement;

final class BannerPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (string) $this->user()?->status === 'active' && (bool) $this->user()?->can('advertising.manage');
    }

    public function rules(): array
    {
        return ['device' => ['sometimes', Rule::in(['mobile', 'desktop'])], 'placement' => ['sometimes', Rule::in(BannerPlacement::KEYS)]];
    }
}
