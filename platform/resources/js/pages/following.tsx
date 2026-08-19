import { AdSlot, type Ad } from '@/components/ad-slot';
import { Chip, EmptyState, Panel, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, UniversePreview } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    posts: Paginated<PostCardData>;
    following: {
        personas: { handle: string; display_name: string; universe: UniversePreview }[];
        universes: UniversePreview[];
    };
    ads: Ad[];
}

export default function Following({ posts, following, ads }: Props) {
    const followsNothing = following.personas.length === 0 && following.universes.length === 0;

    return (
        <SiteLayout wide>
            <Head title="Following" />

            <header className="mb-9">
                <h1 className="font-display text-4xl sm:text-5xl">Following</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Only the voices and worlds you chose. No ranking, no suggestions, no algorithm deciding what you meant to follow — newest first,
                    and that is all.
                </p>
            </header>

            {followsNothing ? (
                <EmptyState
                    title="You're not following anyone yet"
                    body="Follow a persona from their profile, or a whole universe from its page, and their new work lands here."
                    action={
                        <div className="flex flex-wrap justify-center gap-3">
                            <Link href="/posts" className="u-btn u-btn-primary">
                                Find someone to read
                            </Link>
                            <Link href="/universes" className="u-btn u-btn-ghost">
                                Browse universes
                            </Link>
                        </div>
                    }
                />
            ) : (
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_280px]">
                    <div>
                        {posts.data.length === 0 ? (
                            <EmptyState
                                title="Nothing new yet"
                                body="The people and worlds you follow have not published since you last looked. That is not a failure of the feed — it is what an honest one looks like."
                                action={
                                    <Link href="/posts" className="u-btn u-btn-primary">
                                        Read something else
                                    </Link>
                                }
                            />
                        ) : (
                            <>
                                <div className="grid gap-5 sm:grid-cols-2">
                                    {posts.data.map((post, index) => (
                                        <Reveal key={post.slug} delay={Math.min(index * 60, 300)}>
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
                                                    className="u-btn u-btn-ghost px-3 py-1.5 text-sm"
                                                    style={link.active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ) : null,
                                        )}
                                    </nav>
                                )}
                            </>
                        )}

                        {ads.length > 0 && (
                            <div className="mt-10">
                                <AdSlot ads={ads} />
                            </div>
                        )}
                    </div>

                    <aside className="flex flex-col gap-5">
                        {following.personas.length > 0 && (
                            <Panel className="p-5">
                                <Label>Voices</Label>
                                <ul className="mt-3 flex flex-col gap-2">
                                    {following.personas.map((persona) => (
                                        <li key={persona.handle}>
                                            <Link href={`/@${persona.handle}`} className="flex items-center gap-3 rounded-[9px] px-2 py-1.5">
                                                <Swatch swatch={persona.universe.swatch} size={26} />
                                                <span className="min-w-0 flex-1">
                                                    <span className="block truncate text-sm font-medium">{persona.display_name}</span>
                                                    <span className="block truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                                        @{persona.handle}
                                                    </span>
                                                </span>
                                            </Link>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}

                        {following.universes.length > 0 && (
                            <Panel className="p-5">
                                <Label>Worlds</Label>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    {following.universes.map((universe) => (
                                        <Link key={universe.slug} href={`/universes/${universe.slug}`}>
                                            <Chip>{universe.name}</Chip>
                                        </Link>
                                    ))}
                                </div>
                            </Panel>
                        )}
                    </aside>
                </div>
            )}
        </SiteLayout>
    );
}

function Label({ children }: { children: string }) {
    return (
        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </span>
    );
}
