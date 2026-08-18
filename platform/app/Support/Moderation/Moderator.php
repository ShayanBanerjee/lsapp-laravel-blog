<?php

namespace App\Support\Moderation;

interface Moderator
{
    public function check(string $text): ModerationVerdict;
}
