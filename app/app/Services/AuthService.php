<?php

namespace App\Services;

use App\Interfaces\Repositories\AuthRepositoryInterface;
use App\Interfaces\Services\AuthServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService implements AuthServiceInterface
{
    public function __construct(private readonly AuthRepositoryInterface $users) {}

    public function login(array $data): ?array
    {
        $user = $this->users->findByEmail(strtolower($data['email']));
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return null;
        }
        $expiresAt = now()->addHours(8);
        $token = $this->users->createToken($user, $data['device_name'] ?? 'api', $expiresAt);

        return [
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $user,
        ];
    }

    public function me(User $user): User
    {
        return $this->users->withHotels($user);
    }

    public function logout(User $user): void
    {
        $this->users->revokeCurrentToken($user);
    }
}
