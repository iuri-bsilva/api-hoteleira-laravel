<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Console\Command;

class HotelAccess extends Command
{
    protected $signature = 'users:hotel {email} {hotel_id} {role=viewer : viewer, manager ou revoke}';

    protected $description = 'Concede, altera ou revoga acesso de usuário a um hotel';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        $hotel = Hotel::find($this->argument('hotel_id'));
        $role = $this->argument('role');
        if (! $user || ! $hotel || ! in_array($role, ['viewer', 'manager', 'revoke'], true)) {
            $this->error('Usuário/hotel inexistente ou perfil inválido. Use viewer, manager ou revoke.');

            return self::FAILURE;
        }
        if ($role === 'revoke') {
            $user->hotels()->detach($hotel->id);
        } else {
            $user->hotels()->syncWithoutDetaching([$hotel->id => ['role' => $role]]);
        }
        $this->info('Acesso atualizado. A alteração vale imediatamente, inclusive para tokens já emitidos.');

        return self::SUCCESS;
    }
}
