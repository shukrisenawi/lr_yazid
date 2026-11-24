<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookAppointmentRequest;
use App\Models\Appointment;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function calendar(Request $request, AppointmentAvailabilityService $availability): Response
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'], 'service' => ['nullable', 'integer'], 'reschedule' => ['nullable', 'integer'],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d'],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $appointment = isset($filters['reschedule']) ? Appointment::where('user_id', $request->user()->id)->whereKey($filters['reschedule'])->firstOrFail() : null;
        abort_if($appointment && ($appointment->status !== 'approved' || $appointment->scheduled_starts_at?->lte(now())), 422, __('appointment.state'));
        $services = AppointmentService::where('is_active', true)->orderBy('name')->get(['id', 'name', 'description', 'duration_minutes', 'location']);
        $service = $appointment ? AppointmentService::findOrFail($appointment->appointment_service_id)
            : (isset($filters['service']) ? AppointmentService::where('is_active', true)->whereKey($filters['service'])->firstOrFail() : AppointmentService::where('is_active', true)->orderBy('name')->first());
        $month = isset($filters['month']) ? CarbonImmutable::createFromFormat('!Y-m', $filters['month'], 'Asia/Kuala_Lumpur') : CarbonImmutable::now()->startOfMonth();
        $from = isset($filters['from']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['from'], 'Asia/Kuala_Lumpur') : null;
        $to = isset($filters['to']) ? CarbonImmutable::createFromFormat('!Y-m-d', $filters['to'], 'Asia/Kuala_Lumpur') : null;
        if ($from && $to && $to->gt($from->addDays(60))) {
            throw ValidationException::withMessages(['to' => 'Choose a date range of no more than 60 days.']);
        }

        return Inertia::render('vip/appointments/Book', ['services' => $services, 'serviceId' => $service?->id, 'month' => $month->format('Y-m'),
            'from' => $from?->toDateString(), 'to' => $to?->toDateString(),
            'slots' => $service ? ($from && $to ? $availability->range($request->user(), $service, $from, $to, $appointment) : $availability->month($request->user(), $service, $month, $appointment)) : [],
            'reschedule' => $appointment?->only(['id', 'reference', 'scheduled_starts_at', 'scheduled_ends_at']), 'bookingKey' => (string) Str::uuid()]);
    }

    public function store(BookAppointmentRequest $request, AppointmentBookingService $service): RedirectResponse
    {
        $data = $request->validated();
        $appointment = $service->book($request->user(), AppointmentSlot::whereKey($data['slot_id'])->firstOrFail(), $data);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.submitted')]);

        return to_route('appointments.show', $appointment);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['status' => ['nullable', 'in:pending,approved,rejected,cancelled,completed,no_show']]);

        return Inertia::render('customer/appointments/Index', [
            'appointments' => Appointment::where('user_id', $request->user()->id)
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest('scheduled_starts_at')->paginate(15)->withQueryString(), 'filters' => $filters,
        ]);
    }

    public function show(Request $request, Appointment $appointment): Response
    {
        Gate::authorize('view', $appointment);
        // The customer endpoint remains owner-only even for administrators.
        abort_unless($appointment->user_id === $request->user()->id, 403);

        return Inertia::render('customer/appointments/Show', [
            'appointment' => $appointment->load(['histories', 'rescheduleRequests.targetSlot:id,appointment_service_id,starts_at,ends_at,location']),
            'canReschedule' => $request->user()->hasActiveMembership() && $appointment->status === 'approved' && $appointment->scheduled_starts_at?->gt(now()),
            'cancellationNoticeHours' => $appointment->service->cancellation_notice_hours,
        ]);
    }

    public function cancel(Request $request, Appointment $appointment, AppointmentBookingService $service): RedirectResponse
    {
        Gate::authorize('cancel', $appointment);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->cancel($appointment, $request->user(), $data['reason']);

        return $this->updated();
    }

    public function reschedule(Request $request, Appointment $appointment, AppointmentBookingService $service): RedirectResponse
    {
        abort_unless($appointment->user_id === $request->user()->id, 403);
        $data = $request->validate(['slot_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->requestReschedule($appointment, AppointmentSlot::whereKey($data['slot_id'])->firstOrFail(), $request->user(), $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.reschedule_submitted')]);

        return to_route('appointments.show', $appointment);
    }

    public function respond(Request $request, AppointmentRescheduleRequest $rescheduleRequest, AppointmentBookingService $service): RedirectResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean']]);
        $service->respondToProposal($rescheduleRequest, $request->user(), $data['accept']);

        return $this->updated();
    }

    private function updated(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.updated')]);

        return back();
    }
}
