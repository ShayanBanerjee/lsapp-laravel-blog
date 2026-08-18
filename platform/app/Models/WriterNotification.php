<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WriterNotification extends Model
{
    protected $table = 'writer_notifications';

    protected $fillable = ['user_id', 'type', 'post_id', 'actor_persona_id', 'quote', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Persona::class, 'actor_persona_id');
    }
}
