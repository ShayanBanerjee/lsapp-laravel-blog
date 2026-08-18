<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A highlight is a mark on a passage, not a like on an article.
     *
     * Anchoring: block index + character offsets *within that block's plain
     * text*. Anchoring to whole-document offsets would break every highlight in
     * a piece whenever the author edits an earlier paragraph; per-block offsets
     * only break highlights in the block that actually changed. `quote` is kept
     * so a drifted highlight can be re-anchored by searching for its text.
     */
    public function up(): void
    {
        Schema::create('highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('block_index');
            $table->unsignedSmallInteger('start_offset');
            $table->unsignedSmallInteger('end_offset');
            $table->text('quote');

            $table->timestamps();

            // Feed queries: "the marked passages of this post", newest first.
            $table->index(['post_id', 'created_at']);
            // "which passages are most marked" — the trending signal.
            $table->index(['post_id', 'block_index', 'start_offset'], 'highlights_passage_index');
            // A reader's personal index of what mattered to them.
            $table->index(['user_id', 'created_at']);
            // One mark per reader per exact passage.
            $table->unique(['post_id', 'user_id', 'block_index', 'start_offset', 'end_offset'], 'highlights_unique_mark');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('highlights');
    }
};
