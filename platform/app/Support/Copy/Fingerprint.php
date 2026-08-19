<?php

namespace App\Support\Copy;

/**
 * Shingling and SimHash.
 *
 * All pure functions, so the interesting properties — that reordering
 * paragraphs barely moves a simhash, that a rewritten piece does not match,
 * that a copied passage does — are testable without a database.
 *
 * ### Normalisation
 *
 * Comparison happens on normalised text: tags stripped, case folded,
 * punctuation dropped, whitespace collapsed. Anything less and changing a
 * comma defeats detection entirely.
 *
 * ### Why k = 5
 *
 * A shingle is k consecutive words. Too small and every document shares
 * shingles ("one of the most"), producing noise; too large and a single
 * substituted word breaks the match. Five is the usual sweet spot for prose
 * and is what the tests are calibrated against.
 */
class Fingerprint
{
    public const SHINGLE_SIZE = 5;

    /**
     * Keep roughly one shingle in SAMPLE_RATE.
     *
     * Storing every shingle would mean a row per word, which is a table that
     * grows faster than the content it indexes. Sampling by hash value —
     * rather than by position — is what makes the sample *consistent*: the
     * same passage selects the same shingles in both documents, so an overlap
     * that exists in the full sets still shows up in the samples.
     */
    public const SAMPLE_RATE = 4;

    /** Below this many words, a piece is too short to fingerprint meaningfully. */
    public const MIN_WORDS = 40;

    public static function normalize(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        $text = mb_strtolower($text);

        // Keep letters, digits and spaces; everything else becomes a space so
        // hyphenation and punctuation cannot mask a match.
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /** @return array<int, string> */
    public static function words(string $html): array
    {
        $normalized = self::normalize($html);

        return $normalized === '' ? [] : explode(' ', $normalized);
    }

    /**
     * Overlapping word k-grams.
     *
     * @return array<int, string>
     */
    public static function shingles(string $html, int $size = self::SHINGLE_SIZE): array
    {
        $words = self::words($html);

        if (count($words) < $size) {
            return [];
        }

        $shingles = [];

        for ($i = 0; $i + $size <= count($words); $i++) {
            $shingles[] = implode(' ', array_slice($words, $i, $size));
        }

        return $shingles;
    }

    /**
     * 64-bit hash of one shingle, as 16 hex characters.
     *
     * xxhash-like speed is not the concern here; determinism across PHP
     * versions and platforms is, which is why this takes the first 8 bytes of
     * a named hash rather than using PHP's own hashing.
     */
    public static function hash(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
    }

    /**
     * The stored subset of a document's shingles.
     *
     * Selection is on the hash value, not the position, so two documents
     * sharing a passage select the same shingles from it — a positional sample
     * (every 4th) would drift out of step the moment one document had a
     * sentence inserted earlier.
     *
     * @param  array<int, string>  $shingles
     * @return array<int, string> distinct hashes
     */
    public static function sample(array $shingles, int $rate = self::SAMPLE_RATE): array
    {
        $kept = [];

        foreach ($shingles as $shingle) {
            $hash = self::hash($shingle);

            // Sample on the low bits of the hash — uniform, and independent of
            // where the shingle sits in the document.
            if (hexdec(substr($hash, -2)) % $rate === 0) {
                $kept[$hash] = true;
            }
        }

        return array_keys($kept);
    }

    /**
     * SimHash of a document: a 64-bit value where similar documents differ in
     * few bits.
     *
     * Each shingle votes on every bit position — +1 when its own hash has that
     * bit set, −1 otherwise — and the sign of the total becomes the output bit.
     * Changing a few shingles moves few votes, so the result moves by a few
     * bits rather than becoming unrelated, which is the entire point and the
     * thing a cryptographic hash cannot do.
     *
     * @param  array<int, string>  $shingles
     */
    public static function simhash(array $shingles): string
    {
        if ($shingles === []) {
            return str_repeat('0', 16);
        }

        $votes = array_fill(0, 64, 0);

        foreach ($shingles as $shingle) {
            $bits = self::bits(self::hash($shingle));

            for ($i = 0; $i < 64; $i++) {
                $votes[$i] += $bits[$i] === '1' ? 1 : -1;
            }
        }

        $result = '';

        for ($i = 0; $i < 64; $i++) {
            $result .= $votes[$i] > 0 ? '1' : '0';
        }

        return self::binaryToHex($result);
    }

    /** Number of differing bits between two 16-character hex hashes. */
    public static function hamming(string $a, string $b): int
    {
        $bitsA = self::bits($a);
        $bitsB = self::bits($b);
        $distance = 0;

        for ($i = 0; $i < 64; $i++) {
            if (($bitsA[$i] ?? '0') !== ($bitsB[$i] ?? '0')) {
                $distance++;
            }
        }

        return $distance;
    }

    /** How much of `$mine` also appears in `$theirs`, from 0 to 1. */
    public static function containment(int $shared, int $mineCount): float
    {
        return $mineCount === 0 ? 0.0 : round($shared / $mineCount, 4);
    }

    /** 64-character binary string for a 16-character hex value. */
    private static function bits(string $hex): string
    {
        $bits = '';

        foreach (str_split(str_pad(substr($hex, 0, 16), 16, '0', STR_PAD_LEFT)) as $nibble) {
            $bits .= str_pad(decbin((int) hexdec($nibble)), 4, '0', STR_PAD_LEFT);
        }

        return $bits;
    }

    private static function binaryToHex(string $bits): string
    {
        $hex = '';

        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        return $hex;
    }
}
