<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use App\Support\StoryBlocks;
use PHPUnit\Framework\TestCase;

/**
 * The security property under test is the design itself: a stored block holds
 * only scalar attributes, and every piece of rendered markup is generated from
 * escaped values rather than accepted from the client.
 */
class StoryBlocksTest extends TestCase
{
    public function test_a_valid_block_survives_sanitisation(): void
    {
        $html = HtmlSanitizer::clean(
            '<p>Before.</p><figure data-story="callout" data-value="87%" data-label="of drafts are never published"></figure><p>After.</p>'
        );

        $this->assertStringContainsString('data-story="callout"', $html);
        $this->assertStringContainsString('data-value="87%"', $html);
        $this->assertStringContainsString('<p>Before.</p>', $html);
    }

    public function test_an_unknown_block_type_is_dropped(): void
    {
        $html = HtmlSanitizer::clean('<figure data-story="whatever" data-value="x"></figure>');

        $this->assertStringNotContainsString('data-story', $html);
    }

    public function test_a_figure_without_a_story_type_is_dropped(): void
    {
        $html = HtmlSanitizer::clean('<figure class="sneaky" onclick="alert(1)"></figure>');

        $this->assertStringNotContainsString('figure', $html);
        $this->assertStringNotContainsString('onclick', $html);
    }

    public function test_attributes_outside_the_schema_are_dropped(): void
    {
        $html = HtmlSanitizer::clean(
            '<figure data-story="callout" data-value="9" onload="alert(1)" style="position:fixed" data-evil="x"></figure>'
        );

        $this->assertStringContainsString('data-value="9"', $html);
        $this->assertStringNotContainsString('onload', $html);
        $this->assertStringNotContainsString('style', $html);
        $this->assertStringNotContainsString('data-evil', $html);
    }

    public function test_markup_inside_an_attribute_is_reduced_to_text(): void
    {
        $html = HtmlSanitizer::clean(
            '<figure data-story="callout" data-value="9" data-label="&lt;script&gt;alert(1)&lt;/script&gt;"></figure>'
        );

        $rendered = StoryBlocks::expand($html);

        $this->assertStringNotContainsString('<script', $rendered);
        // plain() removes script elements together with their contents, so the
        // payload does not survive as visible text either.
        $this->assertStringNotContainsString('alert(1)', $rendered);
    }

    public function test_image_sources_are_restricted_to_same_origin_paths_and_https(): void
    {
        foreach (['javascript:alert(1)', 'data:image/svg+xml;base64,PHN2Zz4=', 'http://insecure.example/x.png', '//evil.example/x.png'] as $bad) {
            $html = HtmlSanitizer::clean(
                '<figure data-story="pinned" data-src="'.htmlspecialchars($bad, ENT_QUOTES).'" data-steps="One"></figure>'
            );

            $this->assertStringNotContainsString('data-src', $html, "{$bad} should not be accepted as an image source");
        }

        $ok = HtmlSanitizer::clean('<figure data-story="pinned" data-src="/images/covers/a.jpg" data-steps="One"></figure>');
        $this->assertStringContainsString('data-src="/images/covers/a.jpg"', $ok);

        $https = HtmlSanitizer::clean('<figure data-story="pinned" data-src="https://images.example/a.jpg" data-steps="One"></figure>');
        $this->assertStringContainsString('https://images.example/a.jpg', $https);
    }

    public function test_a_quote_in_an_attribute_cannot_break_out_of_the_tag(): void
    {
        $html = HtmlSanitizer::clean(
            '<figure data-story="callout" data-value="1" data-label="a&quot; onmouseover=&quot;alert(1)"></figure>'
        );

        $rendered = StoryBlocks::expand($html);

        // The payload survives as *text* — that is fine and correct. What must
        // not happen is it becoming an attribute, so the quote is what is
        // asserted on, not the word.
        $this->assertStringNotContainsString('" onmouseover=', $rendered);
        $this->assertStringContainsString('&quot; onmouseover=&quot;', $rendered);
    }

    public function test_steps_are_split_capped_and_rendered_in_order(): void
    {
        $steps = implode(StoryBlocks::STEP_SEPARATOR, array_map(fn (int $i) => "Step {$i}", range(1, 20)));
        $html = HtmlSanitizer::clean('<figure data-story="sequence" data-steps="'.$steps.'"></figure>');

        $rendered = StoryBlocks::expand($html);

        $this->assertStringContainsString('Step 1', $rendered);
        $this->assertStringContainsString('Step '.StoryBlocks::MAX_STEPS, $rendered);
        $this->assertStringNotContainsString('Step '.(StoryBlocks::MAX_STEPS + 1), $rendered);
    }

    public function test_expansion_produces_the_expected_structure(): void
    {
        $stored = HtmlSanitizer::clean(
            '<figure data-story="compare" data-before="/images/a.jpg" data-after="/images/b.jpg" data-before-label="1994" data-after-label="2024"></figure>'
        );

        $rendered = StoryBlocks::expand($stored);

        $this->assertStringContainsString('u-story-compare', $rendered);
        // A real range input, so keyboard and screen-reader users get the
        // control for free rather than through a bespoke drag handler.
        $this->assertStringContainsString('type="range"', $rendered);
        $this->assertStringContainsString('1994', $rendered);
    }

    public function test_expansion_is_deterministic(): void
    {
        // Marks are anchored by block index into the rendered body, so the same
        // stored body must always expand to the same structure.
        $stored = HtmlSanitizer::clean('<p>One.</p><figure data-story="callout" data-value="3"></figure><p>Two.</p>');

        $this->assertSame(StoryBlocks::expand($stored), StoryBlocks::expand($stored));
    }

    public function test_an_incomplete_block_renders_nothing_rather_than_a_broken_frame(): void
    {
        $stored = HtmlSanitizer::clean('<figure data-story="compare" data-before="/images/a.jpg"></figure>');

        $this->assertStringNotContainsString('u-story-compare', StoryBlocks::expand($stored));
    }

    public function test_a_forged_placeholder_cannot_smuggle_markup(): void
    {
        // The reinsert step is the one place arbitrary markup is written back
        // into an already-sanitized body, so the token that triggers it must
        // not be guessable from input written beforehand.
        $body = HtmlSanitizer::clean(
            '<p>@@story-0-0@@ and @@story-0@@</p>'
            .'<figure data-story="callout" data-value="1"></figure>'
        );

        $rendered = StoryBlocks::expand($body);

        // Exactly one block, in the position the real figure occupied.
        $this->assertSame(1, substr_count($rendered, 'u-story-callout'));
        $this->assertStringContainsString('@@story-0-0@@', $body);
    }

    public function test_bodies_without_blocks_are_untouched(): void
    {
        $body = '<p>Just prose.</p><blockquote><p>And a quote.</p></blockquote>';

        $this->assertSame($body, StoryBlocks::expand($body));
        $this->assertFalse(StoryBlocks::present($body));
    }
}
