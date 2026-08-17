import { Chip, EmptyState, Panel, SectionHeading, Swatch } from '@/components/metal';
import { useCountUp } from '@/hooks/use-motion';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Clock, PenLine, Pencil } from 'lucide-react';

interface Props {
    posts: PostCard[];
    stats: { published: number; drafts: number; personas: number; following: number };
}

export default function Dashboard({ posts, stats }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title="Your desk" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">Your desk</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    Everything you've written, across every persona.
                </p>
            </header>

            <div className="mb-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {[
                    { label: 'Published', value: stats.published },
                    { label: 'Drafts', value: stats.drafts },
                    { label: 'Personas', value: stats.personas },
                    { label: 'Following', value: stats.following },
                ].map((stat) => (
                    <StatTile key={stat.label} label={stat.label} value={stat.value} />
                ))}
            </div>

            {!auth.user?.is_premium && (
                <Panel className="mb-12 flex flex-wrap items-center gap-5 p-6">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-lg font-semibold">You're on the free plan</h2>
                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Two universes and one persona. Premium opens all six worlds and unlimited personas.
                        </p>
                    </div>
                    <Link href="/upgrade" className="u-btn u-btn-primary">
                        See premium
                    </Link>
                </Panel>
            )}

            <SectionHeading
                eyebrow="Your work"
                title="All pieces"
                action={
                    <Link href="/write" className="u-btn u-btn-primary">
                        <PenLine className="size-4" />
                        New piece
                    </Link>
                }
            />

            {posts.length === 0 ? (
                <EmptyState
                    title="Nothing written yet"
                    body="Your desk is empty. Pick a persona and start something — drafts stay private until you publish."
                    action={
                        <Link href="/write" className="u-btn u-btn-primary">
                            Write your first piece
                        </Link>
                    }
                />
            ) : (
                <div className="flex flex-col gap-3">
                    {posts.map((post) => (
                        <Panel key={post.slug} className="flex flex-wrap items-center gap-5 p-5">
                            {post.universe && <Swatch swatch={post.universe.swatch} size={38} />}

                            <div className="min-w-0 flex-1">
                                <Link href={`/posts/${post.slug}`} className="font-display text-xl hover:underline">
                                    {post.title}
                                </Link>
                                <div className="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    {post.persona && <span>@{post.persona.handle}</span>}
                                    {post.universe && (
                                        <>
                                            <span aria-hidden>·</span>
                                            <span>{post.universe.name}</span>
                                        </>
                                    )}
                                    <span aria-hidden>·</span>
                                    <span className="inline-flex items-center gap-1">
                                        <Clock className="size-3" />
                                        {post.reading_time} min
                                    </span>
                                    {post.updated_human && (
                                        <>
                                            <span aria-hidden>·</span>
                                            <span>edited {post.updated_human}</span>
                                        </>
                                    )}
                                </div>
                            </div>

                            <Chip tone={post.status === 'draft' ? 'accent' : 'default'}>
                                {post.status === 'draft' ? 'Draft' : (post.published_human ?? 'Published')}
                            </Chip>

                            <Link href={`/posts/${post.slug}/edit`} className="u-btn u-btn-ghost">
                                <Pencil className="size-3.5" />
                                Edit
                            </Link>
                        </Panel>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}

/** Stat that counts up the first time it scrolls into view. */
function StatTile({ label, value }: { label: string; value: number }) {
    const [ref, display] = useCountUp(value);

    return (
        <Panel className="p-6">
            <span ref={ref} className="font-display block text-4xl tabular-nums" style={{ color: 'var(--u-accent)' }}>
                {display}
            </span>
            <p className="mt-1.5 text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                {label}
            </p>
        </Panel>
    );
}
