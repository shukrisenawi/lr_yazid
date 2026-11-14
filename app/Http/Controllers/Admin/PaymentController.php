<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\GatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentSettingsRequest;
use App\Models\Payment;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'in:creating,creation_unknown,pending,paid,failed,expired']]);

        return Inertia::render('admin/Payments', [
            'payments' => Payment::with(['order:id,reference,customer_name,customer_email'])->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(20)->withQueryString(),
            'status' => $data['status'] ?? '',
        ]);
    }

    public function settings(): Response
    {
        $settings = PaymentGatewaySetting::current();

        return Inertia::render('admin/PaymentSettings', [
            'settings' => $settings->only(['enabled', 'environment', 'sandbox_category_code', 'production_category_code']),
            'sandboxConfigured' => $settings->configured('sandbox'), 'productionConfigured' => $settings->configured('production'),
            'callbackUrl' => rtrim(config('app.url'), '/').route('payments.callback', absolute: false),
            'returnUrl' => rtrim(config('app.url'), '/').route('payments.return', absolute: false),
        ]);
    }

    public function update(PaymentSettingsRequest $request): RedirectResponse
    {
        $settings = PaymentGatewaySetting::current();
        $data = $request->validated();
        $settings->fill(Arr::except($data, ['sandbox_secret_key', 'production_secret_key']));
        foreach (['sandbox_secret_key', 'production_secret_key'] as $field) {
            if (filled($data[$field] ?? null)) {
                $settings->setAttribute($field, $data[$field]);
            }
        }
        $settings->save();
        PaymentCallback::create(['event' => 'settings_updated', 'outcome' => 'saved', 'payload' => ['admin_id' => $request->user()->id, 'environment' => $settings->environment, 'enabled' => $settings->enabled]]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Payment settings saved. Secret keys are never displayed.']);

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
