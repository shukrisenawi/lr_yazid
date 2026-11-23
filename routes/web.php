<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\AppointmentController as AdminAppointmentController;
use App\Http\Controllers\Admin\AppointmentServiceController;
use App\Http\Controllers\Admin\AppointmentSlotController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CommerceNoticeController;
use App\Http\Controllers\Admin\ContentPageController as AdminContentPageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\CustomerImpersonationController;
use App\Http\Controllers\Admin\HomepageBannerController;
use App\Http\Controllers\Admin\MembershipController as AdminMembershipController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RecordActionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ShippingSettingsController;
use App\Http\Controllers\Admin\VipProgramController as AdminVipProgramController;
use App\Http\Controllers\Admin\WebsiteSettingController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ContentPageController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\VipProgramController;
use App\Http\Middleware\CustomerOnly;
use Illuminate\Support\Facades\Route;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::post('/impersonation/stop', [CustomerImpersonationController::class, 'destroy'])
    ->middleware('auth')->block()->name('impersonation.stop');
Route::get('/pages/{slug}', [ContentPageController::class, 'show'])->name('content.show');
Route::get('/lp/{slug}', [ContentPageController::class, 'landing'])->name('landing.show');
Route::get('/vip-membership', [MembershipController::class, 'index'])->middleware(CustomerOnly::class)->name('membership.info');
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [CatalogController::class, 'show'])->name('products.show');
Route::post('/payments/toyyibpay/callback', [PaymentController::class, 'callback'])->middleware('throttle:120,1')->name('payments.callback');
Route::middleware(CustomerOnly::class)->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'store'])->block()->middleware('throttle:60,1')->name('cart.store');
    Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->block()->name('cart.update');
    Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->block()->name('cart.remove');
    Route::delete('/cart', [CartController::class, 'clear'])->block()->name('cart.clear');
});

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::delete('records/{module}/{record}', [RecordActionController::class, 'destroy'])
        ->whereNumber('record')->name('records.destroy');
    Route::patch('records/{module}/{record}/archive', [RecordActionController::class, 'archive'])
        ->whereNumber('record')->name('records.archive');
    Route::get('commerce-notices', [CommerceNoticeController::class, 'index'])->name('commerce-notices.index');
    Route::get('dashboard', [ReportController::class, 'index'])->name('dashboard');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportController::class, 'export'])->middleware('throttle:10,1')->name('reports.export');
    Route::get('website-settings', [WebsiteSettingController::class, 'edit'])->name('website-settings.edit');
    Route::put('website-settings', [WebsiteSettingController::class, 'update'])->name('website-settings.update');
    Route::resource('banners', HomepageBannerController::class)->except(['show', 'destroy']);
    Route::resource('content-pages', AdminContentPageController::class)->parameters(['content-pages' => 'contentPage'])->except(['show', 'destroy']);
    Route::get('invoice-settings', [InvoiceController::class, 'settings'])->name('invoices.settings');
    Route::put('invoice-settings', [InvoiceController::class, 'updateSettings'])->name('invoices.settings.update');
    Route::post('orders/{order}/invoice', [InvoiceController::class, 'issue'])->name('orders.invoice');
    Route::get('orders/export', [AdminOrderController::class, 'export'])->middleware('throttle:10,1')->name('orders.export');
    Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
    Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::put('orders/{order}', [AdminOrderController::class, 'update'])->name('orders.update');
    Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::post('orders/{order}/manual-refunds', [AdminOrderController::class, 'refund'])->name('orders.manual-refunds.store');
    Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::post('customers/{customer}/login', [CustomerImpersonationController::class, 'store'])
        ->block()->name('customers.login');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::get('appointments', [AdminAppointmentController::class, 'index'])->name('appointments.index');
    Route::get('appointments/calendar', [AdminAppointmentController::class, 'calendar'])->name('appointments.calendar');
    Route::get('appointments/{appointment}', [AdminAppointmentController::class, 'show'])->name('appointments.show');
    Route::put('appointments/{appointment}', [AdminAppointmentController::class, 'update'])->name('appointments.update');
    Route::post('appointments/{appointment}/propose', [AdminAppointmentController::class, 'propose'])->name('appointments.propose');
    Route::put('appointment-reschedules/{rescheduleRequest}', [AdminAppointmentController::class, 'reviewReschedule'])->name('appointments.reschedules.review');
    Route::resource('appointment-services', AppointmentServiceController::class)->only(['index', 'store', 'update']);
    Route::prefix('appointment-services/{appointmentService}')->name('appointment-services.')->group(function () {
        Route::get('slots', [AppointmentSlotController::class, 'index'])->name('slots.index');
        Route::post('slots', [AppointmentSlotController::class, 'store'])->name('slots.store');
        Route::put('slots/{slot}', [AppointmentSlotController::class, 'update'])->name('slots.update');
        Route::delete('slots/{slot}', [AppointmentSlotController::class, 'destroy'])->name('slots.destroy');
        Route::post('generate-slots', [AppointmentSlotController::class, 'generate'])->middleware('throttle:10,1')->name('slots.generate');
        Route::post('blocked-dates', [AppointmentSlotController::class, 'block'])->name('blocked-dates.store');
        Route::delete('blocked-dates/{blockedDate}', [AppointmentSlotController::class, 'unblock'])->name('blocked-dates.destroy');
    });
    Route::resource('programs', AdminVipProgramController::class)->except(['show', 'destroy']);
    Route::get('programs/{program}/participants', [AdminVipProgramController::class, 'participants'])->name('programs.participants');
    Route::get('programs/{program}/export', [AdminVipProgramController::class, 'export'])->name('programs.export');
    Route::put('programs/{program}/participants/{registration}/attendance', [AdminVipProgramController::class, 'attendance'])->name('programs.attendance');
    Route::resource('products', ProductController::class)->except('show');
    Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'removeImage'])->name('products.images.destroy');
    Route::post('products/{product}/images/{image}', [ProductController::class, 'replaceImage'])->name('products.images.replace');
    Route::post('editor-images', [ProductController::class, 'upload'])->middleware('throttle:30,1')->name('editor-images.store');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('shipping', [ShippingSettingsController::class, 'edit'])->name('shipping.edit');
    Route::put('shipping', [ShippingSettingsController::class, 'update'])->name('shipping.update');
    Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
    Route::get('membership-plans', [MembershipPlanController::class, 'index'])->name('membership-plans.index');
    Route::get('memberships', [AdminMembershipController::class, 'index'])->name('memberships.index');
    Route::put('memberships/{membership}/expiry', [AdminMembershipController::class, 'adjust'])->name('memberships.adjust');
    Route::post('membership-plans', [MembershipPlanController::class, 'store'])->name('membership-plans.store');
    Route::put('membership-plans/{membershipPlan}', [MembershipPlanController::class, 'update'])->name('membership-plans.update');
    Route::get('payment-settings', [AdminPaymentController::class, 'settings'])->name('payments.settings');
    Route::put('payment-settings', [AdminPaymentController::class, 'update'])->name('payments.settings.update');
    Route::post('payments/{payment}/reconcile', [AdminPaymentController::class, 'reconcile'])->middleware('throttle:10,1')->name('payments.reconcile');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::middleware(CustomerOnly::class)->group(function () {
        Route::get('/vip/appointments/book', [AppointmentController::class, 'calendar'])->middleware('can:vip')->name('appointments.book');
        Route::post('/vip/appointments', [AppointmentController::class, 'store'])->middleware(['can:vip', 'throttle:20,1'])->name('appointments.store');
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel'])->name('appointments.cancel');
        Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->middleware(['can:vip', 'throttle:20,1'])->name('appointments.reschedule');
        Route::post('/appointment-reschedules/{rescheduleRequest}/respond', [AppointmentController::class, 'respond'])->name('appointments.reschedules.respond');
        Route::get('/my-programs', [VipProgramController::class, 'mine'])->name('programs.mine');
        Route::post('/my-programs/{registration}/cancel', [VipProgramController::class, 'cancel'])->name('programs.cancel');
        Route::middleware('can:vip')->prefix('vip/programs')->name('vip.programs.')->group(function () {
            Route::get('/', [VipProgramController::class, 'index'])->name('index');
            Route::get('/{slug}', [VipProgramController::class, 'show'])->name('show');
            Route::post('/{program}/register', [VipProgramController::class, 'register'])->middleware('throttle:20,1')->name('register');
        });
        Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
        Route::post('/checkout', [CheckoutController::class, 'store'])->block()->middleware('throttle:10,1')->name('checkout.store');
        Route::resource('addresses', AddressController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->block()->name('orders.cancel');
        Route::post('/orders/{order}/pay', [PaymentController::class, 'start'])->block(30, 30)->middleware('throttle:10,1')->name('orders.pay');
        Route::get('/payments/return', [PaymentController::class, 'result'])->name('payments.return');
        Route::inertia('dashboard', 'customer/Dashboard')->name('dashboard');
        Route::get('/membership', [MembershipController::class, 'index'])->name('membership.index');
        Route::get('vip/dashboard', [MembershipController::class, 'dashboard'])
            ->middleware('can:vip')->name('vip.dashboard');
    });
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
});

require __DIR__.'/settings.php';
