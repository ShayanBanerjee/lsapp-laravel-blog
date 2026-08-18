<?php

namespace App\Support;

/**
 * The reader's typography settings.
 *
 * Every value is validated against an allowlist here rather than trusted from
 * the client, because these end up as CSS custom properties: an unchecked font
 * name would be a style-injection sink.
 */
class ReadingPreferences
{
    /**
     * Typeface options. Each is verified available on the font host.
     *
     * Atkinson Hyperlegible is included deliberately — it was designed by the
     * Braille Institute to maximise character distinction for low-vision
     * readers, and it is the single most useful option here for accessibility.
     *
     * @var array<string, array<string, string>>
     */
    public const FONTS = [
        'literata' => ['label' => 'Literata', 'stack' => "'Literata', Georgia, serif", 'note' => 'Designed for long-form reading on screens'],
        'source-serif-4' => ['label' => 'Source Serif', 'stack' => "'Source Serif 4', Georgia, serif", 'note' => 'Clean, high-legibility serif'],
        'newsreader' => ['label' => 'Newsreader', 'stack' => "'Newsreader', Georgia, serif", 'note' => 'Warm and editorial'],
        'lora' => ['label' => 'Lora', 'stack' => "'Lora', Georgia, serif", 'note' => 'Contemporary, slightly calligraphic'],
        'inter' => ['label' => 'Inter', 'stack' => "'Inter', ui-sans-serif, system-ui, sans-serif", 'note' => 'Sans-serif, if you prefer no serifs'],
        'atkinson-hyperlegible' => ['label' => 'Atkinson Hyperlegible', 'stack' => "'Atkinson Hyperlegible', ui-sans-serif, sans-serif", 'note' => 'Built for low vision — highly distinct letterforms'],
    ];

    public const DEFAULTS = [
        'font' => 'literata',
        'size' => 19,       // px
        'leading' => 1.75,  // unitless line-height
        'measure' => 68,    // characters per line
    ];

    /**
     * Coerce whatever is stored (or submitted) into a safe, complete set.
     *
     * @param  array<string, mixed>|null  $prefs
     * @return array<string, mixed>
     */
    public static function normalize(?array $prefs): array
    {
        $prefs ??= [];

        $font = is_string($prefs['font'] ?? null) && isset(self::FONTS[$prefs['font']])
            ? $prefs['font']
            : self::DEFAULTS['font'];

        return [
            'font' => $font,
            // Clamped rather than rejected: an out-of-range value from an older
            // client should still produce a readable page.
            'size' => (int) min(26, max(15, (int) ($prefs['size'] ?? self::DEFAULTS['size']))),
            'leading' => round(min(2.2, max(1.35, (float) ($prefs['leading'] ?? self::DEFAULTS['leading']))), 2),
            'measure' => (int) min(88, max(52, (int) ($prefs['measure'] ?? self::DEFAULTS['measure']))),
        ];
    }

    /** The resolved CSS font stack for a normalized preference set. */
    public static function stack(string $font): string
    {
        return self::FONTS[$font]['stack'] ?? self::FONTS[self::DEFAULTS['font']]['stack'];
    }

    /** @return array<int, array<string, string>> */
    public static function options(): array
    {
        return collect(self::FONTS)
            ->map(fn (array $meta, string $key) => ['value' => $key, 'label' => $meta['label'], 'note' => $meta['note']])
            ->values()
            ->all();
    }
}
