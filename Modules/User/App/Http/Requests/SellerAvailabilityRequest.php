<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\User\App\Models\Profile;

final class SellerAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && (string) $this->user()->status === 'active';
    }

    public function rules(): array
    {
        return Profile::availabilityRules();
    }

    public function attributes(): array
    {
        return ['until' => __('user::availability.until'), 'reason' => __('user::availability.reason'), 'public_consent' => __('user::availability.consent')];
    }
}
