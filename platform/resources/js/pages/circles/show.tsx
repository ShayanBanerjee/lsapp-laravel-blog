import { Chip, EmptyState, Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Users } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    circle: {
        slug: string;
        name: string;
        tagline: string;
        description: string;
        members_count: number;
        posts_count: number;
        universe: UniversePreview | null;
    };
    posts: Paginated<PostCardData>;
    joined: boolean;
    members: { name: string }[];
}

export default function CircleShow({ circle, posts, joined, members }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title={circle.name} />

            <header className="mb-12 max-w-3xl">
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    {circle.universe && <Swatch swatch={circle.universe.swatch} size={40} />}
                    <Chip>
                        <Users className="size-3" />
                        {circle.members_count} {circle.members_count === 1 ? 'member' : 'members'}
                    </Chip>
                    {circle.universe && <Chip>{circle.universe.name}</Chip>}
                </div>

                <h1 className="font-display text-4xl sm:text-6xl">{circle.name}</h1>
                <p className="font-display mt-3 text-xl italic" style={{ color: 'var(--u-accent)' }}>
                    {circle.tagline}
                </p>
                <p className="mt-6 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    {circle.description}
                </p>

                <div className="mt-8">
                    {auth.user ? (
                        <button
                            type="button"
                            onClick={() => router.post(`/circles/${circle.slug}/membership`, {}, { preserveScroll: true })}
                            className={joined ? 'u-btn u-btn-ghost' : 'u-btn u-btn-primary'}
                        >
                            {joined ? 'Leave this circle' : 'Join this circle'}
                        </button>
                    ) : (
                        <Link href="/login" className="u-btn u-btn-primary">
                            Log in to join
                        </Link>
                    )}
                </div>
            </header>

            <Rail className="mb-12" />

            <div className="grid gap-12 lg:grid-cols-[1fr_280px]">
                <section>
                    <SectionHeading eyebrow="Shared here" title={`${posts.total} ${posts.total === 1 ? 'piece' : 'pieces'}`} />

                    {posts.data.length === 0 ? (
                        <EmptyState
                            title="Nothing shared yet"
                            body="No one has brought a piece into this circle. The first one sets the tone for everything after it."
                            action={
                                <Link href="/write" className="u-btn u-btn-primary">
                                    Write something
                                </Link>
                            }
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
                </section>

                <aside>
                    <h2 className="mb-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Who's here
                    </h2>

                    {members.length === 0 ? (
                        <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Nobody yet. Be the first.
                        </p>
                    ) : (
                        <Panel className="p-5">
                            <ul className="flex flex-col gap-2.5 text-sm">
                                {members.map((member, index) => (
                                    <li key={index} style={{ color: 'var(--u-text-muted)' }}>
                                        {member.name}
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
