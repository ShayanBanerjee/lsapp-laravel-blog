<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Every custom theme is a variation on a shipped universe, never a
            // blank slate: the base supplies any token the author did not set,
            // so a theme can never be half-defined.
            $table->foreignId('base_universe_id')->constrained('universes')->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');
            $table->json('tokens');
            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::table('personas', function (Blueprint $table) {
            // Deleting a theme must not delete the voice that wore it.
            $table->foreignId('custom_theme_id')->nullable()->after('universe_id')
                ->constrained('custom_themes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('personas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custom_theme_id');
        });

        Schema::dropIfExists('custom_themes');
    }
};
