<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PurchaseOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('orders.index'));

Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

// Asignación es por ítem, no por order: cada juego de una compra necesita su propia cuenta
Route::post('/items/{item}/assign', [OrderController::class, 'assignItem'])
    ->name('items.assign');

// Generar OC desde un ítem sin stock
Route::post('/items/{item}/purchase-order', [PurchaseOrderController::class, 'storeFromItem'])
    ->name('items.purchase-order.store');

Route::get('/purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');

Route::resource('accounts', AccountController::class);

// Acciones operativas sobre cuentas
Route::post('/accounts/{account}/disable',          [AccountController::class, 'disable'])->name('accounts.disable');
Route::post('/accounts/{account}/enable',           [AccountController::class, 'enable'])->name('accounts.enable');
Route::post('/accounts/{account}/usage/increment',  [AccountController::class, 'incrementUsage'])->name('accounts.usage.increment');
Route::post('/accounts/{account}/usage/decrement',  [AccountController::class, 'decrementUsage'])->name('accounts.usage.decrement');
Route::post('/accounts/{account}/reset',            [AccountController::class, 'reset'])->name('accounts.reset');

Route::get('/games/picker', [GameController::class, 'picker'])->name('games.picker');



Route::prefix('purchase-orders')->name('purchase-orders.')->group(function () {
    Route::get('/',                       [PurchaseOrderController::class, 'index'])->name('index');
    Route::post('/',                      [PurchaseOrderController::class, 'store'])->name('store');
    Route::delete('{purchaseOrder}',      [PurchaseOrderController::class, 'destroy'])->name('destroy');
    Route::post('{purchaseOrder}/complete', [PurchaseOrderController::class, 'complete'])->name('complete');
});