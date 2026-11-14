<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $order->user_id === $user->id || $user->role === UserRole::Admin;
    }

    public function cancel(User $user, Order $order): bool
    {
        return $this->view($user, $order) && in_array($order->payment_status, ['pending', 'failed'], true);
    }

    public function pay(User $user, Order $order): bool
    {
        return $order->user_id === $user->id && in_array($order->payment_status, ['pending', 'failed'], true);
    }
}
