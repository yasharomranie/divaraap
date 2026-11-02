<?php

declare(strict_types=1);

namespace Modules\Listing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListingQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && (string) $this->user()->status === 'active';
    }

    public function rules(): array
    {
        if ($this->routeIs('listing-questions.store')) {
            return ['question' => ['required', 'string', 'min:10', 'max:600', 'regex:/\S/u'], 'public_consent' => ['accepted']];
        }
        $rules = ['revision' => ['required', 'integer', 'min:1']];
        if ($this->routeIs('listing-questions.answer')) {
            $rules += ['answer' => ['required', 'string', 'min:2', 'max:1200', 'regex:/\S/u'], 'public_consent' => ['accepted']];
        }

        if ($this->routeIs('listing-questions.withdraw')) {
            $rules['confirm_withdraw'] = ['accepted'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['question' => __('listing::questions.question'), 'answer' => __('listing::questions.answer'), 'public_consent' => __('listing::questions.consent')];
    }
}
