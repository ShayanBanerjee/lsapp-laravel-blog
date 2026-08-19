<?php

namespace App\Models;

use App\Support\Earnings\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contribution extends Model
{
    /** A one-off thank-you for a specific piece. */
    public const TIP = 'tip';

    /** One month's charge against an ongoing membership. */
    public const MEMBERSHIP = 'membership';

    public const PENDING = 'pending';

    public const SETTLED = 'settled';

    public const REFUNDED = 'refunded';

    public const FAILED = 'failed';

    protected $fillable = [
        'from_user_id', 'to_user_id', 'persona_id', 'post_id', 'kind', 'currency',
        'gross_minor', 'platform_fee_minor', 'writer_net_minor', 'rate_basis_points',
        'status', 'provider', 'provider_ref', 'message', 'is_anonymous', 'settled_at',
    ];

    protected function casts(): array
    {
        return [
            'is_anonymous' => 'boolean',
            'settled_at' => 'datetime',
        ];
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function gross(): Money
    {
        return new Money($this->gross_minor, $this->currency);
    }

    public function writerNet(): Money
    {
        return new Money($this->writer_net_minor, $this->currency);
    }

    public function platformFee(): Money
    {
        return new Money($this->platform_fee_minor, $this->currency);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', self::SETTLED);
    }

    /**
     * Settled long enough ago that a chargeback is no longer likely.
     *
     * Paying out inside the dispute window means reclaiming money from a
     * writer who has already been told it is theirs.
     */
    public function scopePayable(Builder $query): Builder
    {
        return $query->settled()
            ->where('settled_at', '<=', now()->subDays((int) config('earnings.payout_hold_days')));
    }

    /** How the supporter should be named, honouring anonymity. */
    public function supporterName(): string
    {
        if ($this->is_anonymous) {
            return 'A reader';
        }

        return $this->fromUser?->name ?? 'A reader';
    }
}
