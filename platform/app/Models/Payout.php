<?php

namespace App\Models;

use App\Support\Earnings\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const FAILED = 'failed';

    protected $fillable = [
        'user_id', 'amount_minor', 'currency', 'status', 'provider_ref',
        'period_start', 'period_end', 'paid_at', 'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amount(): Money
    {
        return new Money($this->amount_minor, $this->currency);
    }
}
