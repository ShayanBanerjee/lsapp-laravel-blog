<?php

namespace App\Support\Integrations;

use App\Support\Academic\Manuscript;

/** A destination a finished piece can be sent to. */
interface PublishesPosts extends Integration
{
    /**
     * @param  array<string, string>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function publish(array $credentials, Manuscript $manuscript, array $settings = []): PushResult;
}
