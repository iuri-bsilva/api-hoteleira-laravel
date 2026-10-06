<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    public function hotels()
    {
        return $this->belongsToMany(Hotel::class)->withPivot('role')->withTimestamps();
    }

    public function hotelIds()
    {
        return $this->hotels()->select('hotels.id');
    }

    public function requireHotelAccess(int $hotelId, bool $write = false): void
    {
        $query = $this->hotels()->where('hotels.id', $hotelId);
        if ($write) {
            $query->wherePivot('role', 'manager');
        }
        abort_unless($query->exists(), 403, 'Sem permissão para esta operação no hotel.');
    }

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
