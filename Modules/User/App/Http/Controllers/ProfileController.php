<?php

declare(strict_types=1);

namespace Modules\User\App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Rules\IranianMobile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\User\App\Models\Profile;

class ProfileController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('panel.profile.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updateProfile', [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:60', new IranianMobile],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($request->user()->id),
            ],
        ]);

        $phone = $validated['phone'] ?? null;
        unset($validated['phone']);
        if ($request->exists('phone')) {
            Profile::savePhoneForUser($request->user(), $phone);
        }
        $request->user()->fill($validated);

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return redirect()->route('panel.profile.edit')->with('status', 'profile-updated');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
