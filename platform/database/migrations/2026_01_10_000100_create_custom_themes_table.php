<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A reader-authored palette.
         *
         * Stored as token *overrides* over a base universe rather than a whole
         * token set: components read `--u-bg`, `--u-accent` and the rest and
         * never branch on which universe they came from, so a custom theme is
         * the same shape as a seeded one and needs no component to know it
         * exists. A seventh universe is still a seeder row; this is a seeder
         * row a user wrote.
         */
        Schema::create('custom_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('overrides');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_themes');
    }
};
