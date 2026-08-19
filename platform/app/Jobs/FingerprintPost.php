<?php

namespace App\Jobs;

use App\Models\CopyFlag;
use App\Models\Post;
use App\Models\Scopes\StandalonePostScope;
use App\Support\Copy\CopyDetector;
use App\Support\Copy\CopyMatch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Index a published piece and record anything it closely resembles.
 *
 * Queued rather than inline: fingerprinting is proportional to body length,
 * and nobody should wait on it to see their own piece go live. The work is
 * idempotent, so a retry re-indexes rather than duplicating.
 *
 * **Flags are a private signal, not an accusation.** They land on the
 * publishing author's own desk, where a genuine mistake — republishing a
 * draft, reusing a section, quoting at length without meaning to — can be
 * fixed quietly. The author of the earlier piece is deliberately *not*
 * notified: containment over sampled shingles is a probabilistic signal, and
 * turning a probabilistic signal into an automatic accusation between two real
 * people is how a moderation feature becomes a harassment vector.
 */
class FingerprintPost implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $postId) {}

    public function handle(CopyDetector $detector): void
    {
        $post = Post::withoutGlobalScope(StandalonePostScope::class)->find($this->postId);

        if (! $post) {
            return;
        }

        // Drafts are not indexed and not compared. An unpublished piece is not
        // yet a claim on anything, and indexing it would let a draft flag the
        // author's own later work against itself.
        if (! $post->isPublished()) {
            $detector->forget($post);

            return;
        }

        $detector->index($post);

        $detector->compare($post)
            // Reusing your own words is not plagiarism. Serial writers quote
            // themselves constantly and would otherwise flag on every piece.
            ->reject(fn (CopyMatch $match) => $match->post->user_id === $post->user_id)
            ->each(function (CopyMatch $match) use ($post) {
                CopyFlag::updateOrCreate(
                    ['post_id' => $post->id, 'matched_post_id' => $match->post->id],
                    ['kind' => $match->kind, 'similarity' => $match->similarity, 'status' => CopyFlag::OPEN],
                );
            });
    }
}
