<?php

namespace App\Support\Integrations;

use Illuminate\Support\Collection;

/**
 * A service that stores highlights.
 *
 * This is the integration the platform is actually shaped for: a mark here is
 * the same object as a highlight there — a passage, its source, and optionally
 * a note — so the mapping is exact rather than approximate.
 */
interface ReceivesHighlights extends Integration
{
    /**
     * @param  array<string, string>  $credentials
     * @param  Collection<int, array<string, mixed>>  $highlights
     */
    public function sendHighlights(array $credentials, Collection $highlights): PushResult;
}
