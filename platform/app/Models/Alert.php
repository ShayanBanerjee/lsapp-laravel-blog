<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alert extends Model
{
    /** Someone marked a passage in your piece. */
    public const MARK = 'mark';

    /** Someone answered your piece, usually against a passage. */
    public const RESPONSE = 'response';

    /** Someone wrote to you privately. */
    public const LETTER = 'letter';

    /** Someone followed one of your personas. */
    public const FOLLOW = 'follow';

    /** Someone gave you money. Never aggregated — see Alerts::supported. */
    public const SUPPORT = 'support';

    protected $fillable = [
        'user_id', 'type', 'actor_persona_id', 'post_id', 'count', 'preview', 'read_at',
    ];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actorPersona(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'actor_persona_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
