import { Action, ActionLink } from '@/components/action';
import { Chip, EmptyState, Panel, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Highlighter, PenLine, Plus, Users } from 'lucide-react';

interface Circle {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    members_count: number;
    posts_count: number;
    universe: UniversePreview | null;
    hero_image: string | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    circle: Circle;
    posts: Paginated<PostCardData>;
    joined: boolean;
    members: { name: string }[];
    passages: { quote: string; marks: number; slug: string; title: string }[];
}

export default function CircleShow({ circle, posts, joined, members, passages }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title={circle.name} />

            <Panel className="mb-10 overflow-hidden">
                {circle.hero_image && (
                    <div className="relative h-44 sm:h-60">
                        <img src={circle.hero_image} alt="" className="size-full object-cover opacity-70" />
                        <div
                            aria-hidden
                            className="absolute inset-0"
                            style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 65%)' }}
                        />
                    </div>
                )}

                <div className="p-7 sm:p-9">
                    <Link href="/circles" className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        ← All circles
                    </Link>

                    <div className="mt-5 flex flex-wrap items-start gap-5">
                        {circle.universe && <Swatch swatch={circle.universe.swatch} size={52} />}

                        <div className="min-w-0 flex-1">
                            <h1 className="font-display text-4xl sm:text-5xl">{circle.name}</h1>
                            {circle.tagline && (
                                <p className="mt-2 text-lg" style={{ color: 'var(--u-accent)' }}>
                                    {circle.tagline}
                                </p>
                            )}
                        </div>

                        {auth.user && (
                            <Action
                                variant={joined ? 'ghost' : 'primary'}
                                icon={joined ? <Check className="size-4" /> : <Plus className="size-4" />}
                                onClick={() => router.post(`/circles/${circle.slug}/membership`, {}, { preserveScroll: true })}
                            >
                                {joined ? 'Member' : 'Join this circle'}
                            </Action>
                        )}
                    </div>

                    {circle.description && (
                        <p className="mt-6 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {circle.description}
                        </p>
                    )}

                    <div className="mt-7 flex flex-wrap gap-2">
                        <Chip>
                            <Users className="size-3" />
                            {circle.members_count} {circle.members_count === 1 ? 'member' : 'members'}
                        </Chip>
                        <Chip>
                            {circle.posts_count} {circle.posts_count === 1 ? 'piece' : 'pieces'}
                        </Chip>
                        {circle.universe && (
                            <Link href={`/universes/${circle.universe.slug}`}>
                                <Chip tone="accent">{circle.universe.name}</Chip>
                            </Link>
                        )}
                    </div>
                </div>
            </Panel>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div>
                    {posts.data.length === 0 ? (
                        <EmptyState
                            title="Nothing shared here yet"
                            body="This circle has no pieces in it. Being first means everyone who joins later starts with your work."
                            action={
                                <ActionLink href="/write" icon={<PenLine className="size-4" />}>
                                    Write something
                                </ActionLink>
                            }
                        />
                    ) : (
                        <>
                            <div className="grid gap-5 sm:grid-cols-2">
                                {posts.data.map((post, index) => (
                                    <Reveal key={post.slug} delay={Math.min(index * 50, 250)}>
                                        <PostCard post={post} />
                                    </Reveal>
                                ))}
                            </div>

                            {posts.links.length > 3 && (
                                <nav className="mt-10 flex flex-wrap justify-center gap-2" aria-label="Pagination">
                                    {posts.links.map((link) =>
                                        link.url ? (
                                            <Link
                                                key={link.label}
                                                href={link.url}
                                                className="u-btn u-btn-ghost u-btn-sm"
                                                style={link.active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        ) : null,
                                    )}
                                </nav>
                            )}
                        </>
                    )}
                </div>

                <aside className="flex flex-col gap-5">
                    {/*
                      What the room stopped on. This is the circle's actual
                      character — far more informative than a member list.
                    */}
                    {passages.length > 0 && (
                        <Panel className="p-5">
                            <span
                                className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase"
                                style={{ color: 'var(--u-text-muted)' }}
                            >
                                <Highlighter className="size-3.5" />
                                What this circle stopped on
                            </span>

                            <ul className="mt-5 flex flex-col gap-5">
                                {passages.map((passage, index) => (
                                    <li key={index}>
                                        <Link href={`/posts/${passage.slug}`}>
                                            <blockquote className="font-display text-base leading-snug italic">“{passage.quote}”</blockquote>
                                        </Link>
                                        <p className="mt-1.5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {passage.marks} {passage.marks === 1 ? 'reader' : 'readers'} · {passage.title}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}

                    {members.length > 0 && (
                        <Panel className="p-5">
                            <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                Who is here
                            </span>
                            <ul className="mt-4 flex flex-wrap gap-2">
                                {members.map((member, index) => (
                                    <li key={index}>
                                        <Chip>{member.name}</Chip>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    )}
                </aside>
            </div>
        </SiteLayout>
    );
}
