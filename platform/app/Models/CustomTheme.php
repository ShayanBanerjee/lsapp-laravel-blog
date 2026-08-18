<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomTheme extends Model
{
    /** Only these tokens may be overridden. Anything else is ignored on write. */
    public const EDITABLE = [
        'bg', 'bgDeep', 'surface1', 'surface2', 'border',
        'text', 'textMuted', 'accent', 'accentFg',
        'metalBase', 'metalSheen', 'metalEdge',
    ];

    protected $fillable = ['user_id', 'universe_id', 'name', 'overrides', 'is_active'];

    protected function casts(): array
    {
        return ['overrides' => 'array', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function universe(): BelongsTo
    {
        return $this->belongsTo(Universe::class);
    }

    /**
     * Keep only editable keys holding a valid hex colour.
     *
     * These values are interpolated into a `style` attribute as CSS custom
     * properties, so an unvalidated one is a CSS injection: a value like
     * `red; background-image: url(...)` would escape the declaration. Matching
     * a strict hex pattern removes the whole class of problem rather than
     * trying to escape it.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    public static function sanitizeOverrides(array $input): array
    {
        $clean = [];

        foreach (self::EDITABLE as $key) {
            $value = $input[$key] ?? null;

            if (is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $clean[$key] = strtolower($value);
            }
        }

        return $clean;
    }
}
