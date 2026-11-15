<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentBlockedDate;
use App\Models\AppointmentRescheduleRequest;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Services\AppointmentAvailabilityService;
use App\Services\AppointmentBookingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function calendar(Request $request): Response
    {
        $filters = $request->validate(['date' => ['nullable', 'date_format:Y-m-d'], 'view' => ['nullable', 'in:month,week,day,list'],
            'service' => ['nullable', 'integer'], 'status' => ['nullable', 'in:pending,approved,rejected,cancelled,completed,no_show']]);
        $date = CarbonImmutable::parse($filters['date'] ?? now()->toDateString(), 'Asia/Kuala_Lumpur')->startOfDay();
        $view = $filters['view'] ?? 'month';
        $from = match ($view) {
            'day' => $date, 'week' => $date->startOfWeek(), default => $date->startOfMonth()->startOfWeek()
        };
        $to = match ($view) {
            'day' => $from->addDay(), 'week' => $from->addWeek(), default => $from->addDays(42)
        };
        $query = Appointment::where('scheduled_starts_at', '>=', $from)->where('scheduled_starts_at', '<', $to)
            ->when($filters['service'] ?? null, fn ($q, $id) => $q->where('appointment_service_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return Inertia::render('admin/appointments/Calendar', [
            'filters' => [...$filters, 'date' => $date->toDateString(), 'view' => $view],
            'appointments' => $query->with('user:id,name')->orderBy('scheduled_starts_at')->limit(500)->get(),
            'truncated' => (clone $query)->count() > 500,
            'services' => AppointmentService::orderBy('name')->get(['id', 'name']),
            'blockedDates' => AppointmentBlockedDate::whereBetween('date', [$from->toDateString(), $to->subDay()->toDateString()])
                ->when($filters['service'] ?? null, fn ($q, $id) => $q->where('appointment_service_id', $id))->get(['id', 'date', 'reason', 'appointment_service_id']),
            'slots' => AppointmentSlot::where('starts_at', '>=', $from)->where('starts_at', '<', $to)
                ->when($filters['service'] ?? null, fn ($q, $id) => $q->where('appointment_service_id', $id))
                ->withCount(['appointments as approved_count' => fn ($q) => $q->where('status', 'approved')])
                ->get(['id', 'starts_at', 'capacity', 'status', 'appointment_service_id']),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->validate(['service' => ['nullable', 'integer'], 'status' => ['nullable', 'in:pending,approved,rejected,cancelled,completed,no_show'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'search' => ['nullable', 'string', 'max:100']]);
        $query = Appointment::with(['user' => fn ($query) => $query->select('id', 'name', 'email', 'phone')
            ->withMax(['memberships as vip_expires_at' => fn ($query) => $query->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())], 'expires_at')
            ->withCasts(['vip_expires_at' => 'immutable_datetime'])])->withCount(['rescheduleRequests as pending_reschedules_count' => fn ($query) => $query->where('status', 'pending')])
            ->when($filters['service'] ?? null, fn ($query, $value) => $query->where('appointment_service_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->where('scheduled_starts_at', '>=', $value.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->where('scheduled_starts_at', '<=', $value.' 23:59:59'))
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where(fn ($q) => $q->where('reference', 'like', '%'.$value.'%')->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$value.'%')->orWhere('email', 'like', '%'.$value.'%'))));

        return Inertia::render('admin/appointments/Requests', ['appointments' => $query->latest()->paginate(20)->withQueryString(),
            'filters' => $filters, 'services' => AppointmentService::orderBy('name')->get(['id', 'name'])]);
    }

    public function show(Request $request, Appointment $appointment, AppointmentAvailabilityService $availability): Response
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($data['month']) ? CarbonImmutable::createFromFormat('!Y-m', $data['month'], 'Asia/Kuala_Lumpur') : CarbonImmutable::now()->startOfMonth();
        $expiry = $appointment->user->memberships()->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now())->max('expires_at');

        return Inertia::render('admin/appointments/Review', [
            'appointment' => $appointment->load(['user:id,name,email,phone', 'histories', 'rescheduleRequests.targetSlot']),
            'membershipExpiresAt' => $expiry ? CarbonImmutable::parse($expiry, 'Asia/Kuala_Lumpur')->toIso8601String() : null,
            'slots' => in_array($appointment->status, ['pending', 'approved'], true) ? $availability->month($appointment->user, $appointment->service, $month, $appointment) : [],
            'month' => $month->format('Y-m'),
        ]);
    }

    public function update(Request $request, Appointment $appointment, AppointmentBookingService $service): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected,cancelled,completed,no_show'], 'remarks' => ['nullable', 'string', 'max:1000', 'required_if:decision,cancelled']]);
        if ($data['decision'] === 'cancelled') {
            $service->cancel($appointment, $request->user(), $data['remarks'], true);
        } else {
            $service->review($appointment, $request->user(), $data['decision'], $data['remarks'] ?? null);
        }

        return $this->updated();
    }

    public function propose(Request $request, Appointment $appointment, AppointmentBookingService $service): RedirectResponse
    {
        $data = $request->validate(['slot_id' => ['required', 'integer'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->requestReschedule($appointment, AppointmentSlot::whereKey($data['slot_id'])->firstOrFail(), $request->user(), $data['reason'], true);

        return $this->updated();
    }

    public function reviewReschedule(Request $request, AppointmentRescheduleRequest $rescheduleRequest, AppointmentBookingService $service): RedirectResponse
    {
        $data = $request->validate(['approve' => ['required', 'boolean'], 'remarks' => ['nullable', 'string', 'max:1000']]);
        $service->reviewReschedule($rescheduleRequest, $request->user(), $data['approve'], $data['remarks'] ?? null);

        return $this->updated();
    }

    private function updated(): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.updated')]);

        return back();
    }
}
