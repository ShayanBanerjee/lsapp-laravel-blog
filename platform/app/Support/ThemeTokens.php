<?php

namespace App\Support;

/**
 * Validation and assembly for author-made themes.
 *
 * Two things make this more than a form:
 *
 * 1. **These values become CSS.** Every token is written into a style
 *    attribute as a custom property, so free text here is a style-injection
 *    sink. Colours are matched against a hex pattern and nothing else is
 *    accepted — in particular `halo`, which is a full gradient expression, is
 *    never taken from the client at all. The author picks a shape by name and
 *    the gradient is composed here, from values that have already been
 *    validated. There is no path from user input to arbitrary CSS.
 *
 * 2. **Unreadable is a failure state, not a style choice.** A theme is
 *    rejected when body text does not clear WCAG AA against the surfaces it is
 *    actually drawn on. See Contrast for why that check cannot live in the
 *    browser.
 *
 * Anything the author does not set is inherited from the base universe, so a
 * stored theme is always a complete token set.
 */
class ThemeTokens
{
    /** Colour tokens an author may set. Everything else is derived or fixed. */
    public const COLOR_KEYS = [
        'bg', 'bgDeep', 'surface1', 'surface2', 'border',
        'text', 'textMuted', 'accent', 'accentFg',
        'metalBase', 'metalSheen', 'metalEdge', 'metalShadow', 'glow',
    ];

    /**
     * Halo shapes. The author chooses a key; the gradient is built here.
     *
     * @var array<string, array{label: string, template: string}>
     */
    public const HALO_SHAPES = [
        'dome' => ['label' => 'Dome', 'template' => 'radial-gradient(ellipse 120%% 70%% at 50%% -10%%, %s, transparent 65%%)'],
        'corner' => ['label' => 'Corner', 'template' => 'radial-gradient(ellipse 90%% 60%% at 12%% 0%%, %s, transparent 70%%)'],
        'spine' => ['label' => 'Spine', 'template' => 'linear-gradient(180deg, %s, transparent 55%%)'],
        'wash' => ['label' => 'Wash', 'template' => 'radial-gradient(ellipse 150%% 100%% at 50%% 40%%, %s, transparent 75%%)'],
        'none' => ['label' => 'None', 'template' => 'none'],
    ];

    /** Pairs that must clear WCAG AA, as [foreground, background, minimum, label]. */
    private const CONTRAST_RULES = [
        ['text', 'bg', Contrast::AA_TEXT, 'Body text on the page background'],
        ['text', 'surface1', Contrast::AA_TEXT, 'Body text on panels'],
        ['textMuted', 'bg', Contrast::AA_TEXT, 'Muted text on the page background'],
        ['textMuted', 'surface1', Contrast::AA_TEXT, 'Muted text on panels'],
        ['accentFg', 'accent', Contrast::AA_TEXT, 'Button labels on the accent colour'],
        ['border', 'bg', 1.2, 'Borders against the page background'],
    ];

    /**
     * Merge author input over a base universe's tokens, dropping anything not
     * recognised and rebuilding every derived value from scratch.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    public static function compose(array $input, array $base): array
    {
        $tokens = $base;

        foreach (self::COLOR_KEYS as $key) {
            $value = $input[$key] ?? null;

            if (is_string($value) && self::isHex($value)) {
                $tokens[$key] = strtolower($value);
            }
        }

        // Grain angle is a bare number here and only becomes a CSS `deg` value
        // after being clamped, so no unit string ever crosses the boundary.
        $angle = (int) round((float) ($input['grainAngle'] ?? 115));
        $tokens['grainAngle'] = (($angle % 360) + 360) % 360 .'deg';

        $tokens['scheme'] = ($input['scheme'] ?? null) === 'light' ? 'light' : 'dark';

        // Derived, never accepted: the accent wash behind selected controls.
        $tokens['accentSoft'] = Contrast::withAlpha($tokens['accent'], 0.18);

        $tokens['halo'] = self::halo(
            $tokens['glow'],
            is_string($input['haloShape'] ?? null) ? $input['haloShape'] : 'dome',
            (float) ($input['haloStrength'] ?? 0.2),
        );

        return $tokens;
    }

    /**
     * Compose the halo gradient from a validated colour and a named shape.
     *
     * The template is a constant and the only interpolated value is a hex
     * string this class produced — that is the whole reason the author cannot
     * hand us a gradient directly.
     */
    public static function halo(string $glow, string $shape, float $strength): string
    {
        $template = self::HALO_SHAPES[$shape]['template'] ?? self::HALO_SHAPES['dome']['template'];

        if ($template === 'none') {
            return 'none';
        }

        return sprintf($template, Contrast::withAlpha($glow, min(0.6, max(0.0, $strength))));
    }

    /**
     * Every contrast rule this token set fails, as readable sentences.
     *
     * Returns an empty array when the theme is fit to publish.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<int, string>
     */
    public static function contrastFailures(array $tokens): array
    {
        $failures = [];

        foreach (self::CONTRAST_RULES as [$foreground, $background, $minimum, $label]) {
            $ratio = Contrast::ratio((string) $tokens[$foreground], (string) $tokens[$background]);

            if ($ratio < $minimum) {
                $failures[] = sprintf('%s is %s:1 — needs at least %s:1.', $label, number_format($ratio, 2), number_format($minimum, 1));
            }
        }

        return $failures;
    }

    /**
     * Measured contrast for every rule, pass or fail, so the editor can show
     * the numbers rather than only complaining when they are wrong.
     *
     * @param  array<string, mixed>  $tokens
     * @return array<int, array<string, mixed>>
     */
    public static function contrastReport(array $tokens): array
    {
        return array_map(function (array $rule) use ($tokens) {
            [$foreground, $background, $minimum, $label] = $rule;
            $ratio = Contrast::ratio((string) $tokens[$foreground], (string) $tokens[$background]);

            return [
                'label' => $label,
                'ratio' => $ratio,
                'minimum' => $minimum,
                'passes' => $ratio >= $minimum,
            ];
        }, self::CONTRAST_RULES);
    }

    /** Which halo shape a stored token set was built with, for round-tripping the editor. */
    public static function haloShapeOf(array $tokens): string
    {
        $halo = (string) ($tokens['halo'] ?? '');

        if ($halo === 'none') {
            return 'none';
        }

        foreach (self::HALO_SHAPES as $key => $shape) {
            if ($shape['template'] === 'none') {
                continue;
            }

            // Compare the fixed part of the template, which is everything up to
            // the interpolated colour.
            $prefix = substr($shape['template'], 0, strpos($shape['template'], '%s') ?: 0);

            if (str_starts_with($halo, str_replace('%%', '%', $prefix))) {
                return $key;
            }
        }

        return 'dome';
    }

    private static function isHex(string $value): bool
    {
        return (bool) preg_match('/^#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', trim($value));
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function haloOptions(): array
    {
        return array_map(
            fn (string $key) => ['value' => $key, 'label' => self::HALO_SHAPES[$key]['label']],
            array_keys(self::HALO_SHAPES),
        );
    }
}
