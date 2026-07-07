<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPickerController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BulkGameAssignmentController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\VoidGameController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PackController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ResettableStockController;
use App\Http\Controllers\WooProductPickerController;
use App\Http\Controllers\WooWebhookController;
use Illuminate\Support\Facades\Route;

// ─── Autenticación ─────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
Route::get('api/pack/candidates', [PackController::class, 'packCandidates'])->middleware(['api.key', 'throttle:60,1']);
// ─── App (protegida) ───────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', fn () => redirect()->route('orders.index'));

    // Orders
    Route::get('/orders',          [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/poll', [OrderController::class, 'poll'])->name('orders.poll');
    Route::get('/orders/{order}',  [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/heartbeat', [OrderController::class, 'heartbeat'])->name('orders.heartbeat');
    Route::delete('/orders/bulk', [OrderController::class, 'bulkDestroy'])->name('orders.bulk-destroy');

    Route::post('/orders/{order}/add-item', [OrderController::class, 'addItem'])->name('orders.add-item');
    Route::delete('/order-items/{item}', [OrderController::class, 'destroyItem'])->name('order-items.destroy');
    Route::post('/items/{item}/notify-game-change', [OrderController::class, 'notifyGameChange'])->name('items.notify-game-change');

    Route::get('items/{item}/secondary-candidates', [OrderController::class, 'secondaryCandidates'])
        ->name('items.secondary-candidates');
    Route::post('items/{item}/assign-secondary', [OrderController::class, 'assignSecondary'])
        ->name('items.assign-secondary');

    // Asignación por ítem
    Route::post('/items/{item}/assign', [OrderController::class, 'assignItem'])
        ->name('items.assign');
    // Asignación masiva por orden
    Route::post('/orders/{order}/assign-all', [OrderController::class, 'assignAll'])
    ->name('orders.assign-all');

    // OC desde ítem sin stock
    Route::post('/items/{item}/purchase-order', [PurchaseOrderController::class, 'storeFromItem'])->name('items.purchase-order.store');
    Route::post('purchase-orders/stock', [PurchaseOrderController::class, 'storeStockAccount'])->name('purchase-orders.stock.store');
    Route::post('/purchase-orders/{purchaseOrder}/reset', [PurchaseOrderController::class, 'reset'])->name('purchase-orders.reset');
    

    Route::post('/items/{item}/send-to-reset', [PurchaseOrderController::class, 'sendToReset'])->name('items.send-to-reset');

    Route::get('/export/accounts-without-game', [AccountController::class, 'exportAccountWithNoGame'])->name('accounts.export.without-game');
    Route::get('/accounts/picker', [AccountPickerController::class, 'index'])->name('accounts.picker');

    Route::get('/accounts/bulk-assign',  [BulkGameAssignmentController::class, 'index'])->name('accounts.bulk-assign');
    Route::post('/accounts/bulk-assign', [BulkGameAssignmentController::class, 'store'])->name('accounts.bulk-assign.store');
    Route::get('accounts/secondary-stock', [AccountController::class, 'secondaryStock'])->name('accounts.secondary-stock');
    Route::get('accounts/products-without-account', [AccountController::class, 'productsWithoutAccount'])->name('accounts.products-without-account');
    Route::post('accounts/{account}/assignments/{assignment}/status',
        [AccountController::class, 'updateAssignmentStatus'])
        ->name('accounts.assignments.status');

    Route::post('accounts/{account}/secondary-assignments/{assignment}/status',
        [AccountController::class, 'updateSecondaryAssignmentStatus'])
        ->name('accounts.secondary-assignments.status');

    Route::get('accounts/products-without-account/export', [AccountController::class, 'exportProductsWithoutAccount'])->name('accounts.products-without-account.export');

    Route::get('accounts/products-sold-out', [AccountController::class, 'productsSoldOut'])->name('accounts.products-sold-out');
    Route::get('accounts/products-sold-out/export', [AccountController::class, 'exportProductsSoldOut'])->name('accounts.products-sold-out.export');

    Route::get('accounts/platform-mismatch', [AccountController::class, 'platformMismatch'])
        ->name('accounts.platform-mismatch');
    Route::get('accounts/platform-mismatch/export', [AccountController::class, 'exportPlatformMismatch'])
        ->name('accounts.platform-mismatch.export');

    // Accounts (CRUD + acciones operativas)
    Route::resource('accounts', AccountController::class);
    Route::post('/accounts/{account}/disable',         [AccountController::class, 'disable'])->name('accounts.disable');
    Route::post('/accounts/{account}/enable',          [AccountController::class, 'enable'])->name('accounts.enable');
    Route::post('/accounts/{account}/usage/increment', [AccountController::class, 'incrementUsage'])->name('accounts.usage.increment');
    Route::post('/accounts/{account}/usage/decrement', [AccountController::class, 'decrementUsage'])->name('accounts.usage.decrement');
    Route::post('/accounts/{account}/reset',           [AccountController::class, 'reset'])->name('accounts.reset');
    Route::post('/accounts/{account}/reset-snooze',   [AccountController::class, 'snoozeReset'])->name('accounts.reset-snooze.set');
    Route::delete('/accounts/{account}/reset-snooze', [AccountController::class, 'clearResetSnooze'])->name('accounts.reset-snooze.clear');

    Route::post('accounts/{account}/secondary-usage/increment', [AccountController::class, 'incrementSecondaryUsage'])
        ->name('accounts.secondary-usage.increment');

    Route::post('accounts/{account}/secondary-usage/decrement', [AccountController::class, 'decrementSecondaryUsage'])
        ->name('accounts.secondary-usage.decrement');

    Route::get('/stock/reseteables', [ResettableStockController::class, 'index'])->name('stock.resettable');

    // Games
    Route::get('/games',         [GameController::class, 'index'])->name('games.index');
    Route::get('/games/picker',  [GameController::class, 'picker'])->name('games.picker'); // la que ya tenías
    Route::get('/games/{game}',  [GameController::class, 'show'])->name('games.show');

    Route::get('/woo-products/picker', WooProductPickerController::class)
    ->name('woo-products.picker');
    // Purchase Orders
    Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
        Route::get('/',                         [PurchaseOrderController::class, 'index'])->name('index');
        Route::post('/',                        [PurchaseOrderController::class, 'store'])->name('store');
        Route::delete('{purchaseOrder}',        [PurchaseOrderController::class, 'destroy'])->name('destroy');
        Route::post('{purchaseOrder}/complete', [PurchaseOrderController::class, 'complete'])->name('complete');
    });

    Route::get('/juegos/sin-producto', [VoidGameController::class, 'withoutProduct'])->name('games.without-product');


    Route::get('accounts/mismatched/show',          [AccountController::class, 'mismatched'])->name('accounts.mismatched');
    Route::get('accounts/{account}/reassign',  [AccountController::class, 'reassignForm'])->name('accounts.reassign.form');
    Route::post('accounts/{account}/reassign', [AccountController::class, 'reassign'])->name('accounts.reassign');

   
  

// Route::resource('accounts', AccountController::class);   // ← debe quedar DEBAJO
});

Route::middleware('auth')->group(function () {
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
    });
});
