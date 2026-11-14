<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MembershipController as AdminMembershipController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ShippingSettingsController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MembershipController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/vip-membership', [MembershipController::class, 'index'])->name('membership.info');
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [CatalogController::class, 'show'])->name('products.show');
Route::post('/payments/toyyibpay/callback', [PaymentController::class, 'callback'])->middleware('throttle:120,1')->name('payments.callback');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/items', [CartController::class, 'store'])->block()->middleware('throttle:60,1')->name('cart.store');
Route::patch('/cart/items/{cartItem}', [CartController::class, 'update'])->block()->name('cart.update');
Route::delete('/cart/items/{cartItem}', [CartController::class, 'destroy'])->block()->name('cart.remove');
Route::delete('/cart', [CartController::class, 'clear'])->block()->name('cart.clear');

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
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
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::get('vip/dashboard', [MembershipController::class, 'dashboard'])
        ->middleware('can:vip')->name('vip.dashboard');
    Route::get('admin/dashboard', fn () => Inertia::render('admin/Dashboard', [
        'customers' => User::where('role', 'customer')->count(),
        'activeVipMembers' => User::whereHas('memberships', fn ($query) => $query
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now()))->count(),
    ]))->middleware('can:admin')->name('admin.dashboard');
});

require __DIR__.'/settings.php';
