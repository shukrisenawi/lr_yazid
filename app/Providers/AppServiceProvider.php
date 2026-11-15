<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Mail\PhpMailTransport;
use App\Models\AppointmentNoticeDelivery;
use App\Models\CommerceNoticeDelivery;
use App\Models\MembershipReminder;
use App\Models\OrderFulfillmentHistory;
use App\Models\ProgramNoticeDelivery;
use App\Models\User;
use App\Notifications\AppointmentNotice;
use App\Notifications\CommerceNotice;
use App\Notifications\MembershipNotice;
use App\Notifications\ProgramNotice;
use App\Notifications\ShipmentNotice;
use App\Services\CartService;
use App\Services\CommerceNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\DevCommands;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
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
        Mail::extend('mail', fn () => new PhpMailTransport);
        if ($this->app->runningInConsole() && $this->app->environment('local')) {
            DevCommands::artisan('queue:listen database --queue=commerce-notices,default --tries=1 --timeout=0', 'queue');
        }
        Gate::define('admin', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::define('vip', fn (User $user): bool => $user->hasActiveMembership());
        Event::listen(Verified::class, function (Verified $event): void {
            if ($event->user instanceof User) {
                app(CommerceNotificationService::class)->welcome($event->user);
            }
        });
        Event::listen(Login::class, function (Login $event): void {
            if (request()->hasSession() && $event->user instanceof User) {
                app(CartService::class)->mergeGuest(request(), $event->user);
            }
        });
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            if ($event->notification instanceof CommerceNotice && in_array($event->channel, ['mail', 'database'], true)) {
                CommerceNoticeDelivery::whereKey($event->notification->deliveryId)->where('user_id', $event->notifiable->id)
                    ->whereNull($event->channel.'_sent_at')->update([$event->channel.'_sent_at' => now()]);
            }
            if ($event->notification instanceof ShipmentNotice && in_array($event->channel, ['mail', 'database'], true)) {
                OrderFulfillmentHistory::whereKey($event->notification->historyId)
                    ->where('order_id', $event->notification->orderId)
                    ->whereHas('order', fn ($query) => $query->where('user_id', $event->notifiable->id))
                    ->update([$event->channel.'_sent_at' => now()]);
            }
            if ($event->notification instanceof AppointmentNotice && in_array($event->channel, ['mail', 'database'], true)) {
                AppointmentNoticeDelivery::whereKey($event->notification->deliveryId)->where('user_id', $event->notifiable->id)
                    ->update([$event->channel.'_sent_at' => now()]);
            }
            if ($event->notification instanceof ProgramNotice && in_array($event->channel, ['mail', 'database'], true)) {
                ProgramNoticeDelivery::whereKey($event->notification->deliveryId)
                    ->where('user_id', $event->notifiable->id)->update([$event->channel.'_sent_at' => now()]);
            }
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
