<?php

namespace App\Support;

/**
 * Scrollytelling blocks: pinned images, step-through sequences, before/after
 * comparisons and data callouts.
 *
 * The shape of this is the whole design. A block is **stored** in a compact
 * form — one `<figure>` carrying validated attributes and no children — and
 * **expanded** into full semantic markup only at render time, by this class.
 *
 * That split is what makes rich structured blocks safe in a body that is
 * rendered with dangerouslySetInnerHTML:
 *
 * - Storing markup would mean sanitising arbitrary nested structure on the way
 *   in, which is the hard version of the problem and the one people get wrong.
 *   Here the only thing ever validated is a fixed set of scalar attributes.
 * - The HTML a reader receives is generated here, from escaped values, so it
 *   is safe by construction rather than by inspection.
 *
 * Expansion is deterministic, which also matters for marks: highlights are
 * anchored by block index into the rendered body, so the same stored body must
 * always expand to the same structure. Adding a wrapper element to an existing
 * block type would move every mark after it — change the renderer only by
 * adding new types, not by restructuring old ones.
 */
class StoryBlocks
{
    /** Steps are stored in one attribute, separated by this. */
    public const STEP_SEPARATOR = '||';

    public const MAX_STEPS = 12;

    /**
     * Attributes each block type may carry. Anything else is dropped.
     *
     * `image` values go through imageUrl(); everything else is treated as text
     * and escaped on output.
     *
     * @var array<string, array<string, string>>
     */
    public const SCHEMA = [
        'pinned' => [
            'src' => 'image',
            'alt' => 'text',
            'caption' => 'text',
            'steps' => 'steps',
        ],
        'sequence' => [
            'title' => 'text',
            'steps' => 'steps',
        ],
        'compare' => [
            'before' => 'image',
            'after' => 'image',
            'before-label' => 'text',
            'after-label' => 'text',
            'caption' => 'text',
        ],
        'callout' => [
            'value' => 'text',
            'label' => 'text',
            'note' => 'text',
        ],
    ];

    /**
     * Validate one stored block's attributes.
     *
     * Called from HtmlSanitizer while it walks the body. Returns the reassembled
     * opening tag, or null when the block is not one we know — in which case the
     * caller drops it.
     *
     * @param  string  $attributes  the raw attribute string from the source tag
     */
    public static function sanitizeFigure(string $attributes): ?string
    {
        $type = self::attribute($attributes, 'data-story');

        if ($type === null || ! isset(self::SCHEMA[$type])) {
            return null;
        }

        $out = '<figure data-story="'.htmlspecialchars($type, ENT_QUOTES).'"';

        foreach (self::SCHEMA[$type] as $name => $kind) {
            $value = self::attribute($attributes, 'data-'.$name);

            if ($value === null || $value === '') {
                continue;
            }

            $clean = match ($kind) {
                'image' => self::imageUrl($value),
                'steps' => self::steps($value),
                default => self::text($value),
            };

            if ($clean === null || $clean === '') {
                continue;
            }

            $out .= ' data-'.$name.'="'.htmlspecialchars($clean, ENT_QUOTES).'"';
        }

        return $out.'>';
    }

    /**
     * Expand every stored block in a body into rendered markup.
     *
     * Everything interpolated here is either a constant or a value that came
     * back from sanitizeFigure and is escaped again on the way out.
     */
    public static function expand(string $html): string
    {
        return preg_replace_callback(
            '#<figure\b([^>]*\bdata-story\b[^>]*)>\s*</figure>#i',
            static function (array $match): string {
                $type = self::attribute($match[1], 'data-story');

                return match ($type) {
                    'pinned' => self::renderPinned($match[1]),
                    'sequence' => self::renderSequence($match[1]),
                    'compare' => self::renderCompare($match[1]),
                    'callout' => self::renderCallout($match[1]),
                    // An unknown type reaching here means the sanitizer let
                    // something through; render nothing rather than guess.
                    default => '',
                };
            },
            $html
        ) ?? $html;
    }

    /** True when a body contains any block, so pages can skip the hydration work. */
    public static function present(string $html): bool
    {
        return str_contains($html, 'data-story=');
    }

    private static function renderPinned(string $attributes): string
    {
        $src = self::attr($attributes, 'src');
        $alt = self::attr($attributes, 'alt');
        $caption = self::attr($attributes, 'caption');
        $steps = self::stepList($attributes);

        if ($src === '') {
            return '';
        }

        $stepsHtml = '';
        foreach ($steps as $index => $step) {
            $stepsHtml .= '<div class="u-story-step" data-step="'.$index.'"><p>'.$step.'</p></div>';
        }

        // The image is sticky in CSS rather than pinned by scroll maths — a
        // scroll listener repositioning an element every frame is exactly the
        // thing that stutters on a phone.
        return '<figure class="u-story u-story-pinned" data-story="pinned">'
            .'<div class="u-story-media"><img src="'.$src.'" alt="'.$alt.'" loading="lazy" /></div>'
            .'<div class="u-story-steps">'.$stepsHtml.'</div>'
            .($caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '')
            .'</figure>';
    }

    private static function renderSequence(string $attributes): string
    {
        $title = self::attr($attributes, 'title');
        $steps = self::stepList($attributes);

        if ($steps === []) {
            return '';
        }

        $items = '';
        foreach ($steps as $index => $step) {
            $items .= '<li class="u-story-step" data-step="'.$index.'">'
                .'<span class="u-story-num" aria-hidden="true">'.($index + 1).'</span>'
                .'<span class="u-story-body">'.$step.'</span>'
                .'</li>';
        }

        return '<figure class="u-story u-story-sequence" data-story="sequence">'
            .($title !== '' ? '<figcaption class="u-story-title">'.$title.'</figcaption>' : '')
            .'<ol>'.$items.'</ol>'
            .'</figure>';
    }

    private static function renderCompare(string $attributes): string
    {
        $before = self::attr($attributes, 'before');
        $after = self::attr($attributes, 'after');

        if ($before === '' || $after === '') {
            return '';
        }

        $beforeLabel = self::attr($attributes, 'before-label') ?: 'Before';
        $afterLabel = self::attr($attributes, 'after-label') ?: 'After';
        $caption = self::attr($attributes, 'caption');

        /*
         * The slider is a real <input type="range">, not a div with pointer
         * handlers. That gives keyboard control, a screen-reader-announced
         * value and touch behaviour for free — all of which a custom drag
         * implementation would have to rebuild and usually does not.
         */
        return '<figure class="u-story u-story-compare" data-story="compare">'
            .'<div class="u-story-compare-frame">'
            .'<img class="u-story-compare-after" src="'.$after.'" alt="'.$afterLabel.'" loading="lazy" />'
            .'<div class="u-story-compare-clip"><img src="'.$before.'" alt="'.$beforeLabel.'" loading="lazy" /></div>'
            .'<span class="u-story-compare-handle" aria-hidden="true"></span>'
            .'<input class="u-story-compare-range" type="range" min="0" max="100" value="50" '
            .'aria-label="Reveal '.$beforeLabel.' or '.$afterLabel.'" />'
            .'<span class="u-story-compare-label u-story-compare-label-before">'.$beforeLabel.'</span>'
            .'<span class="u-story-compare-label u-story-compare-label-after">'.$afterLabel.'</span>'
            .'</div>'
            .($caption !== '' ? '<figcaption>'.$caption.'</figcaption>' : '')
            .'</figure>';
    }

    private static function renderCallout(string $attributes): string
    {
        $value = self::attr($attributes, 'value');
        $label = self::attr($attributes, 'label');
        $note = self::attr($attributes, 'note');

        if ($value === '' && $label === '') {
            return '';
        }

        return '<figure class="u-story u-story-callout" data-story="callout">'
            .'<span class="u-story-value">'.$value.'</span>'
            .($label !== '' ? '<span class="u-story-label">'.$label.'</span>' : '')
            .($note !== '' ? '<figcaption>'.$note.'</figcaption>' : '')
            .'</figure>';
    }

    /** An already-sanitized attribute, re-escaped for output. */
    private static function attr(string $attributes, string $name): string
    {
        $value = self::attribute($attributes, 'data-'.$name);

        return $value === null ? '' : htmlspecialchars(html_entity_decode($value), ENT_QUOTES);
    }

    /** @return array<int, string> escaped step strings */
    private static function stepList(string $attributes): array
    {
        $raw = self::attribute($attributes, 'data-steps');

        if ($raw === null || $raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $step) => htmlspecialchars(trim(html_entity_decode($step)), ENT_QUOTES),
            explode(self::STEP_SEPARATOR, html_entity_decode($raw)),
        ), static fn (string $step) => $step !== ''));
    }

    private static function attribute(string $attributes, string $name): ?string
    {
        if (! preg_match('/\b'.preg_quote($name, '/').'\s*=\s*("|\')(.*?)\1/is', $attributes, $match)) {
            return null;
        }

        return $match[2];
    }

    /**
     * Image sources are same-origin paths or https URLs, nothing else.
     *
     * `data:` is excluded deliberately: it is the one scheme that can smuggle
     * SVG, and an SVG in an <img> is a scripting context in some browsers.
     */
    private static function imageUrl(string $value): ?string
    {
        $url = trim(html_entity_decode($value));

        if (preg_match('#^/(?!/)[\w\-./%]*$#', $url)) {
            return $url;
        }

        if (preg_match('#^https://[\w\-.]+(:\d+)?(/[\w\-./%?=&+,~]*)?$#i', $url)) {
            return $url;
        }

        return null;
    }

    private static function text(string $value): string
    {
        return mb_substr(trim(HtmlSanitizer::plain(html_entity_decode($value), 400)), 0, 400);
    }

    private static function steps(string $value): string
    {
        $steps = array_slice(
            array_values(array_filter(array_map(
                static fn (string $step) => self::text($step),
                explode(self::STEP_SEPARATOR, html_entity_decode($value)),
            ), static fn (string $step) => $step !== '')),
            0,
            self::MAX_STEPS,
        );

        return implode(self::STEP_SEPARATOR, $steps);
    }
}
