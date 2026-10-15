<?php

namespace Modules\Users\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Users\Http\Requests\PasswordRequest;
use Modules\Users\Http\Requests\ProfileRequest;

/**
 * Every admin user's own profile: details, avatar, language, password and two-factor
 * authentication (the 2FA buttons post to Fortify's routes). No users.* permission is needed.
 */
class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        return view('users::admin.profile', ['user' => $this->user($request)]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $emailChanged = $request->string('email')->toString() !== $user->email;

        $user->fill($request->safe()->only(['name', 'email', 'locale']));
        if ($emailChanged) {
            // A new address must be verified again (User implements MustVerifyEmail).
            $user->forceFill(['email_verified_at' => null]);
        }
        $user->save();

        if ($request->hasFile('avatar')) {
            $user->addMediaFromRequest('avatar')->toMediaCollection('avatar');
        } elseif ($request->boolean('avatar_remove')) {
            $user->clearMediaCollection('avatar');
        }

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice')->with('status', 'verification-link-sent');
        }

        // The language may have changed; it is used from the next request on.
        $request->session()->put('locale', $user->locale ?? config('cms.default_locale'));

        return back()->with('success', __('users::admin.profile_saved'));
    }

    public function password(PasswordRequest $request): RedirectResponse
    {
        $this->user($request)->forceFill(['password' => $request->string('password')->toString()])->save();

        return back()->with('success', __('users::admin.password_changed'));
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
