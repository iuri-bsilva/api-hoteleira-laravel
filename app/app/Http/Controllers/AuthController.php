<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Interfaces\Services\AuthServiceInterface;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function login(LoginRequest $request)
    {
        $result = $this->auth->login($request->validated());
        if ($result === null) {
            return response()->json(['message' => 'Credenciais inválidas.'], 401);
        }

        return response()->json($result);
    }

    public function me(Request $request)
    {
        return response()->json($this->auth->me($request->user()));
    }

    public function logout(Request $request)
    {
        $this->auth->logout($request->user());

        return response()->json(['message' => 'Token revogado.']);
    }
}
