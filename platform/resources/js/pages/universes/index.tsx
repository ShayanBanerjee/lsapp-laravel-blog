import { Panel } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import { UniverseCard } from '@/components/universe-card';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, UniversePreview } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';

export default function UniversesIndex({ universes }: { universes: UniversePreview[] }) {
    const { auth } = usePage<SharedData>().props;
    const lockedCount = universes.filter((universe) => universe.locked).length;

    return (
        <SiteLayout wide>
            <Head title="Universes" />

            <header className="mb-12 max-w-3xl">
                <h1 className="font-display text-4xl sm:text-6xl">Six worlds to write in</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Each universe is a complete design system — palette, material, texture, typography and motion. Not a colour swap. Choosing one
                    changes what the whole platform feels like while you work.
                </p>
            </header>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {universes.map((universe, index) => (
                    <Reveal key={universe.slug} delay={Math.min(index * 80, 400)}>
                        <UniverseCard universe={universe} />
                    </Reveal>
                ))}
            </div>

            {lockedCount > 0 && (
                <Panel className="mt-12 p-8 sm:p-10">
                    <div className="flex flex-wrap items-center gap-6">
                        <Sparkles className="size-8 shrink-0" style={{ color: 'var(--u-accent)' }} />
                        <div className="min-w-0 flex-1">
                            <h2 className="font-display text-2xl">
                                {lockedCount} {lockedCount === 1 ? 'universe is' : 'universes are'} locked on your plan
                            </h2>
                            <p className="mt-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                You can read everything published in them. Writing there — and applying their themes — needs premium.
                            </p>
                        </div>
                        <Link href={auth.user ? '/upgrade' : '/register'} className="u-btn u-btn-primary px-5 py-2.5">
                            {auth.user ? 'Unlock all six' : 'Create an account'}
                        </Link>
                    </div>
                </Panel>
            )}
        </SiteLayout>
    );
}
