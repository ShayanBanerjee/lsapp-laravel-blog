<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('universes', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('tagline');
            $table->text('description');
            $table->string('material');           // "brushed steel & slate"
            $table->json('theme');                // design token payload
            $table->string('hero_image');         // public/ path, bundled locally
            $table->json('hero_credit');          // { name, username } — Unsplash attribution
            $table->boolean('is_premium')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_premium', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('universes');
    }
};
