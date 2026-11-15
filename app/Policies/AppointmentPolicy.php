<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id || $user->role === UserRole::Admin;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->id === $appointment->user_id;
    }
}
