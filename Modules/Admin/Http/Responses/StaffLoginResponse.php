<?php

declare(strict_types=1);

namespace Modules\Admin\Http\Responses;

use Filament\Auth\Http\Responses\LoginResponse;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class StaffLoginResponse extends LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (auth()->check() && ! auth()->user()->hasRole('admin')) {
            $request->session()->forget('url.intended');

            return redirect('/admin/operations');
        }

        return parent::toResponse($request);
    }
}
