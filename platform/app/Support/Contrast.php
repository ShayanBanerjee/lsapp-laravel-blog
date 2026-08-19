<?php

namespace App\Support;

/**
 * WCAG relative-luminance contrast, used to keep author-made themes readable.
 *
 * This is not decoration. A theme editor without a contrast floor produces
 * grey-on-grey articles that a sighted user squints at and a low-vision user
 * simply cannot read — and the author, looking at their own screen at their own
 * brightness, never sees the problem. The check belongs on the server for the
 * same reason theme tokens are gated there: it must not be skippable.
 */
class Contrast
{
    /** WCAG 2.1 AA for body text. */
    public const AA_TEXT = 4.5;

    /** WCAG 2.1 AA for large text and UI component boundaries. */
    public const AA_LARGE = 3.0;

    /**
     * Contrast ratio between two colours, 1.0 (identical) to 21.0 (black/white).
     *
     * Alpha is ignored rather than composited: a token carrying transparency is
     * always drawn over an unknown stack of surfaces, so any single answer
     * would be a guess. The tokens this is applied to are all opaque.
     */
    public static function ratio(string $foreground, string $background): float
    {
        $lighter = max(self::luminance($foreground), self::luminance($background));
        $darker = min(self::luminance($foreground), self::luminance($background));

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    public static function passes(string $foreground, string $background, float $minimum = self::AA_TEXT): bool
    {
        return self::ratio($foreground, $background) >= $minimum;
    }

    /** Relative luminance per WCAG 2.1, from an #rgb / #rrggbb / #rrggbbaa string. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        $linear = array_map(static function (float $channel): float {
            $channel /= 255;

            return $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    /**
     * Split a hex colour into 0-255 channels.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    public static function channels(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');

        // Shorthand doubles each nibble: #abc is #aabbcc.
        if (strlen($hex) === 3 || strlen($hex) === 4) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        $hex = substr($hex, 0, 6);

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0.0, 0.0, 0.0];
        }

        return [
            (float) hexdec(substr($hex, 0, 2)),
            (float) hexdec(substr($hex, 2, 2)),
            (float) hexdec(substr($hex, 4, 2)),
        ];
    }

    /** Same colour with an alpha channel appended, for the *-soft tokens. */
    public static function withAlpha(string $hex, float $alpha): string
    {
        [$r, $g, $b] = self::channels($hex);

        return sprintf('#%02x%02x%02x%02x', (int) $r, (int) $g, (int) $b, (int) round(max(0, min(1, $alpha)) * 255));
    }
}
