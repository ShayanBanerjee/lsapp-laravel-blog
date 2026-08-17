<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** Free accounts get one persona; premium is unlimited. */
    public const FREE_PERSONA_LIMIT = 1;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_premium',
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
            'is_premium' => 'boolean',
        ];
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function follows(): HasMany
    {
        return $this->hasMany(Follow::class);
    }

    public function themeEntitlements(): HasMany
    {
        return $this->hasMany(ThemeEntitlement::class);
    }

    /**
     * Whether this user may write in — and receive the design tokens for — a
     * given universe. Free universes are open to everyone; premium ones need
     * either a subscription or an explicit unexpired entitlement row.
     */
    public function canAccessUniverse(Universe $universe): bool
    {
        if (! $universe->is_premium) {
            return true;
        }

        if ($this->is_premium) {
            return true;
        }

        return $this->themeEntitlements
            ->where('universe_id', $universe->id)
            ->contains(fn (ThemeEntitlement $entitlement) => $entitlement->isActive());
    }

    public function canCreateAnotherPersona(): bool
    {
        return $this->is_premium
            || $this->personas()->count() < self::FREE_PERSONA_LIMIT;
    }
}
