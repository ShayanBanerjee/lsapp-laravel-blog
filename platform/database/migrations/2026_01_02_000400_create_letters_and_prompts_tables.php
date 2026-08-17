<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A private note from a reader to a writer.
         *
         * Deliberately not public: no audience means no performance, which is
         * why this is the highest-signal thing a reader can send and the thing
         * writers report missing most.
         */
        Schema::create('letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['to_user_id', 'read_at']);
            $table->index(['to_user_id', 'created_at']);
        });

        /**
         * The blank page is the reason most pieces are never started, so each
         * universe carries prompts written in its own register.
         */
        Schema::create('prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('universe_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts');
        Schema::dropIfExists('letters');
    }
};
