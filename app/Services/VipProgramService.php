<?php

namespace App\Services;

use App\Jobs\AnnounceVipProgram;
use App\Models\VipProgram;
use App\Models\VipProgramRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class VipProgramService
{
    public function __construct(private ProductImageService $images, private RichTextService $richText) {}

    /** @param array<string, mixed> $data */
    public function save(?VipProgram $program, array $data): VipProgram
    {
        foreach (['starts_at', 'ends_at', 'registration_opens_at', 'registration_closes_at'] as $field) {
            $data[$field] = CarbonImmutable::parse($data[$field], 'Asia/Kuala_Lumpur')->setTimezone('Asia/Kuala_Lumpur')->toDateTimeString();
        }
        $stored = [];
        $removed = [];
        try {
            $result = DB::transaction(function () use ($program, $data, &$stored, &$removed) {
                $program = $program ? VipProgram::whereKey($program->id)->lockForUpdate()->firstOrFail() : new VipProgram;
                if ($program->exists && in_array($program->status, ['cancelled', 'completed', 'archived'], true) && $program->status !== $data['status'] && $data['status'] !== 'archived') {
                    throw ValidationException::withMessages(['status' => __('program.terminal')]);
                }
                $participants = $program->exists ? $program->registrations()->where('status', 'registered')->lockForUpdate()->get() : collect();
                $previousStatus = $program->status;
                if ($data['capacity'] < $participants->count()) {
                    throw ValidationException::withMessages(['capacity' => __('program.capacity')]);
                }
                $oldSchedule = $program->only(['starts_at', 'ends_at', 'location']);
                $program->fill(Arr::except($data, ['images', 'remove_image_ids']));
                if ($program->exists && $program->registrations()->exists()
                    && ($program->starts_at->ne($oldSchedule['starts_at']) || $program->ends_at->ne($oldSchedule['ends_at']) || $program->location !== $oldSchedule['location'])) {
                    throw ValidationException::withMessages(['starts_at' => __('program.schedule_locked')]);
                }
                if ($program->status === 'published' && $program->starts_at->lte(now())) {
                    throw ValidationException::withMessages(['starts_at' => 'Published programs must start in the future. Mark past programs completed.']);
                }
                if ($program->status === 'completed' && $program->ends_at->gt(now())) {
                    throw ValidationException::withMessages(['status' => 'A program cannot be completed before its end time.']);
                }
                if ($participants->isNotEmpty() && $program->status === 'archived' && $previousStatus !== 'completed') {
                    throw ValidationException::withMessages(['status' => 'Cancel the program to notify registered participants before archiving.']);
                }
                $program->description = $this->richText->sanitize($data['description'] ?? '');
                $program->save();
                $removeIds = $data['remove_image_ids'] ?? [];
                $selected = $program->images()->whereIn('id', $removeIds)->get();
                if ($selected->count() !== count($removeIds)) {
                    throw ValidationException::withMessages(['remove_image_ids' => 'Only images belonging to this program can be removed.']);
                }
                if ($program->images()->count() - $selected->count() + count($data['images'] ?? []) > 10) {
                    throw ValidationException::withMessages(['images' => 'A program may have at most 10 images.']);
                }
                foreach ($selected as $image) {
                    $removed[] = $image->path;
                    $image->delete();
                }
                $position = (int) $program->images()->max('position') + 1;
                foreach ($data['images'] ?? [] as $file) {
                    $path = $this->images->store($file, 'programs');
                    $stored[] = $path;
                    $program->images()->create(['path' => $path, 'position' => $position++]);
                }
                if ($program->status === 'cancelled') {
                    foreach ($participants as $registration) {
                        $registration->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => 'program_cancelled']);
                        app(ProgramNotificationService::class)->queue($registration->user, $program, 'cancellation', 'program_cancelled');
                    }
                }
                if ($program->status === 'published' && ! $program->publication_queued_at) {
                    $program->update(['publication_queued_at' => now()]);
                    AnnounceVipProgram::dispatch($program->id);
                }

                return $program;
            });
        } catch (Throwable $error) {
            Storage::disk('public')->delete($stored);
            throw $error;
        }
        Storage::disk('public')->delete($removed);

        return $result;
    }

    public function attendance(VipProgramRegistration $registration, string $attendance, int $adminId): void
    {
        DB::transaction(function () use ($registration, $attendance, $adminId) {
            $program = VipProgram::whereKey($registration->vip_program_id)->lockForUpdate()->firstOrFail();
            $registration = VipProgramRegistration::whereKey($registration->id)->lockForUpdate()->firstOrFail();
            if ($registration->status !== 'registered' || ! in_array($program->status, ['published', 'completed'], true) || $program->starts_at->gt(now())) {
                throw ValidationException::withMessages(['attendance' => __('program.attendance_unavailable')]);
            }
            $registration->update(['attendance' => $attendance, 'attendance_by' => $adminId, 'attendance_at' => now()]);
        }, 3);
    }
}
