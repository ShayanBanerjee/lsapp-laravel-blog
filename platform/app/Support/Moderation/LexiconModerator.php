<?php

namespace App\Support\Moderation;

/**
 * The default checker: a narrow lexicon, deliberately.
 *
 * SCOPE — this looks for exactly two things:
 *   1. Slurs targeting a protected characteristic.
 *   2. Sexual harassment directed at a person.
 *
 * It does NOT look for: profanity used as emphasis, anger, insults about ideas,
 * political opinion, criticism of the author, or rudeness. Those are protected
 * speech on this platform and a filter that catches them is a broken filter.
 * "This argument is fucking stupid" must pass. It is a strong opinion about an
 * argument, which is exactly what a writing platform is for.
 *
 * WHY A LEXICON AND NOT A CLASSIFIER — a hosted classifier would be more
 * accurate on paraphrase, but it sends every reader's words to a third party,
 * costs money per comment, and fails open or closed on an outage. A narrow
 * lexicon is auditable: you can read the entire ruleset and know exactly what
 * it will and will not catch. Swap in a hosted model via the Moderator
 * interface if that trade is worth it — the call site does not change.
 */
class LexiconModerator implements Moderator
{
    /**
     * Slur patterns. Only unambiguous terms whose sole use is as an attack on a
     * protected characteristic. Deliberately short: every entry is a commitment
     * that the word has no legitimate use in a response.
     *
     * Stored as fragments and matched with word boundaries so "Scunthorpe"
     * style false positives cannot occur.
     *
     * @var array<int, string>
     */
    private const SLUR_PATTERNS = [
        'n[i1]gg[ae]r', 'f[a4]gg?[o0]t', 'tr[a4]nn[yi]e?', 'k[i1]ke',
        'sp[i1]c\b', 'ch[i1]nk\b', 'w[e3]tb[a4]ck', 'p[a4]k[i1]\b',
        'r[e3]t[a4]rd(ed)?\b', 'g[o0]{2}k\b',
    ];

    /**
     * Sexual harassment: sexual content *directed at a person*.
     *
     * The second-person targeting is what makes it harassment rather than a
     * discussion of sex, which is a legitimate subject for an essay. A piece
     * about sexuality must not be un-commentable.
     *
     * @var array<int, string>
     */
    private const HARASSMENT_PATTERNS = [
        '\b(send|show)\s+(me\s+)?(your\s+)?(nudes?|tits|dick|pussy)\b',
        '\bi\s+(want|wanna|will)\s+to?\s*(fuck|rape|molest)\s+(you|u|her|him)\b',
        '\b(rape|molest)\s+(you|u|her|him)\b',
        '\byou\s+(deserve|need)\s+(to\s+be\s+)?(raped|assaulted)\b',
        '\b(suck|lick)\s+my\s+(dick|cock|pussy)\b',
        '\bshut\s+up\s+and\s+(show|spread)\b',
    ];

    public function check(string $text): ModerationVerdict
    {
        $normalized = $this->normalize($text);

        foreach (self::HARASSMENT_PATTERNS as $pattern) {
            if (preg_match('/'.$pattern.'/iu', $normalized)) {
                // Directed sexual harassment is not a grey area.
                return ModerationVerdict::block('sexual_harassment', 0.95);
            }
        }

        foreach (self::SLUR_PATTERNS as $pattern) {
            if (preg_match('/\b'.$pattern.'\b/iu', $normalized)) {
                return ModerationVerdict::block('slur', 0.9);
            }
        }

        return ModerationVerdict::allow();
    }

    /**
     * Fold the cheap evasions — spaced letters, digit substitution, repeated
     * characters — without folding so aggressively that ordinary words start
     * colliding with the patterns.
     */
    private function normalize(string $text): string
    {
        $text = mb_strtolower($text);

        // "n i g g e r" and "n.i.g.g.e.r" -> collapse single-char separators
        $text = preg_replace('/(?<=\b\w)[\s._\-*]+(?=\w\b)/u', '', $text) ?? $text;

        // Collapse runs: "niiiigger" -> "niger" would over-fold, so cap at two.
        $text = preg_replace('/(.)\1{2,}/u', '$1$1', $text) ?? $text;

        return $text;
    }
}
