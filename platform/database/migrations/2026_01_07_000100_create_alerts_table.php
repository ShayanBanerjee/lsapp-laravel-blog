<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient activity notices.
 *
 * Named `alerts` rather than `notifications` on purpose: Laravel's Notifiable
 * trait already owns a `notifications()` relation for its own database
 * channel, and quietly shadowing it would break `notify()` the first time
 * anyone reaches for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);

            // Who caused it, as a persona — the platform's unit of attribution.
            // Nullable so retiring a voice does not erase the notice.
            $table->foreignId('actor_persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->foreignId('post_id')->nullable()->constrained()->cascadeOnDelete();

            // Repeated events of the same kind collapse into one row and raise
            // this instead. Twelve separate "someone marked your piece" notices
            // is not twelve times the signal — it is noise, and noise is what
            // makes people turn notifications off.
            $table->unsignedInteger('count')->default(1);

            // A short excerpt of what happened, denormalized so rendering the
            // list never has to fan out into responses or highlights.
            $table->string('preview', 240)->nullable();

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The two reads that matter: the unread badge, and the list itself.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);

            // Aggregation looks a row up by exactly this shape.
            $table->index(['user_id', 'type', 'post_id', 'read_at'], 'alerts_aggregation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
