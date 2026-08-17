<?php

namespace Tests\Unit;

use App\Support\HtmlSanitizer;
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

    public function test_it_strips_image_tags_since_they_are_not_allowlisted(): void
    {
        $clean = HtmlSanitizer::clean('<img src=x onerror="alert(1)">');

        $this->assertSame('', $clean);
    }

    public function test_excerpts_are_plain_text_and_truncated(): void
    {
        $excerpt = HtmlSanitizer::excerpt('<p>'.str_repeat('word ', 100).'</p>', 40);

        $this->assertStringNotContainsString('<', $excerpt);
        $this->assertStringEndsWith('…', $excerpt);
        $this->assertLessThanOrEqual(41, mb_strlen($excerpt));
    }
}
