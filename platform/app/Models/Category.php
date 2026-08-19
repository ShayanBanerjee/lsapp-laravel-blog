<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    protected $fillable = [
        'slug', 'name', 'tagline', 'description', 'hero_image', 'prompt', 'hero_credit', 'icon', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['hero_credit' => 'array'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withTimestamps();
    }

    /** @return array<string, mixed> */
    public function preview(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'hero_image' => $this->hero_image,
            'prompt' => $this->prompt,
            'icon' => $this->icon,
            'posts_count' => $this->posts_count ?? null,
        ];
    }
}
