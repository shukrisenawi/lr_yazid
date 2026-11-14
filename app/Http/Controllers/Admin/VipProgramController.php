<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\VipProgramRequest;
use App\Models\VipProgram;
use App\Models\VipProgramRegistration;
use App\Services\VipProgramService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VipProgramController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published,cancelled,completed,archived']]);

        return Inertia::render('admin/programs/Index', [
            'programs' => VipProgram::with('images')->withCount(['registrations as registered_count' => fn ($query) => $query->where('status', 'registered')])
                ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->latest()->paginate(20)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/programs/Edit', ['program' => null]);
    }

    public function edit(VipProgram $program): Response
    {
        return Inertia::render('admin/programs/Edit', ['program' => $program->load('images')]);
    }

    public function store(VipProgramRequest $request, VipProgramService $service): RedirectResponse
    {
        $program = $service->save(null, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('program.saved')]);

        return to_route('admin.programs.edit', $program);
    }

    public function update(VipProgramRequest $request, VipProgram $program, VipProgramService $service): RedirectResponse
    {
        $service->save($program, $request->validated());
        Inertia::flash('toast', ['type' => 'success', 'message' => __('program.saved')]);

        return back();
    }

    public function participants(Request $request, VipProgram $program): Response
    {
        $filters = $request->validate(['status' => ['nullable', 'in:registered,cancelled'], 'attendance' => ['nullable', 'in:unmarked,attended,no_show']]);

        return Inertia::render('admin/programs/Participants', [
            'program' => $program,
            'registrations' => $program->registrations()->with('user:id,name,email,phone')
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['attendance'] ?? null, fn ($query, $attendance) => $query->where('attendance', $attendance))
                ->latest('registered_at')->paginate(30)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function attendance(Request $request, VipProgram $program, VipProgramRegistration $registration, VipProgramService $service): RedirectResponse
    {
        abort_unless($registration->vip_program_id === $program->id, 404);
        $data = $request->validate(['attendance' => ['required', 'in:unmarked,attended,no_show']]);
        $service->attendance($registration, $data['attendance'], $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('program.attendance')]);

        return back();
    }

    public function export(VipProgram $program): StreamedResponse
    {
        return response()->streamDownload(function () use ($program) {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('The export stream could not be opened.');
            }
            fputcsv($stream, ['Reference', 'Name', 'Email', 'Phone', 'Status', 'Attendance', 'Registered at (MYT)'], escape: '');
            foreach ($program->registrations()->with('user:id,name,email,phone')->orderBy('id')->lazyById(200) as $registration) {
                $values = [$registration->reference, $registration->user->name, $registration->user->email,
                    $registration->user->phone ?? '', $registration->status, $registration->attendance, $registration->registered_at->format('Y-m-d H:i:s')];
                $values = array_map(fn ($value) => preg_match('/^[\s]*[=+\-@\t\r\n]/u', $value) ? "'".$value : $value, $values);
                fputcsv($stream, $values, escape: '');
            }
            fclose($stream);
        }, 'program-'.$program->id.'-participants.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
