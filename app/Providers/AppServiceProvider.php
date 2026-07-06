<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use App\Models\Order;
use App\Models\PurchaseOrder;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.app', function ($view) {
            $view->with([
                'ordersBadgeCount'    => Order::where('wc_status', 'processing')->count(),
                'purchasesBadgeCount' => PurchaseOrder::where('status', 'pending')->count(),
            ]);
        });
    }
}
