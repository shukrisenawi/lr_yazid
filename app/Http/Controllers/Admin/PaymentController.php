<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentSettingsRequest;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentSettingsService;
use App\Services\PaymentVerificationService;
use App\Support\PublicHttpsUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'in:creating,creation_unknown,pending,paid,failed,expired'], 'archive' => ['nullable', 'in:active,archived,all']]);

        return Inertia::render('admin/Payments', [
            'payments' => Payment::with(['order:id,reference,customer_name,customer_email'])
                ->when(($data['archive'] ?? 'active') !== 'all', fn ($query) => ($data['archive'] ?? 'active') === 'archived' ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at'))
                ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20)->withQueryString(),
            'status' => $data['status'] ?? '',
            'archive' => $data['archive'] ?? 'active',
        ]);
    }

    public function settings(): Response
    {
        $settings = PaymentGatewaySetting::current();

        return Inertia::render('admin/PaymentSettings', [
            'settings' => $settings->only(['enabled', 'environment', 'sandbox_category_code', 'production_category_code', 'version']),
            'sandboxConfigured' => $settings->configured('sandbox'), 'productionConfigured' => $settings->configured('production'),
            'sandboxKeyAvailable' => filled($settings->sandbox_secret_key), 'productionKeyAvailable' => filled($settings->production_secret_key),
            'storedInDatabase' => $settings->exists,
            'callbackReady' => PublicHttpsUrl::configured(config('app.url')),
            'callbackUrl' => rtrim(config('app.url'), '/').route('payments.callback', absolute: false),
            'returnUrl' => rtrim(config('app.url'), '/').route('payments.return', absolute: false),
        ]);
    }

    public function update(PaymentSettingsRequest $request, PaymentSettingsService $service): RedirectResponse
    {
        $service->update($request->user(), $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('payment_settings.saved')]);

        return back();
    }

    public function reconcile(Payment $payment, PaymentVerificationService $verification): RedirectResponse
    {
        try {
            $status = $verification->reconcile($payment);
        } catch (GatewayException $error) {
            throw ValidationException::withMessages(['payment' => $error->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Reconciliation result: '.str_replace('_', ' ', $status).'.']);

        return back();
    }
}
