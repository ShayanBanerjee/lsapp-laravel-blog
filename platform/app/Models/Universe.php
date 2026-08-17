<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Universe extends Model
{
    protected $fillable = [
        'slug', 'name', 'tagline', 'description', 'material',
        'theme', 'is_premium', 'sort_order', 'hero_image', 'hero_credit',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'hero_credit' => 'array',
            'is_premium' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function followers(): MorphMany
    {
        return $this->morphMany(Follow::class, 'followable');
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(ThemeEntitlement::class);
    }

    /**
     * Identity + a three-colour preview. Always safe to serialize: it is enough
     * to render a card thumbnail, but not enough to reconstruct the theme.
     *
     * @return array<string, mixed>
     */
    public function preview(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'material' => $this->material,
            'is_premium' => $this->is_premium,
            // The photograph is identity, not a paid asset — showing a locked
            // world is the whole point of the paywall preview. Only the token
            // set below is gated.
            'hero_image' => $this->hero_image,
            'hero_credit' => $this->hero_credit,
            'swatch' => [
                'bg' => $this->theme['bg'],
                'accent' => $this->theme['accent'],
                'metalBase' => $this->theme['metalBase'],
            ],
            'scheme' => $this->theme['scheme'],
        ];
    }

    /**
     * The complete token set. Only ever reached through UniversePolicy::use —
     * see that policy for why this must not be gated client-side.
     *
     * @return array<string, mixed>
     */
    public function tokens(): array
    {
        return $this->preview() + ['theme' => $this->theme];
    }
}
