<?php

namespace App\Providers;

use App\Http\Responses\Auth\LoginResponse;
use App\Http\Responses\Auth\LogoutResponse;
use App\Policies\RolePolicy;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Spatie's Role model lives outside App\Models, so its policy is registered by hand.
        Gate::policy(Role::class, RolePolicy::class);
    }
}
