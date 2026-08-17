import { Chip, Panel, Swatch } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowUpRight, Users } from 'lucide-react';

interface CircleRow {
    slug: string;
    name: string;
    tagline: string;
    description: string;
    members_count: number;
    posts_count: number;
    universe: UniversePreview | null;
    joined: boolean;
}

export default function CirclesIndex({ circles }: { circles: CircleRow[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title="Circles" />

            <header className="mb-12 max-w-3xl">
                <h1 className="font-display text-4xl sm:text-6xl">Circles</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Gathered by subject, not by follower count. A following list makes community a byproduct of fame; a circle makes it a byproduct of
                    what you are actually interested in.
                </p>
            </header>

            <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {circles.map((circle, index) => (
                    <Reveal key={circle.slug} delay={Math.min(index * 70, 350)}>
                        <Panel interactive className="group flex h-full flex-col p-6">
                            <div className="mb-4 flex items-start gap-3">
                                {circle.universe && <Swatch swatch={circle.universe.swatch} size={34} />}
                                <div className="min-w-0 flex-1">
                                    <Link href={`/circles/${circle.slug}`} className="font-display text-2xl hover:underline">
                                        {circle.name}
                                    </Link>
                                    <p className="mt-0.5 text-sm italic" style={{ color: 'var(--u-accent)' }}>
                                        {circle.tagline}
                                    </p>
                                </div>
                            </div>

                            <p className="line-clamp-3 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                {circle.description}
                            </p>

                            <div className="mt-4 flex flex-wrap gap-2">
                                <Chip>
                                    <Users className="size-3" />
                                    {circle.members_count} {circle.members_count === 1 ? 'member' : 'members'}
                                </Chip>
                                <Chip>{circle.posts_count} shared</Chip>
                                {circle.universe && <Chip>{circle.universe.name}</Chip>}
                            </div>

                            <div className="mt-6 flex gap-2 pt-4" style={{ borderTop: '1px solid var(--u-border)' }}>
                                {auth.user ? (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/circles/${circle.slug}/membership`, {}, { preserveScroll: true })}
                                        className={circle.joined ? 'u-btn u-btn-ghost flex-1' : 'u-btn u-btn-primary flex-1'}
                                    >
                                        {circle.joined ? 'Joined' : 'Join'}
                                    </button>
                                ) : (
                                    <Link href="/login" className="u-btn u-btn-ghost flex-1">
                                        Log in to join
                                    </Link>
                                )}
                                <Link href={`/circles/${circle.slug}`} className="u-btn u-btn-ghost" aria-label={`Open ${circle.name}`}>
                                    <ArrowUpRight className="size-4" />
                                </Link>
                            </div>
                        </Panel>
                    </Reveal>
                ))}
            </div>
        </SiteLayout>
    );
}
