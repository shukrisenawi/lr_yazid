<?php

namespace App\Http\Controllers;

use App\Models\VipProgram;
use App\Models\VipProgramRegistration;
use App\Services\ProgramRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VipProgramController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('vip/programs/Index', [
            'programs' => VipProgram::where('status', 'published')->where('ends_at', '>', now())->with('images')
                ->withCount(['registrations as registered_count' => fn ($query) => $query->where('status', 'registered')])
                ->orderBy('starts_at')->paginate(12),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $program = VipProgram::where('slug', $slug)->whereIn('status', ['published', 'completed', 'cancelled'])->with('images')
            ->withCount(['registrations as registered_count' => fn ($query) => $query->where('status', 'registered')])->firstOrFail();
        $eligible = $request->user()->memberships()->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', $program->ends_at)->exists();

        return Inertia::render('vip/programs/Show', [
            'program' => $program, 'eligible' => $eligible,
            'registration' => $program->registrations()->where('user_id', $request->user()->id)->first(),
        ]);
    }

    public function register(Request $request, VipProgram $program, ProgramRegistrationService $service): RedirectResponse
    {
        $service->register($request->user(), $program);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('program.registered')]);

        return to_route('programs.mine');
    }

    public function mine(Request $request): Response
    {
        return Inertia::render('customer/Programs', [
            'registrations' => VipProgramRegistration::where('user_id', $request->user()->id)->with('program.images')->latest('registered_at')->paginate(15),
        ]);
    }

    public function cancel(Request $request, VipProgramRegistration $registration, ProgramRegistrationService $service): RedirectResponse
    {
        $service->cancel($request->user(), $registration);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('program.cancelled')]);

        return back();
    }
}
