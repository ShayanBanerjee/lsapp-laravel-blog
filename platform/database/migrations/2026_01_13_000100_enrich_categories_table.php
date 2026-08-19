<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects get an editorial voice.
 *
 * A subject hub with nothing but a name and a count is a filter, not a place.
 * These three columns are what turn it into somewhere a writer decides to
 * write: a line that sets the tone, a photograph that gives it weight, and a
 * prompt that answers "yes but what would I actually write".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('tagline')->nullable()->after('name');
            $table->string('hero_image')->nullable()->after('description');
            $table->text('prompt')->nullable()->after('hero_image');
            $table->json('hero_credit')->nullable()->after('prompt');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'hero_image', 'prompt', 'hero_credit']);
        });
    }
};
