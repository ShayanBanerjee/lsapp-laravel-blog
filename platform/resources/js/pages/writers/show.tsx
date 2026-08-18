import { Chip, EmptyState, Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, Universe } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Rss } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
}

interface Props {
    writer: {
        handle: string;
        display_name: string;
        bio: string | null;
        avatar_path: string | null;
        universe: Universe;
        published_posts_count: number;
    };
    posts: Paginated<PostCardData>;
    marked: { slug: string; title: string; quote: string; marks: number }[];
    isFollowing: boolean;
}

export default function WriterShow({ writer, posts, marked, isFollowing }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout>
            <Head title={writer.display_name} />

            <header className="mb-12 max-w-3xl">
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    <Swatch swatch={writer.universe.swatch} size={44} />
                    <Chip>{writer.universe.name}</Chip>
                </div>

                <h1 className="font-display text-4xl sm:text-6xl">{writer.display_name}</h1>
                <p className="mt-2 text-lg" style={{ color: 'var(--u-text-muted)' }}>
                    @{writer.handle}
                </p>

                {writer.bio && <p className="mt-5 text-base leading-relaxed">{writer.bio}</p>}

                {/*
                    Pieces published, and nothing about followers. A follower
                    count is standing, not writing — see STRATEGY.md.
                */}
                <div className="mt-6 flex flex-wrap items-center gap-4 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                    <span>
                        {writer.published_posts_count} {writer.published_posts_count === 1 ? 'piece' : 'pieces'} published
                    </span>

                    {auth.user && (
                        <button
                            type="button"
                            onClick={() => router.post(`/personas/${writer.handle}/follow`, {}, { preserveScroll: true })}
                            className="u-btn u-btn-ghost"
                            aria-pressed={isFollowing}
                            style={isFollowing ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                        >
                            <Rss className="size-3.5" />
                            {isFollowing ? 'Following' : 'Follow'}
                        </button>
                    )}
                </div>
            </header>

            {marked.length > 0 && (
                <section className="mb-14">
                    <SectionHeading eyebrow="What landed" title="Sentences readers stopped on" />
                    <div className="grid gap-3 sm:grid-cols-2">
                        {marked.map((passage, index) => (
                            <Panel key={`${passage.slug}-${index}`} className="p-5">
                                <p className="font-display text-lg leading-snug italic">“{passage.quote}”</p>
                                <div className="mt-3 flex min-w-0 items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    <Link href={`/posts/${passage.slug}`} className="min-w-0 truncate hover:underline">
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

            <Rail className="mb-10" />

            <SectionHeading eyebrow="Everything" title="Published work" />

            {posts.data.length === 0 ? (
                <EmptyState title="Nothing published yet" body="When this writer publishes, it will appear here." />
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.data.map((post) => (
                        <PostCard key={post.slug} post={post} />
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}
