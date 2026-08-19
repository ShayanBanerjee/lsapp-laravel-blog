import type { Ad } from '@/components/ad-slot';
import { AdSlot } from '@/components/ad-slot';
import { Chip, EmptyState, Panel } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import type { SeoPayload } from '@/components/seo-head';
import { SeoHead } from '@/components/seo-head';
import { ShareMenu } from '@/components/share-menu';
import { SupportWidget, type SupportConfig } from '@/components/support-widget';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, UniversePreview } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Highlighter, PenLine, UserMinus, UserPlus } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    persona: {
        handle: string;
        display_name: string;
        bio: string | null;
        avatar_path: string | null;
        universe: UniversePreview;
        joined_human: string | null;
        marks: number;
        published_count: number;
        followers: number;
        is_following: boolean;
        is_mine: boolean;
    };
    posts: Paginated<PostCardData>;
    ads: Ad[];
    seo: SeoPayload;
    support: SupportConfig;
}

export default function ProfileShow({ persona, posts, ads, seo, support }: Props) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title={`${persona.display_name} (@${persona.handle})`} />
            <SeoHead seo={seo} />

            <Panel className="mb-10 overflow-hidden">
                {persona.universe.hero_image && (
                    <div className="relative h-40 sm:h-56">
                        <img src={persona.universe.hero_image} alt="" className="size-full object-cover" />
                        <div className="absolute inset-0" style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 70%)' }} />
                    </div>
                )}

                <div className="p-6 sm:p-8">
                    <div className="flex flex-wrap items-start gap-5">
                        <div className="min-w-0 flex-1">
                            <h1 className="font-display text-4xl sm:text-5xl">{persona.display_name}</h1>
                            <p className="mt-1.5 text-lg" style={{ color: 'var(--u-text-muted)' }}>
                                @{persona.handle}
                            </p>

                            {persona.bio && (
                                <p className="mt-4 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                    {persona.bio}
                                </p>
                            )}

                            <div className="mt-5 flex flex-wrap items-center gap-2">
                                <Link href={`/universes/${persona.universe.slug}`}>
                                    <Chip tone="accent">{persona.universe.name}</Chip>
                                </Link>
                                {persona.joined_human && <Chip>Writing since {persona.joined_human}</Chip>}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            {persona.is_mine ? (
                                <Link href="/personas" className="u-btn u-btn-ghost">
                                    <PenLine className="size-4" />
                                    Edit this voice
                                </Link>
                            ) : (
                                auth.user && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/personas/${persona.handle}/follow`, {}, { preserveScroll: true })}
                                        className={persona.is_following ? 'u-btn u-btn-ghost' : 'u-btn u-btn-primary'}
                                    >
                                        {persona.is_following ? <UserMinus className="size-4" /> : <UserPlus className="size-4" />}
                                        {persona.is_following ? 'Following' : 'Follow'}
                                    </button>
                                )
                            )}
                            <ShareMenu url={seo.canonical} title={`${persona.display_name} on Inkfathom`} />
                        </div>
                    </div>

                    {/*
                     * Marks received, not views. A writer learns nothing from
                     * "412 people loaded this" — they learn from how many
                     * people stopped on a sentence.
                     */}
                    <dl className="mt-7 flex flex-wrap gap-x-10 gap-y-4">
                        <Stat label="Published" value={persona.published_count} />
                        <Stat label="Passages marked" value={persona.marks} icon={<Highlighter className="size-4" />} />
                        <Stat label="Followers" value={persona.followers} />
                    </dl>
                </div>
            </Panel>

            <div className="mb-10 max-w-md">
                <SupportWidget config={support} />
            </div>

            {posts.data.length === 0 ? (
                <EmptyState
                    title="Nothing published yet"
                    body={
                        persona.is_mine
                            ? 'Your first piece under this voice will appear here.'
                            : 'This voice has not published anything yet. Follow to hear when it does.'
                    }
                    action={
                        persona.is_mine ? (
                            <Link href="/write" className="u-btn u-btn-primary">
                                Start writing
                            </Link>
                        ) : undefined
                    }
                />
            ) : (
                <>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
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
                <div className="mt-12">
                    <AdSlot ads={ads} />
                </div>
            )}
        </SiteLayout>
    );
}

function Stat({ label, value, icon }: { label: string; value: number; icon?: React.ReactNode }) {
    return (
        <div>
            <dt className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                {label}
            </dt>
            <dd className="font-display mt-1 flex items-center gap-2 text-2xl tabular-nums">
                {icon}
                {value.toLocaleString()}
            </dd>
        </div>
    );
}
