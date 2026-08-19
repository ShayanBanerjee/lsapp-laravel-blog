<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copy detection.
 *
 * Two representations are stored per published body because they answer
 * different questions:
 *
 * - `simhash` on post_fingerprints answers "is this the same piece overall",
 *   in one 64-bit comparison. It is cheap and catches wholesale reposts.
 * - `post_shingles` answers "did a passage move between two pieces", which is
 *   what actual plagiarism usually looks like — a few paragraphs, not a whole
 *   article. SimHash cannot see that: a long original with three stolen
 *   paragraphs is overwhelmingly its own words, so its simhash barely moves.
 *
 * Shingles are sampled, not stored whole — see Fingerprint::sample().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();

            // 16 hex characters. Stored as text rather than a signed 64-bit
            // integer so the value means the same thing on SQLite, MySQL and
            // Postgres, none of which agree about unsigned bigints.
            $table->char('simhash', 16);

            $table->unsignedInteger('shingle_count')->default(0);
            $table->timestamps();

            $table->index('simhash');
        });

        Schema::create('post_shingles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->char('hash', 16);

            // The lookup is "which other posts share these hashes", so hash
            // leads the index.
            $table->index(['hash', 'post_id']);
        });

        Schema::create('copy_flags', function (Blueprint $table) {
            $table->id();
            // The piece that was just published.
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            // The earlier piece it resembles.
            $table->foreignId('matched_post_id')->constrained('posts')->cascadeOnDelete();

            $table->string('kind', 20);
            $table->float('similarity');
            $table->string('status', 20)->default('open');
            $table->timestamps();

            $table->unique(['post_id', 'matched_post_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copy_flags');
        Schema::dropIfExists('post_shingles');
        Schema::dropIfExists('post_fingerprints');
    }
};
