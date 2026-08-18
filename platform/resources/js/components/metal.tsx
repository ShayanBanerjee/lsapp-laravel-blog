import { cn } from '@/lib/utils';
import type { ComponentPropsWithoutRef, ReactNode, Ref } from 'react';

/**
 * The metallic primitives. Everything visual in the app composes these — no
 * component anywhere branches on which universe it is rendering in.
 */

interface PanelProps extends ComponentPropsWithoutRef<'div'> {
    /** Adds the hover lift. Pair with `.specular-pointer` for cursor tracking. */
    interactive?: boolean;
    /** Adds the anisotropic brush grain. Turn off behind photography. */
    grain?: boolean;
    /** React 19 passes ref as a plain prop; no forwardRef needed. */
    ref?: Ref<HTMLDivElement>;
    children?: ReactNode;
}

export function Panel({ interactive, grain = true, className, children, ref, ...props }: PanelProps) {
    return (
        <div ref={ref} className={cn('metal metal-edge', grain && 'grain', interactive && 'lift', className)} {...props}>
            {children}
        </div>
    );
}

/** Thin brushed strip — used as a section divider and card accent rail. */
export function Rail({ className }: { className?: string }) {
    return (
        <div
            aria-hidden
            className={cn('h-px w-full', className)}
            style={{
                backgroundImage: 'linear-gradient(90deg, transparent, color-mix(in srgb, var(--u-metal-edge) 70%, transparent), transparent)',
            }}
        />
    );
}

/** Small metadata chip. */
export function Chip({ children, className, tone = 'default' }: { children: ReactNode; className?: string; tone?: 'default' | 'accent' }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium tracking-wide uppercase',
                className,
            )}
            style={{
                borderColor: tone === 'accent' ? 'var(--u-accent)' : 'var(--u-border)',
                color: tone === 'accent' ? 'var(--u-accent)' : 'var(--u-text-muted)',
                backgroundColor: tone === 'accent' ? 'var(--u-accent-soft)' : 'color-mix(in srgb, var(--u-surface-2) 55%, transparent)',
            }}
        >
            {children}
        </span>
    );
}

/** Three-colour universe preview. Safe to render for locked worlds. */
export function Swatch({ swatch, size = 34 }: { swatch: { bg: string; accent: string; metalBase: string }; size?: number }) {
    return (
        <span
            aria-hidden
            className="inline-block shrink-0 rounded-full"
            style={{
                width: size,
                height: size,
                background: `conic-gradient(from 210deg, ${swatch.accent}, ${swatch.metalBase} 45%, ${swatch.bg} 78%, ${swatch.accent})`,
                boxShadow: 'inset 0 0 0 1px rgb(255 255 255 / 0.14), 0 2px 8px -2px rgb(0 0 0 / 0.5)',
            }}
        />
    );
}

export function SectionHeading({ eyebrow, title, action }: { eyebrow?: string; title: string; action?: ReactNode }) {
    return (
        <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
            <div>
                {eyebrow && (
                    <p className="mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        {eyebrow}
                    </p>
                )}
                <h2 className="font-display text-3xl sm:text-4xl">{title}</h2>
            </div>
            {action}
        </div>
    );
}

export function EmptyState({ title, body, action }: { title: string; body: string; action?: ReactNode }) {
    return (
        <Panel className="px-8 py-16 text-center">
            <h3 className="font-display text-2xl">{title}</h3>
            <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                {body}
            </p>
            {action && <div className="mt-7 flex justify-center">{action}</div>}
        </Panel>
    );
}
