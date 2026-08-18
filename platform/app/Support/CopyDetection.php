<?php

namespace App\Support;

use App\Models\DuplicateFlag;
use App\Models\Post;
use App\Models\PostFingerprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Near-duplicate detection, run on publish.
 *
 * Scope, stated plainly: this reliably detects copying **within the platform**.
 * It cannot detect copying *from the open web*, because that requires an index
 * of the open web — Copyleaks, Originality.ai and similar sell one, at a
 * per-check cost. Nothing here should ever be presented to a writer as proof
 * that a piece is original; it answers exactly one question, which is "is this
 * very close to something already published here?".
 *
 * Nothing is blocked or removed automatically, ever. A flag is a prompt for a
 * human.
 */
class CopyDetection
{
    /** Containment at or above which a pair is worth a human look. */
    public const THRESHOLD = 0.35;

    /** Below this many shared shingles, a match is coincidence. */
    private const MINIMUM_SHARED = 5;

    /** One enormous piece must not dominate the shingle index. */
    private const SHINGLE_CAP = 4000;

    /**
     * Fingerprint a piece and flag anything it closely resembles.
     *
     * @return Collection<int, DuplicateFlag>
     */
    public static function check(Post $post): Collection
    {
        $hashes = Fingerprint::of($post->body);

        if ($hashes === null) {
            self::forget($post);

            return collect();
        }

        $hashes = array_slice($hashes, 0, self::SHINGLE_CAP);

        self::store($post, $hashes);

        $flags = collect();

        foreach (self::candidates($post, $hashes) as $candidate) {
            $containment = Fingerprint::containment(
                (int) $candidate->shared,
                count($hashes),
                (int) $candidate->shingles,
            );

            if ($containment < self::THRESHOLD) {
                continue;
            }

            $flags->push(DuplicateFlag::updateOrCreate(
                ['post_id' => $post->id, 'matched_post_id' => $candidate->post_id],
                [
                    'containment' => (int) round($containment * 100),
                    'shared' => (int) $candidate->shared,
                    'status' => 'pending',
                ],
            ));
        }

        return $flags;
    }

    /**
     * Posts sharing enough shingles to be worth measuring.
     *
     * This is the indexed step, and the reason publishing does not get slower
     * with every piece ever written: the shingle_hash index answers "who else
     * has these phrases" directly, so only genuine candidates are ever loaded.
     *
     * @param  list<int>  $hashes
     * @return Collection<int, object{post_id: int, shared: int, shingles: int}>
     */
    private static function candidates(Post $post, array $hashes): Collection
    {
        return DB::table('post_shingles')
            ->join('post_fingerprints', 'post_fingerprints.post_id', '=', 'post_shingles.post_id')
            ->whereIn('post_shingles.shingle_hash', $hashes)
            ->where('post_shingles.post_id', '!=', $post->id)
            ->groupBy('post_shingles.post_id', 'post_fingerprints.shingles')
            ->havingRaw('COUNT(*) >= ?', [self::MINIMUM_SHARED])
            ->orderByRaw('COUNT(*) DESC')
            ->limit(20)
            ->get([
                'post_shingles.post_id as post_id',
                'post_fingerprints.shingles as shingles',
                DB::raw('COUNT(*) as shared'),
            ]);
    }

    /** @param list<int> $hashes */
    private static function store(Post $post, array $hashes): void
    {
        DB::transaction(function () use ($post, $hashes) {
            // Replace wholesale: an edited piece must not keep matching on
            // phrases it no longer contains.
            DB::table('post_shingles')->where('post_id', $post->id)->delete();

            foreach (array_chunk($hashes, 500) as $chunk) {
                DB::table('post_shingles')->insert(
                    array_map(fn (int $hash) => ['post_id' => $post->id, 'shingle_hash' => $hash], $chunk),
                );
            }

            PostFingerprint::updateOrCreate(
                ['post_id' => $post->id],
                ['shingles' => count($hashes)],
            );
        });
    }

    private static function forget(Post $post): void
    {
        DB::table('post_shingles')->where('post_id', $post->id)->delete();
        PostFingerprint::where('post_id', $post->id)->delete();
    }
}
