import { HeroImage } from '@/components/hero-image';
import { Chip, EmptyState, Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, Universe } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Heart, Lock } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    universe: Universe;
    posts: Paginated<PostCardData>;
    writers: { handle: string; display_name: string; bio: string | null }[];
    isFollowing: boolean;
}

export default function UniverseShow({ universe, posts, writers, isFollowing }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title={universe.name}>
                <meta name="description" content={universe.description} />
            </Head>

            {/* Full-bleed photographic hero, breaking out of the page gutter. */}
            <HeroImage src={universe.hero_image} credit={universe.hero_credit} className="-mx-5 -mt-10 mb-12 sm:-mx-8 sm:-mt-14">
                <div className="mx-auto flex min-h-[68vh] max-w-7xl flex-col justify-end px-5 pt-24 pb-12 sm:px-8">
                    <div className="mb-6 flex flex-wrap items-center gap-3">
                        <Swatch swatch={universe.swatch} size={44} />
                        <Chip>{universe.material}</Chip>
                        {universe.locked && (
                            <Chip tone="accent">
                                <Lock className="size-3" />
                                Locked on your plan
                            </Chip>
                        )}
                    </div>

                    <h1 className="font-display text-5xl sm:text-7xl">{universe.name}</h1>
                    <p className="font-display mt-4 text-xl italic" style={{ color: 'var(--u-accent)' }}>
                        {universe.tagline}
                    </p>
                    <p className="mt-6 max-w-2xl text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        {universe.description}
                    </p>

                    <div className="mt-9 flex flex-wrap gap-3">
                        {auth.user ? (
                            <button
                                type="button"
                                onClick={() => router.post(`/universes/${universe.slug}/follow`, {}, { preserveScroll: true })}
                                className={isFollowing ? 'u-btn u-btn-ghost' : 'u-btn u-btn-primary'}
                            >
                                <Heart className={isFollowing ? 'size-4 fill-current' : 'size-4'} />
                                {isFollowing ? 'Following' : 'Follow this universe'}
                            </button>
                        ) : (
                            <Link href="/login" className="u-btn u-btn-primary">
                                <Heart className="size-4" />
                                Log in to follow
                            </Link>
                        )}

                        {universe.locked ? (
                            <Link href="/upgrade" className="u-btn u-btn-ghost">
                                Unlock to write here
                            </Link>
                        ) : (
                            auth.user && (
                                <Link href="/personas" className="u-btn u-btn-ghost">
                                    Create a persona here
                                </Link>
                            )
                        )}
                    </div>
                </div>
            </HeroImage>

            <Rail className="mb-14" />

            <div className="grid gap-12 lg:grid-cols-[1fr_300px]">
                <section>
                    <SectionHeading eyebrow="Published" title={`${posts.total} ${posts.total === 1 ? 'piece' : 'pieces'}`} />

                    {posts.data.length === 0 ? (
                        <EmptyState
                            title={`${universe.name} is quiet`}
                            body="Nothing has been published in this world yet. The first piece here sets its tone."
                        />
                    ) : (
                        <div className="grid gap-5 sm:grid-cols-2">
                            {posts.data.map((post, index) => (
                                <Reveal key={post.slug} delay={Math.min(index * 70, 350)}>
                                    <PostCard post={post} />
                                </Reveal>
                            ))}
                        </div>
                    )}

                    {posts.links.length > 3 && (
                        <nav className="mt-10 flex flex-wrap gap-1.5" aria-label="Pagination">
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
                                    <span
                                        key={index}
                                        className="u-btn u-btn-ghost min-w-10 opacity-40"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </section>

                <aside>
                    <h2 className="mb-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Writers here
                    </h2>

                    {writers.length === 0 ? (
                        <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            No personas have settled in this world yet.
                        </p>
                    ) : (
                        <div className="flex flex-col gap-3">
                            {writers.map((writer) => (
                                <Panel key={writer.handle} className="p-4">
                                    <p className="text-sm font-semibold">{writer.display_name}</p>
                                    <p className="text-xs" style={{ color: 'var(--u-accent)' }}>
                                        @{writer.handle}
                                    </p>
                                    {writer.bio && (
                                        <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                            {writer.bio}
                                        </p>
                                    )}
                                </Panel>
                            ))}
                        </div>
                    )}
                </aside>
            </div>
        </SiteLayout>
    );
}
