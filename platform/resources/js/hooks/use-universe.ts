import type { SharedData, Universe, UniverseTheme } from '@/types';
import { usePage } from '@inertiajs/react';
import type { CSSProperties } from 'react';

/**
 * The universe the current page should render in.
 *
 * Pages may pass their own `universe` prop — a post is always read in its own
 * world, regardless of which persona the reader writes as. Everything else
 * falls back to the shared `activeUniverse`.
 */
export function useUniverse(): Universe | null {
    const page = usePage<SharedData & { universe?: Universe | null }>();

    return page.props.universe ?? page.props.activeUniverse ?? null;
}

/**
 * Maps the server's token payload onto the CSS custom properties that every
 * metallic primitive reads. This is the only place the two vocabularies meet.
 */
export function themeToCssVars(theme: UniverseTheme | undefined): CSSProperties {
    if (!theme) return {};

    return {
        '--u-bg': theme.bg,
        '--u-bg-deep': theme.bgDeep,
        '--u-surface-1': theme.surface1,
        '--u-surface-2': theme.surface2,
        '--u-border': theme.border,
        '--u-text': theme.text,
        '--u-text-muted': theme.textMuted,
        '--u-accent': theme.accent,
        '--u-accent-fg': theme.accentFg,
        '--u-accent-soft': theme.accentSoft,
        '--u-metal-base': theme.metalBase,
        '--u-metal-sheen': theme.metalSheen,
        '--u-metal-edge': theme.metalEdge,
        '--u-metal-shadow': theme.metalShadow,
        '--u-grain-angle': theme.grainAngle,
        '--u-glow': theme.glow,
        '--u-halo': theme.halo,
    } as CSSProperties;
}
