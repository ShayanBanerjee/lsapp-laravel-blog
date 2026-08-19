<?php

namespace App\Support\Copy;

use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Resolved through the container, like Moderator, so a hosted service can
 * replace the local index without touching a call site.
 *
 * That matters here specifically because of what the local implementation
 * *cannot* do: it compares a piece against everything published on this
 * platform, and nothing else. Detecting copying from the open web needs a
 * third-party index (Copyleaks, Originality.ai and similar), which is a
 * per-check cost and an outbound copy of the author's unpublished text — both
 * decisions an operator should make deliberately rather than inherit.
 */
interface CopyDetector
{
    /**
     * Index a published body so later pieces can be compared against it.
     */
    public function index(Post $post): void;

    /**
     * Remove a piece from the index.
     */
    public function forget(Post $post): void;

    /**
     * Compare a piece against everything already indexed.
     *
     * @return Collection<int, CopyMatch>
     */
    public function compare(Post $post): Collection;
}
