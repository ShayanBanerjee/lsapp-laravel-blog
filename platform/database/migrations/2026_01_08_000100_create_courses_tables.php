<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-page learning paths.
 *
 * The decision worth explaining is that **a lesson is a Post**, not a new
 * table. Lessons want everything a piece of writing already has here —
 * marks, responses, letters, bookmarks, sanitisation, reading time, SEO — and
 * a parallel `lessons` table would mean reimplementing every one of them, then
 * keeping the two implementations in step forever.
 *
 * The cost of that choice is that lessons must never leak into the places
 * standalone writing appears. That is handled by a global scope
 * (App\Models\Scopes\StandalonePostScope) rather than by remembering to filter
 * at each of the nine call sites, so the failure mode is inverted: reading
 * lessons requires opting in, and forgetting hides them rather than exposing
 * them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Attribution follows the persona, ownership the human — the same
            // split posts already make, for the same reason.
            $table->foreignId('persona_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('universe_id')->constrained()->cascadeOnDelete();

            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('level', 20)->default('beginner');
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('course_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'position']);
        });

        Schema::table('posts', function (Blueprint $table) {
            // Set means "this post is a lesson". Cascade rather than nullOnDelete:
            // a lesson outside its module is not an article, it is an orphan
            // that the standalone scope would then surface as one.
            $table->foreignId('course_module_id')->nullable()->after('universe_id')
                ->constrained('course_modules')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0)->after('course_module_id');

            $table->index(['course_module_id', 'position']);
        });

        Schema::create('lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // Denormalized so "how far through this course am I" is one indexed
            // read rather than a join through modules on every lesson page.
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');

        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('course_module_id');
            $table->dropColumn('position');
        });

        Schema::dropIfExists('course_modules');
        Schema::dropIfExists('courses');
    }
};
