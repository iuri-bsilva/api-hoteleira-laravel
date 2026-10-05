<?php

namespace App\Interfaces\Services;

use App\Models\User;

interface AuthServiceInterface
{
    public function login(array $data): ?array;

    public function me(User $user): User;

    public function logout(User $user): void;
}
