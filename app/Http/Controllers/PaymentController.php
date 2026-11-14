<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentCallbackRequest;
use App\Http\Requests\PaymentReturnRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\PaymentVerificationService;
use App\Services\PaymentWorkflowService;
use App\Services\ToyyibPayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PaymentController extends Controller
{
    public function start(Order $order, PaymentWorkflowService $workflow, ToyyibPayService $gateway): HttpResponse
    {
        Gate::authorize('pay', $order);

        return Inertia::location($gateway->billUrl($workflow->start($order)));
    }

    public function callback(PaymentCallbackRequest $request, PaymentVerificationService $verification): JsonResponse
    {
        $status = $verification->callback($request->validated());

        return response()->json(['status' => $status], $status === 'pending' ? 202 : 200);
    }

    public function result(PaymentReturnRequest $request): Response
    {
        $payment = Payment::where('reference', $request->validated('order_id'))
            ->where('bill_code', $request->validated('billcode'))->firstOrFail();
        Gate::authorize('view', $payment->order);

        // Browser query parameters are deliberately ignored as a source of payment status.
        return Inertia::render('customer/PaymentResult', ['payment' => $payment, 'order' => $payment->order]);
    }
}
