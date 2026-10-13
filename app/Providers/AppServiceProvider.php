<?php

namespace App\Providers;

use App\Models\Batch;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sku;
use App\Models\Supplier;
use App\Models\User;
use App\Policies\MasterDataPolicy;
use App\Policies\OperationsPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Superadmin can do everything — checked before any policy
        Gate::before(fn (User $user) => $user->isSuperadmin() ? true : null);

        // Master data: view all, change = admin
        Gate::policy(Product::class, MasterDataPolicy::class);
        Gate::policy(Sku::class, MasterDataPolicy::class);
        Gate::policy(Supplier::class, MasterDataPolicy::class);
        Gate::policy(Batch::class, MasterDataPolicy::class);
        Gate::policy(Location::class, MasterDataPolicy::class);

        // Daily work: everyone, delete = admin
        Gate::policy(Order::class, OperationsPolicy::class);
        Gate::policy(PurchaseOrder::class, OperationsPolicy::class);

        // Users: superadmin only
        Gate::policy(User::class, UserPolicy::class);
    }
}