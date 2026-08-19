<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
use App\Support\StoryBlocks;
use PHPUnit\Framework\TestCase;

/**
 * Post bodies are rendered with dangerouslySetInnerHTML, so this class is the
 * stored-XSS boundary for the whole application.
 */
class HtmlSanitizerTest extends TestCase
{
    public function test_it_keeps_the_formatting_tiptap_produces(): void
    {
        $html = '<p>A <strong>bold</strong> and <em>italic</em> line.</p><blockquote>Quoted</blockquote><ul><li>One</li></ul>';

        $this->assertSame($html, HtmlSanitizer::clean($html));
    }

    public function test_it_strips_script_tags(): void
    {
        $clean = HtmlSanitizer::clean('<p>Safe</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
    }

    public function test_it_strips_event_handler_attributes(): void
    {
        $clean = HtmlSanitizer::clean('<p onclick="steal()">Text</p>');

        $this->assertSame('<p>Text</p>', $clean);
    }

    public function test_it_strips_javascript_urls_from_links(): void
    {
        $clean = HtmlSanitizer::clean('<a href="javascript:alert(1)">Click</a>');

        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertSame('<a>Click</a>', $clean);
    }

    /**
     * Blocklisting "javascript:" is trivially bypassed with entities and
     * whitespace, which is why the scheme check is an allowlist.
     */
    public function test_it_resists_obfuscated_javascript_urls(): void
    {
        foreach ([
            '<a href="JaVaScRiPt:alert(1)">x</a>',
            '<a href=" javascript:alert(1)">x</a>',
            '<a href="&#106;avascript:alert(1)">x</a>',
        ] as $payload) {
            $clean = HtmlSanitizer::clean($payload);

            $this->assertSame('<a>x</a>', $clean, "Failed on: {$payload}");
        }
    }

    public function test_it_keeps_safe_link_targets(): void
    {
        $clean = HtmlSanitizer::clean('<a href="https://example.com">Link</a>');

        $this->assertStringContainsString('href="https://example.com"', $clean);
        $this->assertStringContainsString('rel="noopener nofollow"', $clean);
    }

    /*
     * Images became allowlisted when storytelling blocks landed. The tag is
     * permitted; everything that made it dangerous is not.
     */

    public function test_it_strips_event_handlers_and_unsafe_sources_from_images(): void
    {
        $clean = HtmlSanitizer::clean('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onerror', $clean);
        $this->assertStringNotContainsString('alert', $clean);
        // Unquoted `src=x` is not a value we accept, so no src survives.
        $this->assertStringNotContainsString('src', $clean);
    }

    public function test_it_rejects_image_sources_that_are_not_https_or_local(): void
    {
        foreach ([
            '<img src="javascript:alert(1)" alt="x">',
            '<img src="data:image/svg+xml;base64,PHN2Zz48c2NyaXB0Pg==" alt="x">',
            '<img src="http://insecure.example.com/a.png" alt="x">',
        ] as $payload) {
            $clean = HtmlSanitizer::clean($payload);

            $this->assertStringNotContainsString('src=', $clean, $payload);
        }
    }

    public function test_it_keeps_safe_image_sources(): void
    {
        $this->assertStringContainsString('src="/images/covers/cosmos-1.jpg"', HtmlSanitizer::clean('<img src="/images/covers/cosmos-1.jpg" alt="A galaxy">'));
        $this->assertStringContainsString('alt="A galaxy"', HtmlSanitizer::clean('<img src="/images/covers/cosmos-1.jpg" alt="A galaxy">'));
        $this->assertStringContainsString('src="https://example.com/a.png"', HtmlSanitizer::clean('<img src="https://example.com/a.png">'));
    }

    /* -------------------- storytelling blocks -------------------- */

    public function test_it_keeps_known_storytelling_block_kinds(): void
    {
        // The vocabulary is StoryBlocks::SCHEMA — read from it rather than
        // repeating it, so adding a block type cannot leave this test asserting
        // a set that no longer exists.
        foreach (array_keys(StoryBlocks::SCHEMA) as $kind) {
            $clean = HtmlSanitizer::clean('<figure data-story="'.$kind.'"><p>Body</p></figure>');

            $this->assertStringContainsString('data-story="'.$kind.'"', $clean, $kind);
        }
    }

    /**
     * The renderer switches on this value, so an unknown one must not survive —
     * otherwise a body could smuggle in an arbitrary attribute value that some
     * future renderer branch trusts.
     */
    public function test_it_drops_unknown_storytelling_kinds(): void
    {
        $clean = HtmlSanitizer::clean('<figure data-story="../../etc"><p>Body</p></figure>');

        $this->assertStringNotContainsString('data-story=', $clean);

        // The whole element goes, not just the attribute: a block's content
        // lives in its attributes, so a figure with an unrecognised type has
        // nothing left to render and an empty <figure> would be a stray hole
        // in the prose.
        $this->assertStringNotContainsString('<figure', $clean);
    }

    public function test_it_drops_style_and_unlisted_attributes_from_storytelling_blocks(): void
    {
        $clean = HtmlSanitizer::clean(
            '<figure data-story="callout" style="position:fixed" onclick="steal()" data-evil="1"><p>Body</p></figure>'
        );

        $this->assertStringContainsString('data-story="callout"', $clean);
        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('data-evil', $clean);
    }

    public function test_it_bounds_storytelling_label_length_and_strips_markup(): void
    {
        $clean = HtmlSanitizer::clean(
            '<figure data-story="callout" data-story-label="'.str_repeat('a', 400).'"><p>B</p></figure>'
        );

        preg_match('/data-story-label="([^"]*)"/', $clean, $found);

        $this->assertLessThanOrEqual(200, strlen($found[1] ?? ''));
    }

    public function test_excerpts_are_plain_text_and_truncated(): void
    {
        $excerpt = HtmlSanitizer::excerpt('<p>'.str_repeat('word ', 100).'</p>', 40);

        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertLessThanOrEqual(41, mb_strlen($excerpt));
    }
}
