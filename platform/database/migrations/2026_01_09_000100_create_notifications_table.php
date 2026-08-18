<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Writer notifications.
         *
         * Deliberately narrow: a writer is told when something *specific*
         * happened to their words — a passage was marked, a passage was
         * answered. There is no "someone followed you", because a follower
         * count is standing rather than feedback, and STRATEGY.md is explicit
         * that turning writing into standing is the failure mode to avoid.
         *
         * `quote` is denormalised on purpose. It is the whole content of the
         * notification — "this sentence landed" — and it must survive the
         * highlight being deleted, which is exactly when the writer would
         * otherwise lose the only specific praise they ever got.
         */
        Schema::create('writer_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');                       // mark | response
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_persona_id')->nullable()->constrained('personas')->nullOnDelete();
            $table->string('quote', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // The only two reads: the unread badge, and the listing.
            $table->index(['user_id', 'read_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('writer_notifications');
    }
};
