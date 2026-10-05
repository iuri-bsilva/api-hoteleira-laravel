<?php

namespace App\Providers;

use App\Interfaces\Repositories\PaymentRepositoryInterface;
use App\Interfaces\Repositories\RoomRepositoryInterface;
use App\Interfaces\Services\PaymentServiceInterface;
use App\Interfaces\Services\RoomServiceInterface;
use App\Repositories\PaymentRepository;
use App\Repositories\RoomRepository;
use App\Services\PaymentService;
use App\Services\RoomService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        $this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);
        $this->app->bind(RoomServiceInterface::class, RoomService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())),
        ]);
    }
}
