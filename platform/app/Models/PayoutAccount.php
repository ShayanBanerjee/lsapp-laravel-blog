<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutAccount extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'provider_account_id', 'country',
        'currency', 'payouts_enabled_at', 'status',
    ];

    protected function casts(): array
    {
        return ['payouts_enabled_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Money can actually leave for this writer. */
    public function canReceivePayouts(): bool
    {
        return $this->payouts_enabled_at !== null;
    }
}
