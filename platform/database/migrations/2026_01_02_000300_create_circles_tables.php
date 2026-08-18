<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Community organised by subject rather than by follower graph.
     *
     * A follower graph makes community a byproduct of fame. A circle makes it a
     * byproduct of what you are interested in, which is the thing people
     * actually want to gather around.
     */
    public function up(): void
    {
        Schema::create('circles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline');
            $table->text('description');
            // A circle may live inside one universe, or span all of them.
            $table->foreignId('universe_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['universe_id', 'sort_order']);
        });

        Schema::create('circle_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['circle_id', 'user_id']);
            $table->index('user_id');
        });

        // Which circles a piece was shared into.
        Schema::create('circle_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['circle_id', 'post_id']);
            $table->index(['circle_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circle_post');
        Schema::dropIfExists('circle_memberships');
        Schema::dropIfExists('circles');
    }
};
