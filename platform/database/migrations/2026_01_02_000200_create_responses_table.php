<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A response is anchored to the passage it responds to.
     *
     * `highlight_id` is nullable so a response to the piece as a whole is still
     * possible, but the UI leads with the anchored path: pointing at the text
     * you are answering removes most arguments-with-things-nobody-said.
     */
    public function up(): void
    {
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('highlight_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('responses')->cascadeOnDelete();

            $table->text('body');
            $table->timestamps();

            $table->index(['post_id', 'created_at']);
            $table->index(['highlight_id', 'created_at']);
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
