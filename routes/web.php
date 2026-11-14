<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\CatalogController;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [CatalogController::class, 'home'])->name('home');
Route::get('/products', [CatalogController::class, 'index'])->name('products.index');
Route::get('/products/{slug}', [CatalogController::class, 'show'])->name('products.show');

Route::middleware(['auth', 'verified', 'can:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::resource('products', ProductController::class)->except('show');
    Route::post('products/{product}/duplicate', [ProductController::class, 'duplicate'])->name('products.duplicate');
    Route::delete('products/{product}/images/{image}', [ProductController::class, 'removeImage'])->name('products.images.destroy');
    Route::post('editor-images', [ProductController::class, 'upload'])->middleware('throttle:30,1')->name('editor-images.store');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'customer/Dashboard')->name('dashboard');
    Route::get('vip/dashboard', fn () => Inertia::render('vip/Dashboard'))
        ->middleware('can:vip')->name('vip.dashboard');
    Route::get('admin/dashboard', fn () => Inertia::render('admin/Dashboard', [
        'customers' => User::where('role', 'customer')->count(),
        'activeVipMembers' => User::whereHas('memberships', fn ($query) => $query
            ->where('status', 'active')->where('starts_at', '<=', now())->where('expires_at', '>', now()))->count(),
    ]))->middleware('can:admin')->name('admin.dashboard');
});

require __DIR__.'/settings.php';
