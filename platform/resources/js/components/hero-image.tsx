import { useParallax } from '@/hooks/use-motion';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Full-bleed photographic hero with a parallax drift and a scrim that fades the
 * image into the universe's background colour, so the seam is invisible in all
 * six palettes.
 */
export function HeroImage({
    src,
    credit,
    height = 'tall',
    children,
    className,
}: {
    src: string;
    credit?: { name: string; username: string } | null;
    height?: 'tall' | 'short';
    children?: ReactNode;
    className?: string;
}) {
    const [ref, offset] = useParallax<HTMLDivElement>(70);

    return (
        <div className={cn('relative isolate overflow-hidden', height === 'tall' ? 'min-h-[68vh]' : 'min-h-[38vh]', className)}>
            <div
                ref={ref}
                className="absolute inset-0 -z-10 will-change-transform"
                style={{ transform: `translate3d(0, ${offset}px, 0) scale(1.12)` }}
            >
                <img src={src} alt="" className="size-full object-cover" fetchPriority="high" decoding="async" />
            </div>

            {/* Scrim: darkens for legibility and melts the photo into the page. */}
            <div
                aria-hidden
                className="absolute inset-0 -z-10"
                style={{
                    background:
                        'linear-gradient(to bottom, color-mix(in srgb, var(--u-bg) 55%, transparent) 0%, color-mix(in srgb, var(--u-bg) 30%, transparent) 38%, color-mix(in srgb, var(--u-bg) 88%, transparent) 78%, var(--u-bg) 100%)',
                }}
            />
            <div aria-hidden className="u-halo absolute inset-0 -z-10" />

            {children}

            {credit && (
                <a
                    href={`https://unsplash.com/@${credit.username}?utm_source=aetheris&utm_medium=referral`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="absolute right-3 bottom-3 rounded-full px-2.5 py-1 text-[10px] tracking-wide backdrop-blur-sm transition-opacity hover:opacity-100"
                    style={{ color: 'var(--u-text-muted)', backgroundColor: 'color-mix(in srgb, var(--u-bg) 55%, transparent)', opacity: 0.7 }}
                >
                    Photo: {credit.name} / Unsplash
                </a>
            )}
        </div>
    );
}
