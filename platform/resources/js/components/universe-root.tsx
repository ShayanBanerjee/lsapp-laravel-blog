import { themeToCssVars, useUniverse } from '@/hooks/use-universe';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import type { CSSProperties, ReactNode } from 'react';

/**
 * Applies the active universe's token set to the subtree.
 *
 * Tokens arrive from the server already filtered by entitlement (see
 * App\Support\UniverseContext), so a locked world reaches the browser carrying
 * the free fallback palette — never the paid one.
 */
export function UniverseRoot({ children, className }: { children: ReactNode; className?: string }) {
    const universe = useUniverse();
    const { reading } = usePage<SharedData>().props;

    // The reader's typography, applied alongside the universe's tokens. The
    // font stack is resolved server-side from an allowlist — never taken
    // straight from client input, since it lands in a style attribute.
    const readingVars = reading
        ? ({
              '--u-read-font': reading.stack,
              '--u-read-size': `${reading.size}px`,
              '--u-read-leading': String(reading.leading),
              '--u-read-measure': `${reading.measure}ch`,
          } as CSSProperties)
        : {};

    return (
        <div
            data-universe={universe?.slug ?? 'cosmos'}
            data-scheme={universe?.scheme ?? 'dark'}
            style={{ ...themeToCssVars(universe?.theme), ...readingVars }}
            className={cn('u-root relative', className)}
        >
            {children}
        </div>
    );
}
