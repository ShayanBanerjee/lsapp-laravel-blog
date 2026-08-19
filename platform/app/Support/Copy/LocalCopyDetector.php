<?php

namespace App\Support\Copy;

use App\Models\CopyFlag;
use App\Models\Post;
use App\Models\Scopes\StandalonePostScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Compares a piece against everything published on this platform.
 *
 * The comparison is one grouped query, not a scan: candidates are found by
 * joining the new piece's sampled shingle hashes against every other piece's,
 * which the (hash, post_id) index answers directly. Cost scales with how much
 * a piece *shares*, not with how much has ever been published.
 */
class LocalCopyDetector implements CopyDetector
{
    /**
     * Report a piece when this much of it also appears elsewhere.
     *
     * Set from what the two errors cost. A false positive here is a private
     * note on the author's own desk, which is cheap; a false negative is
     * someone's work taken without attribution, which is not. But set too low
     * and every piece in a subject flags against every other, at which point
     * nobody reads the flags and the feature is worse than absent.
     */
    public const CONTAINMENT_THRESHOLD = 0.30;

    /** Below this many shared shingles, overlap is coincidence. */
    public const MIN_SHARED = 4;

    /** Differing bits below which two pieces are substantially the same document. */
    public const DUPLICATE_DISTANCE = 6;

    public function index(Post $post): void
    {
        $shingles = Fingerprint::shingles($post->body);

        if (count(Fingerprint::words($post->body)) < Fingerprint::MIN_WORDS || $shingles === []) {
            $this->forget($post);

            return;
        }

        $sample = Fingerprint::sample($shingles);

        DB::transaction(function () use ($post, $shingles, $sample) {
            // Re-indexed wholesale on every edit rather than diffed: a body is
            // small, and a partial update would leave shingles from text the
            // author has since deleted.
            DB::table('post_shingles')->where('post_id', $post->id)->delete();

            DB::table('post_fingerprints')->updateOrInsert(
                ['post_id' => $post->id],
                [
                    'simhash' => Fingerprint::simhash($shingles),
                    'shingle_count' => count($sample),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            foreach (array_chunk($sample, 500) as $chunk) {
                DB::table('post_shingles')->insert(array_map(
                    fn (string $hash) => ['post_id' => $post->id, 'hash' => $hash],
                    $chunk,
                ));
            }
        });
    }

    public function forget(Post $post): void
    {
        DB::table('post_shingles')->where('post_id', $post->id)->delete();
        DB::table('post_fingerprints')->where('post_id', $post->id)->delete();
    }

    public function compare(Post $post): Collection
    {
        $fingerprint = DB::table('post_fingerprints')->where('post_id', $post->id)->first();

        if (! $fingerprint || $fingerprint->shingle_count === 0) {
            return collect();
        }

        $shared = DB::table('post_shingles as mine')
            ->join('post_shingles as theirs', 'mine.hash', '=', 'theirs.hash')
            ->where('mine.post_id', $post->id)
            ->where('theirs.post_id', '!=', $post->id)
            ->groupBy('theirs.post_id')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_SHARED])
            ->select('theirs.post_id', DB::raw('COUNT(*) as shared'))
            ->pluck('shared', 'post_id');

        if ($shared->isEmpty()) {
            return collect();
        }

        // Lessons are Posts, so the scope has to come off or a copied lesson
        // would be invisible to detection.
        $candidates = Post::withoutGlobalScope(StandalonePostScope::class)
            ->whereIn('id', $shared->keys())
            ->with(['persona:id,handle,display_name', 'user:id,name'])
            ->get()
            ->keyBy('id');

        $simhashes = DB::table('post_fingerprints')
            ->whereIn('post_id', $shared->keys())
            ->pluck('simhash', 'post_id');

        return $shared
            ->map(function (int $count, int $candidateId) use ($fingerprint, $candidates, $simhashes) {
                $candidate = $candidates->get($candidateId);

                if (! $candidate) {
                    return null;
                }

                $distance = Fingerprint::hamming($fingerprint->simhash, (string) $simhashes[$candidateId]);

                // A whole-document match is reported as such even when
                // containment is modest — a repost with paragraphs shuffled
                // shares fewer shingles than you would expect but is still the
                // same piece.
                if ($distance <= self::DUPLICATE_DISTANCE) {
                    return new CopyMatch($candidate, CopyFlag::DUPLICATE, round(1 - ($distance / 64), 4));
                }

                $containment = Fingerprint::containment($count, (int) $fingerprint->shingle_count);

                return $containment >= self::CONTAINMENT_THRESHOLD
                    ? new CopyMatch($candidate, CopyFlag::CONTAINMENT, $containment)
                    : null;
            })
            ->filter()
            ->sortByDesc(fn (CopyMatch $match) => $match->similarity)
            ->values();
    }
}
