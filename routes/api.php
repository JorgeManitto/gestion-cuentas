<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Endpoint que recibe el webhook del plugin de Woo.
// La URL final es /api/webhook/order (Laravel agrega el prefijo /api automáticamente).
Route::post('/webhook/order', [OrderController::class, 'store'])
    ->middleware('throttle:120,1');
