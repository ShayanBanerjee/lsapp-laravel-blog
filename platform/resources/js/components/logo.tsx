import { cn } from '@/lib/utils';

/**
 * The Inkfathom mark.
 *
 * An ink drop descending through depth strata — the two halves of the name in
 * one shape. "Ink" is the drop; "fathom" is both the depth it is falling
 * through and the act of understanding. The narrowing strata are the same idea
 * as the Deep Field: one subject, read at descending scales.
 *
 * Drawn in `currentColor` and a single accent, so it re-themes automatically in
 * every universe instead of needing one exported file per world.
 */
export function LogoMark({ className, size = 28 }: { className?: string; size?: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 32 32" fill="none" role="img" aria-label="Inkfathom" className={cn('shrink-0', className)}>
            {/* The drop */}
            <path d="M16 2.5C16 2.5 9.5 10.6 9.5 14.2a6.5 6.5 0 1 0 13 0C22.5 10.6 16 2.5 16 2.5Z" fill="var(--u-accent, currentColor)" />
            {/* Specular catchlight — the metallic surface treatment, in miniature */}
            <path d="M13.4 12.4c.35-1.6 1.3-3.2 2.2-4.5" stroke="var(--u-accent-fg, #fff)" strokeWidth="1.4" strokeLinecap="round" opacity="0.55" />
            {/* Depth strata: the fathom lines, narrowing as they descend */}
            <g stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <path d="M6 24h20" opacity="0.85" />
                <path d="M9.5 27.6h13" opacity="0.55" />
                <path d="M13 30.6h6" opacity="0.3" />
            </g>
        </svg>
    );
}

/** Mark plus wordmark, for the header and footer. */
export function Logo({ className, size = 28 }: { className?: string; size?: number }) {
    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <LogoMark size={size} />
            <span className="font-display text-xl tracking-tight">Inkfathom</span>
        </span>
    );
}
