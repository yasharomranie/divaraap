<?php

declare(strict_types=1);

namespace Modules\Panel\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_id' => ['required', 'integer', 'exists:listings,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'video_file' => ['nullable', 'file', config('video.processing') === 'direct' ? 'mimetypes:video/mp4' : 'mimes:mp4,mov,webm,m4v', 'max:'.config('video.max_upload_size_kb', 102400)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'listing_id.required' => __('panel::messages.video_listing'),
            'listing_id.exists' => __('panel::messages.video_valid_listing'),
            'video_file.mimetypes' => __('video::admin.direct_mp4_only'),
            'video_file.mimes' => __('panel::messages.video_formats'),
        ];
    }
}
