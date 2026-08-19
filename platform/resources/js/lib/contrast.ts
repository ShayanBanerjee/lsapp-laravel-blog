/**
 * Client-side mirror of App\Support\Contrast.
 *
 * This exists so the theme editor can show a contrast ratio update as a colour
 * is dragged, without a round trip. It is *not* the enforcement point — the
 * server rejects a failing palette regardless of what this reports, because a
 * check that ships in the bundle is a check an author can edit out.
 */

function channels(hex: string): [number, number, number] {
    let value = hex.trim().replace(/^#/, '');

    if (value.length === 3 || value.length === 4) {
        value = value[0] + value[0] + value[1] + value[1] + value[2] + value[2];
    }

    value = value.slice(0, 6);

    if (!/^[0-9a-f]{6}$/i.test(value)) return [0, 0, 0];

    return [parseInt(value.slice(0, 2), 16), parseInt(value.slice(2, 4), 16), parseInt(value.slice(4, 6), 16)];
}

export function luminance(hex: string): number {
    const [r, g, b] = channels(hex).map((channel) => {
        const c = channel / 255;
        return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contrastRatio(foreground: string, background: string): number {
    const a = luminance(foreground);
    const b = luminance(background);

    return Math.round(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)) * 100) / 100;
}

/** Same rule table as ThemeTokens::CONTRAST_RULES, in the same order. */
export const CONTRAST_RULES: { fg: string; bg: string; min: number; label: string }[] = [
    { fg: 'text', bg: 'bg', min: 4.5, label: 'Body text on the page background' },
    { fg: 'text', bg: 'surface1', min: 4.5, label: 'Body text on panels' },
    { fg: 'textMuted', bg: 'bg', min: 4.5, label: 'Muted text on the page background' },
    { fg: 'textMuted', bg: 'surface1', min: 4.5, label: 'Muted text on panels' },
    { fg: 'accentFg', bg: 'accent', min: 4.5, label: 'Button labels on the accent colour' },
    { fg: 'border', bg: 'bg', min: 1.2, label: 'Borders against the page background' },
];

export function withAlpha(hex: string, alpha: number): string {
    const [r, g, b] = channels(hex);
    const a = Math.round(Math.max(0, Math.min(1, alpha)) * 255);

    return `#${[r, g, b, a].map((v) => v.toString(16).padStart(2, '0')).join('')}`;
}
