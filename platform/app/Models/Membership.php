<?php

namespace App\Models;

use App\Support\Earnings\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reader's ongoing monthly support for one persona.
 *
 * Cancelling sets `cancelled_at` and leaves the row: a reader who supported a
 * writer for two years and then stopped is part of that writer's history, and
 * deleting the record would erase it from both their views.
 */
class Membership extends Model
{
    public const ACTIVE = 'active';

    public const CANCELLED = 'cancelled';

    public const PAST_DUE = 'past_due';

    protected $fillable = [
        'user_id', 'persona_id', 'to_user_id', 'amount_minor', 'currency',
        'status', 'provider_ref', 'started_at', 'cancelled_at', 'renews_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renews_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function amount(): Money
    {
        return new Money($this->amount_minor, $this->currency);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE);
    }
}
