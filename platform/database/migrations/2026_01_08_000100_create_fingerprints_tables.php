<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * Near-duplicate detection over published bodies.
         *
         * The unit is a *shingle* — an overlapping run of four words. A bag of
         * words matches any two pieces on the same subject; overlapping word
         * runs capture phrasing, which is what copying actually preserves and
         * what light editing mostly fails to destroy.
         *
         * Measured empirically on this corpus, comparing a piece against edits
         * of itself and against unrelated writing:
         *
         *   identical / reformatted     containment 1.00
         *   one word changed                        0.97
         *   sentences reordered                     0.91
         *   three words changed                     0.84
         *   ~10% reworded                           0.73
         *   one paragraph of three copied           0.55
         *   unrelated writing                       0.00
         *   same subject, different words           0.00
         *
         * Containment rather than Jaccard, because Jaccard punishes a copy for
         * being pasted into a longer piece — the paragraph-lifted case scores
         * 0.26 by Jaccard and 0.55 by containment, and the second number is the
         * one describing what happened.
         */
        Schema::create('post_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('shingles')->default(0);
            $table->timestamps();
        });

        /**
         * One row per distinct shingle. This is the index that makes detection
         * a lookup rather than a scan: finding candidates is "which posts share
         * my shingles", answered by the index on shingle_hash, instead of
         * comparing against every piece ever published on each publish.
         *
         * Capped per post (see CopyDetection::SHINGLE_CAP) so one enormous
         * piece cannot dominate the table.
         */
        Schema::create('post_shingles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('shingle_hash');

            $table->index('shingle_hash');
            $table->unique(['post_id', 'shingle_hash']);
        });

        /**
         * A flagged pair, awaiting a human.
         *
         * Deliberately never an automatic block. Two pieces can legitimately be
         * near-identical — an author reposting their own work, a quoted primary
         * document, a translation — and a platform that auto-removes writing on
         * a similarity score will eventually delete something it should not.
         */
        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matched_post_id')->constrained('posts')->cascadeOnDelete();
            $table->unsignedTinyInteger('containment');   // percent
            $table->unsignedInteger('shared');
            $table->string('status')->default('pending'); // pending | dismissed | confirmed
            $table->timestamps();

            $table->unique(['post_id', 'matched_post_id']);
            $table->index(['status', 'created_at']);
            $table->index('matched_post_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
        Schema::dropIfExists('post_shingles');
        Schema::dropIfExists('post_fingerprints');
    }
};
