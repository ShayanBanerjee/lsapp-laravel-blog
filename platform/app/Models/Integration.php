<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = ['user_id', 'service', 'token', 'settings', 'last_synced_at', 'last_error'];

    /**
     * `encrypted` on the token, and `hidden` so it cannot be serialized into an
     * Inertia page by accident — the connection status is the reader's
     * business, the credential is not.
     */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'settings' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
