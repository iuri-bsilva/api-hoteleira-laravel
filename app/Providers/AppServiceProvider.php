<?php

namespace App\Providers;

use App\Interfaces\Repositories\AuthRepositoryInterface;
use App\Interfaces\Repositories\CouponRepositoryInterface;
use App\Interfaces\Repositories\HotelRepositoryInterface;
use App\Interfaces\Repositories\PaymentRepositoryInterface;
use App\Interfaces\Repositories\ReservationRepositoryInterface;
use App\Interfaces\Repositories\RoomCategoryRepositoryInterface;
use App\Interfaces\Repositories\RoomRepositoryInterface;
use App\Interfaces\Services\AuthServiceInterface;
use App\Interfaces\Services\CouponServiceInterface;
use App\Interfaces\Services\HotelServiceInterface;
use App\Interfaces\Services\PaymentServiceInterface;
use App\Interfaces\Services\ReservationServiceInterface;
use App\Interfaces\Services\RoomCategoryServiceInterface;
use App\Interfaces\Services\RoomServiceInterface;
use App\Repositories\AuthRepository;
use App\Repositories\CouponRepository;
use App\Repositories\HotelRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Services\AuthService;
use App\Services\CouponService;
use App\Services\HotelService;
use App\Services\PaymentService;
use App\Services\ReservationService;
use App\Services\RoomCategoryService;
use App\Services\RoomService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(HotelRepositoryInterface::class, HotelRepository::class);
        $this->app->bind(HotelServiceInterface::class, HotelService::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ReservationRepositoryInterface::class, ReservationRepository::class);
        $this->app->bind(ReservationServiceInterface::class, ReservationService::class);
        $this->app->bind(RoomCategoryRepositoryInterface::class, RoomCategoryRepository::class);
        $this->app->bind(RoomCategoryServiceInterface::class, RoomCategoryService::class);
        $this->app->bind(PaymentRepositoryInterface::class, PaymentRepository::class);
        $this->app->bind(PaymentServiceInterface::class, PaymentService::class);
        $this->app->bind(RoomRepositoryInterface::class, RoomRepository::class);
        $this->app->bind(RoomServiceInterface::class, RoomService::class);
        $this->app->bind(CouponRepositoryInterface::class, CouponRepository::class);
        $this->app->bind(CouponServiceInterface::class, CouponService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('api-login', fn (Request $request) => [
            Limit::perMinute(10)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', strtolower((string) $request->input('email')).'|'.$request->ip())),
        ]);
    }
}
