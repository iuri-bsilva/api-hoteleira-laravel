<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateApiUser extends Command
{
    protected $signature = 'users:create';

    protected $description = 'Cria usuário da API; solicita senha sem exibir no terminal';

    public function handle(): int
    {
        $data = [
            'name' => trim((string) $this->ask('Nome')),
            'email' => strtolower(trim((string) $this->ask('Email'))),
            'password' => $this->secret('Senha (mínimo 12 caracteres, letras e números)'),
            'password_confirmation' => $this->secret('Confirme a senha'),
        ];
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::create(array_intersect_key($data, array_flip(['name', 'email', 'password'])));
        $this->info('Usuário criado. Faça login em POST /api/auth/login.');

        return self::SUCCESS;
    }
}
