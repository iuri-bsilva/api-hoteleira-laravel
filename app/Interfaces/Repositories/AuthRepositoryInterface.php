<?php

namespace App\Interfaces\Repositories;

use App\Models\User;
use DateTimeInterface;
use Laravel\Sanctum\NewAccessToken;

interface AuthRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function createToken(User $user, string $deviceName, DateTimeInterface $expiresAt): NewAccessToken;

    public function withHotels(User $user): User;

    public function revokeCurrentToken(User $user): void;
}
