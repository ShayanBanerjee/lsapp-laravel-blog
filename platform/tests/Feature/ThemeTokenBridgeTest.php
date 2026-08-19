<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Guards the shadcn → universe token bridge.
 *
 * The bug this exists to prevent was invisible text on the login form: shadcn's
 * `<Input>` sets `bg-background` with no text colour, `.u-root` sets a light
 * `color`, and the field rendered near-white text on white — a measured
 * contrast of 1.06:1.
 *
 * The subtle part, and the reason a test is worth having, is *which* variables
 * have to be redefined. Tailwind v4 declares `--color-background:
 * var(--background)` at `:root`, and a custom property is substituted where it
 * is declared rather than where it is used. Redefining `--background` deeper in
 * the tree therefore does not reach the utility — only redefining
 * `--color-background` does. Someone tidying this later would very reasonably
 * delete the "duplicate" block and silently reintroduce the bug.
 */
class ThemeTokenBridgeTest extends TestCase
{
    private function css(): string
    {
        return file_get_contents(resource_path('css/app.css'));
    }

    /** The block inside `.u-root` that redefines the tokens. */
    private function bridgeBlock(): string
    {
        $css = $this->css();
        $start = strpos($css, '--color-background: var(--u-bg)');

        $this->assertNotFalse($start, 'The shadcn → universe token bridge is missing entirely.');

        return substr($css, $start - 400, 2600);
    }

    public function test_tailwind_facing_colour_tokens_are_remapped(): void
    {
        $bridge = $this->bridgeBlock();

        // These are the names Tailwind utilities actually read. Redefining the
        // bare shadcn names alone does not work — see the class comment.
        $required = [
            '--color-background',
            '--color-foreground',
            '--color-card',
            '--color-popover',
            '--color-primary',
            '--color-primary-foreground',
            '--color-secondary',
            '--color-muted',
            '--color-muted-foreground',
            '--color-accent',
            '--color-border',
            '--color-input',
            '--color-ring',
        ];

        foreach ($required as $token) {
            $this->assertStringContainsString(
                $token.': var(--u-',
                $bridge,
                "{$token} is not mapped onto a universe token; components using it will render against the light theme inside a dark universe.",
            );
        }
    }

    public function test_form_controls_get_an_explicit_colour(): void
    {
        // The belt to the bridge's braces: even if a token were missed, a field
        // must never inherit its way back to invisible.
        $css = $this->css();

        $this->assertStringContainsString('.u-root input:not([type=\'checkbox\'])', $css);
        $this->assertStringContainsString('.u-root textarea', $css);
        $this->assertMatchesRegularExpression(
            '/\.u-root textarea,\s*\.u-root select \{[^}]*color: var\(--u-text\)/s',
            $css,
            'Form controls inside a universe must set an explicit text colour.',
        );
    }

    public function test_the_bridge_is_scoped_to_universes(): void
    {
        $css = $this->css();

        // Scoped to `.u-root`, not `:root`. Remapping globally would break the
        // light-theme pages that legitimately use the shadcn defaults.
        $this->assertMatchesRegularExpression(
            '/\.u-root \{\s*\/\* What Tailwind utilities read/',
            $css,
            'The bridge must be scoped to .u-root rather than applied globally.',
        );
    }
}
