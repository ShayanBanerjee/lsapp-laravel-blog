import { EmptyState, SectionHeading } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    posts: Paginated<PostCardData>;
    following: { writers: number; universes: number };
}

export default function Following({ posts, following }: Props) {
    return (
        <SiteLayout>
            <Head title="Following" />

            <header className="mb-10 max-w-2xl">
                <h1 className="font-display text-4xl sm:text-5xl">Following</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    Newest first, from {following.writers} {following.writers === 1 ? 'writer' : 'writers'} and {following.universes}{' '}
                    {following.universes === 1 ? 'universe' : 'universes'}. In order, and with an end — nothing here is ranked for you, and the page
                    stops.
                </p>
            </header>

            {posts.data.length === 0 ? (
                <EmptyState
                    title="Nothing here yet"
                    body="Follow a writer or a universe and their newest pieces collect here."
                    action={
                        <Link href="/universes" className="u-btn u-btn-primary">
                            Browse universes
                        </Link>
                    }
                />
            ) : (
                <>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <PostCard key={post.slug} post={post} />
                        ))}
                    </div>

                    {/* Deliberately a pager, not an endless scroll. */}
                    <nav className="mt-12 flex flex-wrap justify-center gap-2">
                        {posts.links.map((link) => (
                            <Link
                                key={link.label}
                                href={link.url ?? '#'}
                                className="u-btn u-btn-ghost"
                                style={link.active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ))}
                    </nav>
                </>
            )}

            <SectionHeading eyebrow="Why it ends" title="No infinite feed" />
            <p className="max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                An endless feed is the mechanic readers came to text to escape. This one is chronological, unranked, and finite on purpose.
            </p>
        </SiteLayout>
    );
}
