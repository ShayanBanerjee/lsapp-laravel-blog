<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A reader's private shelf.
         *
         * Saving and starring are one table with a `kind`, not two: a piece is
         * frequently both, and the queries ("my shelf", "my favourites") are
         * identical apart from one predicate.
         */
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('saved');   // saved | starred
            $table->timestamps();

            $table->unique(['user_id', 'post_id', 'kind']);
            // The shelf listing: one user's items of one kind, newest first.
            $table->index(['user_id', 'kind', 'created_at']);
            $table->index('post_id');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            // Lucide icon name, so categories can be added without a deploy.
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        Schema::create('category_post', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['category_id', 'post_id']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_post');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('bookmarks');
    }
};
