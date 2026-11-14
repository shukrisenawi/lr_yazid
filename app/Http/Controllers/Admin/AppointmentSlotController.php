<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentSlotRequest;
use App\Http\Requests\GenerateAppointmentSlotsRequest;
use App\Models\AppointmentBlockedDate;
use App\Models\AppointmentService;
use App\Models\AppointmentSlot;
use App\Services\AppointmentSchedulingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentSlotController extends Controller
{
    public function index(Request $request, AppointmentService $appointmentService): Response
    {
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'status' => ['nullable', 'in:available,disabled']]);

        return Inertia::render('admin/appointments/Slots', [
            'service' => $appointmentService,
            'slots' => $appointmentService->slots()->withCount('appointments')
                ->when($filters['from'] ?? null, fn ($query, $date) => $query->where('starts_at', '>=', $date.' 00:00:00'))
                ->when($filters['to'] ?? null, fn ($query, $date) => $query->where('starts_at', '<=', $date.' 23:59:59'))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->orderBy('starts_at')->paginate(30)->withQueryString(),
            'blockedDates' => $appointmentService->blockedDates()->where('date', '>=', now()->toDateString())->limit(366)->get(),
            'schedules' => $appointmentService->schedules()->latest()->limit(5)->get(),
            'generation' => $request->session()->get('slotGeneration'), 'filters' => $filters,
        ]);
    }

    public function store(AppointmentSlotRequest $request, AppointmentService $appointmentService, AppointmentSchedulingService $service): RedirectResponse
    {
        $service->saveSlot($appointmentService, null, $request->validated(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.slot_saved')]);

        return back();
    }

    public function update(AppointmentSlotRequest $request, AppointmentService $appointmentService, AppointmentSlot $slot, AppointmentSchedulingService $service): RedirectResponse
    {
        $service->saveSlot($appointmentService, $slot, $request->validated(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.slot_saved')]);

        return back();
    }

    public function destroy(AppointmentService $appointmentService, AppointmentSlot $slot, AppointmentSchedulingService $service): RedirectResponse
    {
        $service->deleteSlot($appointmentService, $slot);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.slot_deleted')]);

        return back();
    }

    public function generate(GenerateAppointmentSlotsRequest $request, AppointmentService $appointmentService, AppointmentSchedulingService $service): RedirectResponse
    {
        $result = $service->generate($appointmentService, $request->validated(), $request->user()->id);
        $request->session()->flash('slotGeneration', $result);
        if (! $result['preview']) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.generated', ['count' => $result['created']])]);
        }

        return back();
    }

    public function block(Request $request, AppointmentService $appointmentService, AppointmentSchedulingService $service): RedirectResponse
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'reason' => ['required', 'string', 'max:500']]);
        $service->blockDate($appointmentService, $data['date'], $data['reason'], $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.blocked_saved')]);

        return back();
    }

    public function unblock(AppointmentService $appointmentService, AppointmentBlockedDate $blockedDate, AppointmentSchedulingService $service): RedirectResponse
    {
        $service->unblockDate($appointmentService, $blockedDate);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.blocked_removed')]);

        return back();
    }
}
