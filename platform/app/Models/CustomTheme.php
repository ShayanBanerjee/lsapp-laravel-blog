<?php

namespace App\Models;

use App\Support\ThemeTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An author's variation on a shipped universe.
 *
 * `tokens` is always a complete set — ThemeTokens::compose fills anything the
 * author left alone from the base universe — so rendering never has to merge
 * at read time, and a later change to the base universe cannot silently
 * restyle work that was already published.
 */
class CustomTheme extends Model
{
    protected $fillable = ['user_id', 'base_universe_id', 'name', 'slug', 'tokens'];

    protected function casts(): array
    {
        return ['tokens' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function baseUniverse(): BelongsTo
    {
        return $this->belongsTo(Universe::class, 'base_universe_id');
    }

    public function personas(): HasMany
    {
        return $this->hasMany(Persona::class);
    }

    /** The token set, plus the editor-facing values derived back out of it. */
    public function editorState(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'base_universe_id' => $this->base_universe_id,
            'tokens' => $this->tokens,
            'haloShape' => ThemeTokens::haloShapeOf($this->tokens),
            'contrast' => ThemeTokens::contrastReport($this->tokens),
        ];
    }
}
