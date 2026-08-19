import { Chip, EmptyState, Panel, SectionHeading, Swatch } from '@/components/metal';
import { useCountUp } from '@/hooks/use-motion';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Banknote, Clock, Mail, PenLine, Pencil, TriangleAlert } from 'lucide-react';

interface MarkedPassage {
    slug: string;
    title: string;
    quote: string;
    marks: number;
}

interface CopyFlagRow {
    id: number;
    kind: 'containment' | 'duplicate';
    similarity: number;
    post: { slug: string | null; title: string | null };
    matched: { slug: string | null; title: string | null; handle: string | null };
}

interface Props {
    posts: PostCard[];
    stats: { published: number; drafts: number; personas: number; following: number };
    markedPassages: MarkedPassage[];
    unreadLetters: number;
    copyFlags: CopyFlagRow[];
}

export default function Dashboard({ posts, stats, markedPassages = [], unreadLetters = 0, copyFlags = [] }: Props) {
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

            {markedPassages.length > 0 && (
                <section className="mb-12">
                    <SectionHeading eyebrow="What landed" title="Sentences readers stopped on" />
                    <p className="mb-6 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        This is the feedback no other medium can give you. Not that a piece was liked — which line did the work.
                    </p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {markedPassages.map((passage, index) => (
                            <Panel key={`${passage.slug}-${index}`} className="p-5">
                                <p className="font-display text-lg leading-snug italic">“{passage.quote}”</p>
                                <div className="mt-3 flex items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    <Link href={`/posts/${passage.slug}`} className="truncate hover:underline">
                                        {passage.title}
                                    </Link>
                                    <span className="ml-auto whitespace-nowrap" style={{ color: 'var(--u-accent)' }}>
                                        {passage.marks} {passage.marks === 1 ? 'mark' : 'marks'}
                                    </span>
                                </div>
                            </Panel>
                        ))}
                    </div>
                </section>
            )}

            <Panel className="mb-12 flex flex-wrap items-center gap-5 p-6">
                <Banknote className="size-6 shrink-0" style={{ color: 'var(--u-accent)' }} />
                <div className="min-w-0 flex-1">
                    <h2 className="text-lg font-semibold">Readers can pay you directly</h2>
                    <p className="mt-1 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Tips on a piece, or a monthly membership in one of your voices. You keep 75% — we pay the card fees out of our share, not
                        yours.
                    </p>
                </div>
                <Link href="/earnings" className="u-btn u-btn-primary">
                    Your earnings
                </Link>
            </Panel>

            {copyFlags.length > 0 && (
                <Panel className="mb-12 p-6">
                    <div className="flex items-start gap-4">
                        <TriangleAlert className="mt-0.5 size-5 shrink-0" style={{ color: '#f0a04b' }} />
                        <div className="min-w-0 flex-1">
                            <h2 className="text-lg font-semibold">
                                {copyFlags.length === 1
                                    ? 'One piece looks close to something else'
                                    : `${copyFlags.length} pieces look close to something else`}
                            </h2>
                            {/*
                              Deliberately phrased as a question, not a charge.
                              This is a similarity measurement, and only the
                              writer can say whether it is a quotation, a
                              republished draft, or a problem.
                            */}
                            <p className="mt-1 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                Only you can see this. Often it is a long quotation or something you republished — dismiss it if so.
                            </p>

                            <ul className="mt-5 flex flex-col gap-3">
                                {copyFlags.map((flag) => (
                                    <li
                                        key={flag.id}
                                        className="flex flex-wrap items-center gap-3 rounded-[9px] border p-3"
                                        style={{ borderColor: 'var(--u-border)' }}
                                    >
                                        <span className="min-w-0 flex-1 text-sm">
                                            {flag.post.slug ? (
                                                <Link href={`/posts/${flag.post.slug}`} className="font-medium">
                                                    {flag.post.title}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">{flag.post.title}</span>
                                            )}
                                            <span style={{ color: 'var(--u-text-muted)' }}>
                                                {' '}
                                                {flag.kind === 'duplicate' ? 'is substantially the same as' : 'shares passages with'}{' '}
                                            </span>
                                            {flag.matched.slug ? (
                                                <Link href={`/posts/${flag.matched.slug}`} className="font-medium">
                                                    {flag.matched.title}
                                                </Link>
                                            ) : (
                                                <span className="font-medium">{flag.matched.title}</span>
                                            )}
                                            {flag.matched.handle && <span style={{ color: 'var(--u-text-muted)' }}> by @{flag.matched.handle}</span>}
                                        </span>

                                        <span className="shrink-0 text-xs tabular-nums" style={{ color: 'var(--u-text-muted)' }}>
                                            {Math.round(flag.similarity * 100)}%
                                        </span>

                                        <button
                                            type="button"
                                            onClick={() => router.post(`/copy-flags/${flag.id}/clear`, {}, { preserveScroll: true })}
                                            className="u-btn u-btn-ghost px-3 py-1 text-xs"
                                        >
                                            Dismiss
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>
                </Panel>
            )}

            {unreadLetters > 0 && (
                <Panel className="mb-12 flex flex-wrap items-center gap-5 p-6">
                    <Mail className="size-6 shrink-0" style={{ color: 'var(--u-accent)' }} />
                    <div className="min-w-0 flex-1">
                        <h2 className="text-lg font-semibold">
                            {unreadLetters} unread {unreadLetters === 1 ? 'letter' : 'letters'}
                        </h2>
                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Someone wrote to you privately about something you published.
                        </p>
                    </div>
                    <Link href="/letters" className="u-btn u-btn-primary">
                        Read them
                    </Link>
                </Panel>
            )}

            {!auth.user?.is_premium && (
                <Panel className="mb-12 flex flex-wrap items-center gap-5 p-6">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-lg font-semibold">You're on the free plan</h2>
                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Two universes and one persona. Premium opens every world and unlimited personas.
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
