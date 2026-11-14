<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\MembershipReminder;
use App\Models\User;
use App\Notifications\MembershipNotice;
use App\Services\CartService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        Gate::define('admin', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('vip', fn (User $user): bool => $user->hasActiveMembership());
        Event::listen(Login::class, function (Login $event): void {
            if (request()->hasSession() && $event->user instanceof User) {
                app(CartService::class)->mergeGuest(request(), $event->user);
            }
        });
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof MembershipNotice && $event->notification->reminderId) {
                $field = match ($event->channel) {
                    'mail' => 'mail_sent_at',
                    'database' => 'database_sent_at',
                    default => null,
                };
                if ($field) {
                    MembershipReminder::whereKey($event->notification->reminderId)->where('user_id', $event->notifiable->id)->update([$field => now()]);
                }
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
