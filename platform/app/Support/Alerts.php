<?php

namespace App\Support;

use App\Models\Alert;
use App\Models\Persona;
use App\Models\Post;
use App\Models\User;

/**
 * Raising activity notices.
 *
 * The product thesis is that writers quit because of silence, so this is the
 * feature that answers it directly — but only if it stays worth reading. Two
 * rules keep it that way, and both are enforced here rather than at the call
 * sites, because a call site added later will forget them:
 *
 * 1. **Nobody is notified about themselves.** Marking your own paragraph or
 *    answering your own piece raises nothing.
 * 2. **Repeats collapse.** A second mark on the same piece while the first
 *    notice is still unread raises the count on that row instead of adding
 *    another. Ten notices saying the same thing is how a person learns to
 *    ignore the badge.
 */
class Alerts
{
    /**
     * How long an unread notice stays open to absorb repeats.
     *
     * Long enough that a burst of attention reads as one event; short enough
     * that interest a week later is not silently folded into a stale row.
     */
    public const AGGREGATION_WINDOW_HOURS = 12;

    public static function markedPassage(Post $post, User $reader, ?Persona $actor, ?string $quote): ?Alert
    {
        return self::raise($post->user_id, Alert::MARK, $reader, $actor, $post, self::trim($quote));
    }

    public static function responded(Post $post, User $responder, ?Persona $actor, string $body): ?Alert
    {
        return self::raise($post->user_id, Alert::RESPONSE, $responder, $actor, $post, self::trim($body));
    }

    public static function letterReceived(int $recipientId, User $sender, ?Persona $actor, string $body): ?Alert
    {
        return self::raise($recipientId, Alert::LETTER, $sender, $actor, null, self::trim($body));
    }

    public static function followed(Persona $followed, User $follower, ?Persona $actor): ?Alert
    {
        return self::raise($followed->user_id, Alert::FOLLOW, $follower, $actor, null, '@'.$followed->handle);
    }

    /**
     * Someone paid you.
     *
     * Deliberately *not* folded into a count like marks are: every
     * contribution is a person choosing to give a writer money, and collapsing
     * three of them into "3 supporters" throws away the one thing the writer
     * most wants to read — what each of them said.
     */
    public static function supported(int $writerId, User $supporter, ?Persona $actor, string $amount, ?string $note): ?Alert
    {
        if ($writerId === $supporter->id) {
            return null;
        }

        return Alert::create([
            'user_id' => $writerId,
            'type' => Alert::SUPPORT,
            'actor_persona_id' => $actor?->id,
            'preview' => trim($amount.($note ? ' — '.self::trim($note) : '')),
        ]);
    }

    /**
     * Create or fold in one notice.
     *
     * Returns null when nothing was raised — self-directed activity — so a
     * caller can tell the difference between "notified" and "deliberately not".
     */
    private static function raise(
        ?int $recipientId,
        string $type,
        User $actor,
        ?Persona $actorPersona,
        ?Post $post,
        ?string $preview,
    ): ?Alert {
        if ($recipientId === null || $recipientId === $actor->id) {
            return null;
        }

        $existing = Alert::query()
            ->where('user_id', $recipientId)
            ->where('type', $type)
            ->where('post_id', $post?->id)
            ->whereNull('read_at')
            ->where('created_at', '>=', now()->subHours(self::AGGREGATION_WINDOW_HOURS))
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->increment('count');

            // The preview follows the most recent event, and `updated_at`
            // moves with it, so the list sorts by "last thing that happened"
            // without losing when the run started.
            $existing->forceFill([
                'preview' => $preview ?? $existing->preview,
                'actor_persona_id' => $actorPersona?->id ?? $existing->actor_persona_id,
            ])->save();

            return $existing;
        }

        return Alert::create([
            'user_id' => $recipientId,
            'type' => $type,
            'actor_persona_id' => $actorPersona?->id,
            'post_id' => $post?->id,
            'preview' => $preview,
        ]);
    }

    /** Plain-text, short, and safe to render as text. */
    private static function trim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $plain = trim(HtmlSanitizer::plain($value));

        return $plain === '' ? null : mb_substr($plain, 0, 180);
    }
}
