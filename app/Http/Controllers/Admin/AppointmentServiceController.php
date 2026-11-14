<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentServiceRequest;
use App\Models\AppointmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/appointments/Services', ['services' => AppointmentService::withCount('slots')->orderBy('id')->paginate(20)]);
    }

    public function store(AppointmentServiceRequest $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(AppointmentServiceRequest $request, AppointmentService $appointmentService): RedirectResponse
    {
        return $this->save($request, $appointmentService);
    }

    private function save(AppointmentServiceRequest $request, ?AppointmentService $service): RedirectResponse
    {
        $data = $request->validated();
        if ($data['minimum_notice_hours'] >= $data['maximum_advance_days'] * 24) {
            throw ValidationException::withMessages(['minimum_notice_hours' => 'Minimum notice must be shorter than the maximum advance booking period.']);
        }
        DB::transaction(function () use ($service, $data) {
            $service = $service ? AppointmentService::whereKey($service->id)->lockForUpdate()->firstOrFail() : new AppointmentService;
            $service->fill($data)->save();
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('appointment.service_saved')]);

        return back();
    }
}
