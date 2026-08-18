<?php

namespace App\Models;

use App\Support\ReadingPreferences;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
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
        'reading_prefs',
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
            'reading_prefs' => 'array',
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

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_memberships')->withTimestamps();
    }

    public function lettersReceived(): HasMany
    {
        return $this->hasMany(Letter::class, 'to_user_id');
    }

    public function socialIdentities(): HasMany
    {
        return $this->hasMany(SocialIdentity::class);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function lettersSent(): HasMany
    {
        return $this->hasMany(Letter::class, 'from_user_id');
    }

    /** Typography settings, always complete and always safe to render. */
    public function readingPreferences(): array
    {
        return ReadingPreferences::normalize($this->reading_prefs);
    }

    /** Free accounts see ads; paying removes them outright. */
    public function seesAds(): bool
    {
        return ! $this->is_premium;
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

    /**
     * Abandoned signups: unverified, older than the grace window, and empty.
     *
     * The exclusions matter more than the rule. An account that holds *any*
     * trace of a human — a persona, a draft, a mark, a letter, a response, a
     * saved piece — is never a throwaway, and deleting it would destroy work.
     * Social identities are excluded for a separate reason: a provider that
     * does not assert a verified address (Facebook) leaves a perfectly real
     * person sitting at `email_verified_at = null` forever, and they must not
     * be swept up by a cleanup aimed at registration spam.
     *
     * @param  Builder<User>  $query
     */
    public function scopeAbandonedUnverified(Builder $query, int $days): void
    {
        $query->whereNull('email_verified_at')
            ->where('created_at', '<', now()->subDays($days))
            ->whereDoesntHave('socialIdentities')
            ->whereDoesntHave('personas')
            ->whereDoesntHave('posts')
            ->whereDoesntHave('highlights')
            ->whereDoesntHave('responses')
            ->whereDoesntHave('lettersSent')
            ->whereDoesntHave('lettersReceived')
            ->whereDoesntHave('bookmarks');
    }
}
