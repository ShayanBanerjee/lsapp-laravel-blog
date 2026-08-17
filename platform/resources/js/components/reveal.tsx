import { useReveal } from '@/hooks/use-motion';
import { cn } from '@/lib/utils';
import type { ElementType, ReactNode } from 'react';

/**
 * Fades and lifts its children in the first time they scroll into view.
 *
 * Under prefers-reduced-motion the content is rendered visible immediately —
 * the animation is removed, never the content.
 */
export function Reveal({
    children,
    delay = 0,
    as: Tag = 'div',
    className,
}: {
    children: ReactNode;
    /** Stagger offset in ms. Keep cumulative delays under ~400ms. */
    delay?: number;
    as?: ElementType;
    className?: string;
}) {
    const [ref, visible] = useReveal<HTMLDivElement>();

    return (
        <Tag
            ref={ref}
            // min-w-0: grid and flex children default to min-width:auto, which
            // makes them refuse to shrink below their content and blows out the
            // page horizontally on narrow screens.
            className={cn('u-reveal min-w-0', visible && 'is-visible', className)}
            style={{ transitionDelay: visible ? `${delay}ms` : '0ms' }}
        >
            {children}
        </Tag>
    );
}

/** Convenience wrapper that staggers a list of children. */
export function RevealGroup({ children, step = 70, className }: { children: ReactNode[]; step?: number; className?: string }) {
    return (
        <div className={className}>
            {children.map((child, index) => (
                <Reveal key={index} delay={Math.min(index * step, 420)}>
                    {child}
                </Reveal>
            ))}
        </div>
    );
}
