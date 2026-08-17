import { Panel } from '@/components/metal';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

export interface Ad {
    slot: string;
    kind: string;
    title: string;
    body: string;
    cta: string;
    href: string;
}

/**
 * Ads only ever render between pieces or after one — never inside a reading
 * view. Interrupting an argument mid-paragraph would sell the one thing the
 * platform is asking people to show up for.
 *
 * For an entitled reader the server sends an empty array, so this renders
 * nothing and no creative was ever serialized into the page.
 */
export function AdSlot({ ads, className }: { ads: Ad[]; className?: string }) {
    if (!ads || ads.length === 0) return null;

    return (
        <>
            {ads.map((ad) => (
                <aside key={ad.slot} className={cn(className)} aria-label="Advertisement">
                    <Panel className="p-6">
                        <p className="mb-3 text-[10px] font-semibold tracking-[0.2em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                            Sponsored
                        </p>
                        <h3 className="font-display text-xl">{ad.title}</h3>
                        <p className="mt-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {ad.body}
                        </p>
                        <Link href={ad.href} className="u-btn u-btn-ghost mt-4">
                            {ad.cta}
                        </Link>
                    </Panel>
                </aside>
            ))}
        </>
    );
}
