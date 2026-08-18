<?php

namespace App\Support;

use App\Models\Post;
use App\Models\User;
use App\Models\WriterNotification;

/**
 * Tell a writer that something specific happened to their words.
 *
 * The thesis in one line: writers do not quit because of criticism, they quit
 * because of silence. A count is silence with a number attached — so every
 * notification here carries the *passage*, not a tally.
 */
class Notifier
{
    public static function marked(Post $post, User $reader, string $quote): void
    {
        // Marking your own work is not feedback.
        if ($post->user_id === $reader->id) {
            return;
        }

        WriterNotification::create([
            'user_id' => $post->user_id,
            'type' => 'mark',
            'post_id' => $post->id,
            'actor_persona_id' => null,   // marks stay anonymous; the passage is the point
            'quote' => HtmlSanitizer::plain($quote, 500),
        ]);
    }

    public static function answered(Post $post, User $responder, ?int $personaId, string $body): void
    {
        if ($post->user_id === $responder->id) {
            return;
        }

        WriterNotification::create([
            'user_id' => $post->user_id,
            'type' => 'response',
            'post_id' => $post->id,
            'actor_persona_id' => $personaId,
            'quote' => HtmlSanitizer::plain($body, 500),
        ]);
    }

    public static function unreadFor(?User $user): int
    {
        return $user ? WriterNotification::where('user_id', $user->id)->whereNull('read_at')->count() : 0;
    }
}
