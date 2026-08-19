import { ActionLink } from '@/components/action';
import { Panel, Rail } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { PenLine } from 'lucide-react';

interface Subject {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    hero_image: string | null;
    prompt: string | null;
    posts_count: number | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function CategoryShow({ category, posts }: { category: Subject; posts: Paginated<PostCardData> }) {
    return (
        <SiteLayout wide>
            <Head title={category.name} />

            <Panel className="mb-12 overflow-hidden">
                <div className="relative">
                    {category.hero_image && (
                        <div className="relative h-56 sm:h-72">
                            <img src={category.hero_image} alt="" className="size-full object-cover" />
                            <div
                                aria-hidden
                                className="absolute inset-0"
                                style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 60%)' }}
                            />
                        </div>
                    )}

                    <div className="p-7 sm:p-10">
                        <Link href="/categories" className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            ← All subjects
                        </Link>

                        {category.tagline && (
                            <p className="mt-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-accent)' }}>
                                {category.tagline}
                            </p>
                        )}

                        <h1 className="font-display mt-2 text-5xl sm:text-6xl">{category.name}</h1>

                        {category.description && (
                            <p className="mt-5 max-w-2xl text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                {category.description}
                            </p>
                        )}

                        {/*
                          The prompt is not decoration. The commonest reason a
                          subject stays empty is not that nobody has anything to
                          say — it is that nobody knows where to start.
                        */}
                        {category.prompt && (
                            <>
                                <Rail className="my-8 max-w-md" />
                                <div className="flex flex-wrap items-center gap-5">
                                    <p className="font-display max-w-xl text-2xl leading-snug italic">{category.prompt}</p>
                                    <ActionLink href="/write" icon={<PenLine className="size-4" />}>
                                        Write this
                                    </ActionLink>
                                </div>
                            </>
                        )}
                    </div>
                </div>
            </Panel>

            {posts.data.length === 0 ? (
                <Panel className="p-12 text-center">
                    <h2 className="font-display text-3xl">Nothing here yet</h2>
                    <p className="mx-auto mt-4 max-w-md text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        This subject is waiting for its first piece. Being first here means every reader who arrives later starts with your work.
                    </p>
                    <ActionLink href="/write" size="lg" className="mt-8" icon={<PenLine className="size-4" />}>
                        Write the first one
                    </ActionLink>
                </Panel>
            ) : (
                <>
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post, index) => (
                            <Reveal key={post.slug} delay={Math.min(index * 50, 300)}>
                                <PostCard post={post} />
                            </Reveal>
                        ))}
                    </div>

                    {posts.links.length > 3 && (
                        <nav className="mt-12 flex flex-wrap justify-center gap-2" aria-label="Pagination">
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
        </SiteLayout>
    );
}
