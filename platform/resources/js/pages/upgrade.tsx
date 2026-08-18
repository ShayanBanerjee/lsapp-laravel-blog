import { Panel, Swatch } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, Lock } from 'lucide-react';

interface Props {
    isPremium: boolean;
    universes: UniversePreview[];
    freePersonaLimit: number;
    isStub: boolean;
}

export default function Upgrade({ isPremium, universes, freePersonaLimit, isStub }: Props) {
    const { auth } = usePage<SharedData>().props;

    const free = [
        `${universes.filter((u) => !u.is_premium).length} universes (Cosmos and Nature)`,
        `${freePersonaLimit} persona`,
        'Unlimited published pieces',
        'Full reading access to every world',
    ];

    const premium = [
        'All six universes',
        'Unlimited personas',
        'Custom theme editor',
        'Vanity handles for each persona',
        'Everything in free, forever',
    ];

    return (
        <SiteLayout>
            <Head title="Premium" />

            <header className="mb-14 text-center">
                <p className="mb-4 text-[11px] font-semibold tracking-[0.22em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    Pricing
                </p>
                <h1 className="font-display text-5xl sm:text-6xl">Unlock every world</h1>
                <p className="mx-auto mt-5 max-w-xl text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Reading is free and always will be — it keeps the writing shareable. Premium is about how many selves you get to write as, and
                    which worlds they live in.
                </p>
            </header>

            {isStub && (
                <Panel className="mb-10 flex items-start gap-4 p-5">
                    <AlertTriangle className="mt-0.5 size-5 shrink-0" style={{ color: '#f0a04b' }} />
                    <div>
                        <p className="text-sm font-semibold">Demo mode — no payments are processed</p>
                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Billing is not connected yet, so the button below simply switches your plan. Nothing is charged and no card details are
                            collected.
                        </p>
                    </div>
                </Panel>
            )}

            <div className="grid gap-5 md:grid-cols-2">
                <Panel className="flex flex-col p-8">
                    <h2 className="font-display text-2xl">Free</h2>
                    <p className="mt-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        For a single voice.
                    </p>
                    <p className="font-display mt-6 text-5xl">£0</p>

                    <ul className="mt-8 flex flex-1 flex-col gap-3">
                        {free.map((item) => (
                            <li key={item} className="flex items-start gap-2.5 text-sm">
                                <Check className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--u-text-muted)' }} />
                                {item}
                            </li>
                        ))}
                    </ul>

                    {!isPremium && (
                        <p className="mt-8 text-center text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Your current plan
                        </p>
                    )}
                </Panel>

                <Panel className="relative flex flex-col p-8" interactive>
                    <div className="u-halo absolute inset-0" aria-hidden />
                    <div className="relative flex flex-1 flex-col">
                        <h2 className="font-display text-2xl" style={{ color: 'var(--u-accent)' }}>
                            Premium
                        </h2>
                        <p className="mt-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            For everyone you contain.
                        </p>
                        <p className="font-display mt-6 text-5xl">
                            £6
                            <span className="text-lg" style={{ color: 'var(--u-text-muted)' }}>
                                {' '}
                                / month
                            </span>
                        </p>

                        <ul className="mt-8 flex flex-1 flex-col gap-3">
                            {premium.map((item) => (
                                <li key={item} className="flex items-start gap-2.5 text-sm">
                                    <Check className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                                    {item}
                                </li>
                            ))}
                        </ul>

                        <div className="mt-8">
                            {!auth.user ? (
                                <Link href="/register" className="u-btn u-btn-primary w-full py-3 text-base">
                                    Create an account
                                </Link>
                            ) : isPremium ? (
                                <div className="flex flex-col gap-3">
                                    <p className="text-center text-sm" style={{ color: 'var(--u-accent)' }}>
                                        Premium is active — all six worlds are yours.
                                    </p>
                                    {isStub && (
                                        <button
                                            type="button"
                                            onClick={() => router.delete('/upgrade', { preserveScroll: true })}
                                            className="u-btn u-btn-ghost w-full"
                                        >
                                            Revert to free (demo)
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <button type="button" onClick={() => router.post('/upgrade')} className="u-btn u-btn-primary w-full py-3 text-base">
                                    {isStub ? 'Enable demo premium' : 'Upgrade now'}
                                </button>
                            )}
                        </div>
                    </div>
                </Panel>
            </div>

            <section className="mt-16">
                <h2 className="mb-6 text-center text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    What premium unlocks
                </h2>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {universes.map((universe) => (
                        <Panel key={universe.slug} className="flex items-center gap-4 p-4">
                            <Swatch swatch={universe.swatch} size={38} />
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">{universe.name}</p>
                                <p className="truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    {universe.material}
                                </p>
                            </div>
                            {universe.locked ? (
                                <Lock className="size-4 shrink-0" style={{ color: 'var(--u-text-muted)' }} />
                            ) : (
                                <Check className="size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                            )}
                        </Panel>
                    ))}
                </div>
            </section>
        </SiteLayout>
    );
}
