import { themeToCssVars, useUniverse } from '@/hooks/use-universe';
import { cn } from '@/lib/utils';
import type { ReactNode } from 'react';

/**
 * Applies the active universe's token set to the subtree.
 *
 * Tokens arrive from the server already filtered by entitlement (see
 * App\Support\UniverseContext), so a locked world reaches the browser carrying
 * the free fallback palette — never the paid one.
 */
export function UniverseRoot({ children, className }: { children: ReactNode; className?: string }) {
    const universe = useUniverse();

    return (
        <div
            data-universe={universe?.slug ?? 'cosmos'}
            data-scheme={universe?.scheme ?? 'dark'}
            style={themeToCssVars(universe?.theme)}
            className={cn('u-root relative', className)}
        >
            {children}
        </div>
    );
}
