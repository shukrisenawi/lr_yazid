<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\ContentPage;
use App\Models\HomepageBanner;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\VipProgram;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RecordActionController extends Controller
{
    public function destroy(Request $request, string $module, int $record): RedirectResponse
    {
        $models = [
            'banners' => HomepageBanner::class, 'content-pages' => ContentPage::class,
            'programs' => VipProgram::class, 'appointment-services' => AppointmentService::class,
            'appointments' => Appointment::class, 'membership-plans' => MembershipPlan::class,
            'memberships' => Membership::class, 'customers' => User::class,
        ];
        abort_unless(isset($models[$module]), 404);
        try {
            DB::transaction(function () use ($models, $module, $record, $request) {
                $model = $models[$module]::whereKey($record)->lockForUpdate()->firstOrFail();
                if ($model instanceof User) {
                    abort_unless($model->role->value === 'customer', 404);
                    // Financial and booking records must never cascade away with a customer.
                    $this->reject($model->orders()->exists() || $model->memberships()->withTrashed()->exists()
                        || DB::table('appointments')->where('user_id', $record)->exists()
                        || DB::table('vip_program_registrations')->where('user_id', $record)->exists(),
                        'This customer has transaction or booking history and cannot be deleted.');
                }
                if ($model instanceof Appointment) {
                    $this->reject(in_array($model->status, ['pending', 'approved'], true)
                        || $model->rescheduleRequests()->where('status', 'pending')->exists(),
                        'Cancel or complete the appointment and resolve pending reschedules before deleting it.');
                }
                if ($model instanceof Membership) {
                    $this->reject($model->status === 'active' && $model->expires_at->isFuture(),
                        'Adjust the membership expiry before deleting an active membership.');
                }
                if ($model instanceof VipProgram) {
                    $this->reject($model->registrations()->where('status', 'registered')->exists(),
                        'Cancel the program registrations before deleting this program.');
                }
                if ($model instanceof AppointmentService) {
                    $this->reject(Appointment::where('appointment_service_id', $record)->whereIn('status', ['pending', 'approved'])->exists(),
                        'Cancel or complete active appointments before deleting this service.');
                    $model->update(['is_active' => false]);
                }
                if ($model instanceof MembershipPlan) {
                    $model->update(['is_active' => false]);
                    $model->product?->update(['status' => 'inactive']);
                }
                $model->delete();
                $this->audit($request, $module, $record, 'deleted', 'Confirmed deletion from the administrator list.');
            }, 3);
        } catch (QueryException $error) {
            if (! in_array((string) $error->getCode(), ['23000', '23503'], true)) {
                throw $error;
            }
            throw ValidationException::withMessages(['record' => 'This record is still in use and cannot be deleted.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => __('record_management.deleted')]);

        return redirect('/admin/'.$module);
    }

    public function archive(Request $request, string $module, int $record): RedirectResponse
    {
        abort_unless(in_array($module, ['orders', 'payments'], true), 404);
        $data = $request->validate(['archived' => ['required', 'boolean'], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($request, $module, $record, $data) {
            $class = $module === 'orders' ? Order::class : Payment::class;
            $model = $class::whereKey($record)->lockForUpdate()->firstOrFail();
            if ($data['archived']) {
                if ($model instanceof Order) {
                    $this->reject(! in_array($model->payment_status, ['cancelled', 'refunded'], true)
                        && $model->fulfillment_status !== 'delivered',
                        'Cancel, refund or deliver this order before archiving it.');
                    $this->reject($model->payments()->where(function ($query) {
                        $query->whereIn('status', ['creating', 'creation_unknown', 'pending'])->orWhere('requires_review', true);
                    })->exists(), 'Resolve pending payments and payment reviews before archiving this order.');
                } else {
                    $this->reject(in_array($model->status, ['creating', 'creation_unknown', 'pending'], true) || $model->requires_review,
                        'Reconcile this payment and resolve any review before archiving it.');
                }
            }
            $model->update(['archived_at' => $data['archived'] ? now() : null]);
            $this->audit($request, $module, $record, $data['archived'] ? 'archived' : 'restored', $data['reason']);
        }, 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('record_management.archive_saved')]);

        return back();
    }

    private function reject(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['record' => $message]);
        }
    }

    private function audit(Request $request, string $module, int $record, string $action, string $reason): void
    {
        DB::table('admin_record_actions')->insert([
            'admin_id' => $request->user()->id, 'module' => $module, 'record_id' => $record,
            'action' => $action, 'reason' => $reason, 'created_at' => now(),
        ]);
    }
}
