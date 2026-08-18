import { AdSlot, type Ad } from '@/components/ad-slot';
import { EmptyState, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    posts: Paginated<PostCardData>;
    filters: { universe: string | null; q: string | null };
    ads: Ad[];
}

export default function PostsIndex({ posts, filters, ads }: Props) {
    const { universeIndex } = usePage<SharedData>().props;
    const [term, setTerm] = useState(filters.q ?? '');

    // Debounced search — one request after typing settles, not one per keystroke.
    useEffect(() => {
        if (term === (filters.q ?? '')) return;

        const timer = window.setTimeout(() => {
            router.get('/posts', { q: term || undefined, universe: filters.universe || undefined }, { preserveState: true, replace: true });
        }, 350);

        return () => window.clearTimeout(timer);
    }, [term, filters.q, filters.universe]);

    const buildFilter = (slug: string | null) => ({
        q: filters.q || undefined,
        universe: slug || undefined,
    });

    return (
        <SiteLayout wide>
            <Head title="Read" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">Everything published</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    {posts.total} {posts.total === 1 ? 'piece' : 'pieces'} across every universe.
                </p>
            </header>

            <div className="mb-9 flex flex-col gap-5">
                <div className="relative max-w-md">
                    <Search
                        className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2"
                        style={{ color: 'var(--u-text-muted)' }}
                    />
                    <input
                        type="search"
                        value={term}
                        onChange={(event) => setTerm(event.target.value)}
                        placeholder="Search titles and excerpts…"
                        aria-label="Search posts"
                        className="u-field pl-10"
                    />
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href="/posts"
                        data={buildFilter(null)}
                        preserveState
                        className="u-btn u-btn-ghost"
                        style={!filters.universe ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                    >
                        All universes
                    </Link>
                    {universeIndex.map((universe: UniversePreview) => {
                        const active = filters.universe === universe.slug;

                        return (
                            <Link
                                key={universe.slug}
                                href="/posts"
                                data={buildFilter(universe.slug)}
                                preserveState
                                className="u-btn u-btn-ghost"
                                style={active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                            >
                                <Swatch swatch={universe.swatch} size={16} />
                                {universe.name}
                            </Link>
                        );
                    })}
                </div>
            </div>

            {posts.data.length === 0 ? (
                <EmptyState
                    title="Nothing here yet"
                    body={
                        filters.q || filters.universe
                            ? 'No pieces match that filter. Try widening the search or picking another universe.'
                            : 'No one has published anything yet. It could be you.'
                    }
                    action={
                        <Link href="/write" className="u-btn u-btn-primary">
                            Write the first piece
                        </Link>
                    }
                />
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.data.map((post) => (
                        <PostCard key={post.slug} post={post} />
                    ))}
                </div>
            )}

            <AdSlot ads={ads} className="mt-12" />

            {posts.links.length > 3 && (
                <nav className="mt-12 flex flex-wrap justify-center gap-1.5" aria-label="Pagination">
                    {posts.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                className="u-btn u-btn-ghost min-w-10"
                                style={link.active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span key={index} className="u-btn u-btn-ghost min-w-10 opacity-40" dangerouslySetInnerHTML={{ __html: link.label }} />
                        ),
                    )}
                </nav>
            )}
        </SiteLayout>
    );
}
