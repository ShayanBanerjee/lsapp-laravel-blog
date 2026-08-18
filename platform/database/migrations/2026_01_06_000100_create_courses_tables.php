<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Learning paths — a course of several modules, each of several lessons.
         *
         * This exists because text is *addressable* in a way video is not: a
         * reader can land on the one section that matters, leave, and come back
         * to exactly there. A contents panel and per-section progress are what
         * that property looks like as a feature.
         */
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();

            // Ownership follows the human, attribution the persona — same split
            // as posts, and for the same reason.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();

            $table->string('cover_image')->nullable();
            $table->string('level')->default('beginner');   // beginner | intermediate | advanced
            $table->string('status')->default('draft');     // draft | published
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // The course index: published, newest first.
            $table->index(['status', 'published_at']);
            $table->index('universe_id');
            $table->index('user_id');
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            // Every read of a module is "this course's modules, in order".
            $table->index(['course_id', 'sort_order']);
        });

        /*
         * A lesson is a post.
         *
         * Not a parallel content type: making lessons rows in `posts` means
         * highlights, responses, letters, bookmarks, reading typography and the
         * sanitiser all apply to them unchanged, rather than every one of those
         * features needing a polymorphic second case. The community layer is
         * the product — a lesson that could not be marked would be a worse
         * lesson.
         */
        Schema::table('posts', function (Blueprint $table) {
            $table->string('kind')->default('piece')->after('status');   // piece | lesson
            $table->foreignId('course_module_id')->nullable()->after('kind')
                ->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0)->after('course_module_id');

            // Reading a module: its lessons, in order.
            $table->index(['course_module_id', 'sort_order']);
            // Every public listing filters on kind, so it belongs in that index.
            $table->index(['kind', 'status', 'published_at']);
        });

        /**
         * Per-lesson completion.
         *
         * Deliberately a row that exists or does not, rather than a percentage:
         * progress here is something the reader asserts, not something we infer
         * from scroll depth. Inferred progress is how a platform ends up
         * telling someone they have read something they have not.
         */
        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'completed_at']);
            $table->index('post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropForeign(['course_module_id']);
            $table->dropIndex(['course_module_id', 'sort_order']);
            $table->dropIndex(['kind', 'status', 'published_at']);
            $table->dropColumn(['kind', 'course_module_id', 'sort_order']);
        });

        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
    }
};
