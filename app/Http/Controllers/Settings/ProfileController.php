<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WebsiteSetting;
use App\Services\ProductImageService;
use App\Services\WebsiteContentService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'websiteVersion' => $request->user()->role === UserRole::Admin ? WebsiteSetting::current()->version : null,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $avatarPath = null;
        $oldAvatarPath = null;
        try {
            DB::transaction(function () use ($request, &$avatarPath, &$oldAvatarPath) {
                $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if ($user->profile_version !== (int) $request->validated('profile_version')) {
                    throw ValidationException::withMessages(['profile_version' => __('customer_contacts.conflict')]);
                }
                $user->fill($request->safe()->only(['name', 'email', 'phone']));
                if ($request->hasFile('avatar')) {
                    $oldAvatarPath = $user->avatar_path;
                    $avatarPath = app(ProductImageService::class)->store($request->file('avatar'), 'avatars');
                    $user->avatar_path = $avatarPath;
                }
                if ($user->isDirty('email')) {
                    $user->email_verified_at = null;
                }
                $user->save();
                if ($request->hasFile('logo')) {
                    app(WebsiteContentService::class)->settings($user, [
                        'version' => $request->validated('website_version'),
                        'logo' => $request->file('logo'),
                        'remove_logo' => false,
                        'remove_favicon' => false,
                    ]);
                }
            }, 1);
        } catch (UniqueConstraintViolationException) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }
            throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
        } catch (Throwable $error) {
            if ($avatarPath) {
                Storage::disk('public')->delete($avatarPath);
            }
            throw $error;
        }
        if ($oldAvatarPath) {
            Storage::disk('public')->delete($oldAvatarPath);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(ProfileDeleteRequest $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->role === UserRole::Admin || $user->orders()->exists() || $user->memberships()->exists()
            || Appointment::where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages(['password' => __('shop.account_orders')]);
        }

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
