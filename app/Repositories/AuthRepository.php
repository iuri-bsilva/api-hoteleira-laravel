<?php

namespace App\Repositories;

use App\Interfaces\Repositories\AuthRepositoryInterface;
use App\Models\User;
use DateTimeInterface;
use Laravel\Sanctum\NewAccessToken;

class AuthRepository implements AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function createToken(User $user, string $deviceName, DateTimeInterface $expiresAt): NewAccessToken
    {
        return $user->createToken($deviceName, ['*'], $expiresAt);
    }

    public function withHotels(User $user): User
    {
        return $user->load('hotels');
    }

    public function revokeCurrentToken(User $user): void
    {
        $user->currentAccessToken()->delete();
    }
}
