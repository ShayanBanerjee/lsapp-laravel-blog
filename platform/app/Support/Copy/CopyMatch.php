<?php

namespace App\Support\Copy;

use App\Models\Post;

/** One candidate overlap, before anyone decides what it means. */
readonly class CopyMatch
{
    public function __construct(
        public Post $post,
        public string $kind,
        public float $similarity,
    ) {}
}
