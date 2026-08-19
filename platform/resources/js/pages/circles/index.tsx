import { Action, ActionLink } from '@/components/action';
import { Chip, Panel, Swatch } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Highlighter, MessageSquareQuote, Plus, Users } from 'lucide-react';

interface CircleRow {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    members_count: number;
    posts_count: number;
    universe: UniversePreview | null;
    hero_image: string | null;
    joined: boolean;
    latest: { slug: string; title: string; when: string | null } | null;
    signal: { quote: string; marks: number } | null;
}

/**
 * Circles, presented as rooms with something happening in them.
 *
 * The previous version listed a name, a line and two counts, which is a
 * directory entry. What tells you whether to join a room is what was last said
 * in it and what people stopped on — so both are surfaced on the card, and the
 * counts are demoted to where counts belong.
 */
export default function CirclesIndex({ circles }: { circles: CircleRow[] }) {
    const { auth } = usePage<SharedData>().props;
    const joined = circles.filter((circle) => circle.joined);
    const rest = circles.filter((circle) => !circle.joined);

    return (
        <SiteLayout wide>
            <Head title="Circles" />

            <header className="mb-12 max-w-3xl">
                <p className="mb-4 text-[11px] font-semibold tracking-[0.22em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    Community by subject
                </p>
                <h1 className="font-display text-5xl leading-[1.05] sm:text-6xl">Circles</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Rooms organised around what people are working on, not around who has the most followers. Nobody's reach decides what you see here
                    — a first piece sits beside a hundredth.
                </p>
            </header>

            {joined.length > 0 && (
                <section className="mb-12">
                    <h2 className="mb-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Yours
                    </h2>
                    <div className="grid gap-5 lg:grid-cols-2">
                        {joined.map((circle) => (
                            <CircleCard key={circle.slug} circle={circle} signedIn={Boolean(auth.user)} />
                        ))}
                    </div>
                </section>
            )}

            <section>
                {joined.length > 0 && (
                    <h2 className="mb-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Everything else
                    </h2>
                )}
                <div className="grid gap-5 lg:grid-cols-2">
                    {rest.map((circle, index) => (
                        <Reveal key={circle.slug} delay={Math.min(index * 50, 250)}>
                            <CircleCard circle={circle} signedIn={Boolean(auth.user)} />
                        </Reveal>
                    ))}
                </div>
            </section>
        </SiteLayout>
    );
}

function CircleCard({ circle, signedIn }: { circle: CircleRow; signedIn: boolean }) {
    return (
        <Panel interactive className="group flex h-full flex-col overflow-hidden">
            <div className="relative">
                {circle.hero_image && (
                    <div className="relative h-32 overflow-hidden">
                        <img src={circle.hero_image} alt="" loading="lazy" className="img-zoom size-full object-cover opacity-60" />
                        <div
                            aria-hidden
                            className="absolute inset-0"
                            style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 85%)' }}
                        />
                    </div>
                )}

                <div className="p-6">
                    <div className="flex items-start gap-4">
                        {circle.universe && <Swatch swatch={circle.universe.swatch} size={38} />}

                        <div className="min-w-0 flex-1">
                            <h3 className="font-display text-2xl leading-tight">
                                <Link href={`/circles/${circle.slug}`}>{circle.name}</Link>
                            </h3>
                            {circle.tagline && (
                                <p className="mt-1 text-sm" style={{ color: 'var(--u-accent)' }}>
                                    {circle.tagline}
                                </p>
                            )}
                        </div>

                        {signedIn && (
                            <Action
                                variant={circle.joined ? 'ghost' : 'primary'}
                                size="sm"
                                magnetic={3}
                                icon={circle.joined ? <Check className="size-3.5" /> : <Plus className="size-3.5" />}
                                onClick={() => router.post(`/circles/${circle.slug}/membership`, {}, { preserveScroll: true })}
                            >
                                {circle.joined ? 'In' : 'Join'}
                            </Action>
                        )}
                    </div>

                    {circle.description && (
                        <p className="mt-4 line-clamp-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {circle.description}
                        </p>
                    )}

                    {/* What was last said in the room, and what it stopped on. */}
                    {circle.signal && (
                        <figure className="mt-5 border-l-2 pl-4" style={{ borderColor: 'var(--u-accent)' }}>
                            <blockquote className="font-display text-lg leading-snug italic">“{circle.signal.quote}”</blockquote>
                            <figcaption className="mt-1.5 flex items-center gap-1.5 text-xs" style={{ color: 'var(--u-accent)' }}>
                                <Highlighter className="size-3" />
                                {circle.signal.marks} {circle.signal.marks === 1 ? 'reader' : 'readers'} stopped here
                            </figcaption>
                        </figure>
                    )}

                    {circle.latest && (
                        <p className="mt-5 flex flex-wrap items-baseline gap-x-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            <MessageSquareQuote className="size-3.5 shrink-0" />
                            <span>Latest:</span>
                            <Link href={`/posts/${circle.latest.slug}`} className="min-w-0 truncate font-medium" style={{ color: 'var(--u-text)' }}>
                                {circle.latest.title}
                            </Link>
                            {circle.latest.when && <span className="text-xs">{circle.latest.when}</span>}
                        </p>
                    )}

                    <div className="mt-6 flex flex-wrap items-center gap-2">
                        <Chip>
                            <Users className="size-3" />
                            {circle.members_count} {circle.members_count === 1 ? 'member' : 'members'}
                        </Chip>
                        <Chip>
                            {circle.posts_count} {circle.posts_count === 1 ? 'piece' : 'pieces'}
                        </Chip>
                        {circle.universe && <Chip>{circle.universe.name}</Chip>}
                        <ActionLink href={`/circles/${circle.slug}`} variant="quiet" size="sm" className="ml-auto">
                            Open
                        </ActionLink>
                    </div>
                </div>
            </div>
        </Panel>
    );
}
