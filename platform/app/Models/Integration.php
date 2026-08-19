<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integration extends Model
{
    protected $fillable = ['user_id', 'provider', 'credentials', 'settings', 'verified_at', 'last_used_at'];

    /**
     * Credentials never appear in a serialized model.
     *
     * The connection list is sent to the browser, and a token that reaches the
     * page — even inside a prop nothing renders — is a token in the DOM, in the
     * history state, and in anything that logs a response.
     *
     * @var list<string>
     */
    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'verified_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
