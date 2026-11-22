<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Laravel\Fortify\InteractsWithTwoFactorState;

class TwoFactorAuthenticationRequest extends FormRequest
{
    use InteractsWithTwoFactorState {
        neverFinishedConfirmingTwoFactorAuthentication as private confirmationWasAbandoned;
    }

    protected function neverFinishedConfirmingTwoFactorAuthentication(int $currentTime): bool
    {
        // The notification bell polls the current page while the setup modal
        // remains open. A partial refresh is not leaving/abandoning setup.
        if ($this->header('X-Inertia') && $this->header('X-Inertia-Partial-Data')) {
            return false;
        }

        // A rejected code is a retry, not an abandoned setup. Empty/sensitive
        // codes may not be present in flashed old input.
        if (array_key_exists('code', $this->session()->getOldInput()) ||
            $this->session()->get('errors')?->getBag('confirmTwoFactorAuthentication')->has('code')) {
            return false;
        }

        return $this->confirmationWasAbandoned($currentTime);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }
}
