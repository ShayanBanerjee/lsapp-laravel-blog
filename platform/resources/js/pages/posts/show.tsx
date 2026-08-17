import { Chip, Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, Universe } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Clock, Pencil, Trash2 } from 'lucide-react';

interface Props {
    post: PostCardData & { body: string; can: { update: boolean; delete: boolean } };
    universe: Universe | null;
    related: PostCardData[];
}

export default function PostShow({ post, universe, related }: Props) {
    const destroy = () => {
        if (window.confirm(`Delete “${post.title}”? This cannot be undone.`)) {
            router.delete(`/posts/${post.slug}`);
        }
    };

    return (
        <SiteLayout>
            <Head title={post.title}>
                <meta name="description" content={post.excerpt ?? ''} />
            </Head>

            <Link href="/posts" className="mb-8 inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                <ArrowLeft className="size-4" />
                All pieces
            </Link>

            <article>
                <header className="mb-10">
                    <div className="mb-5 flex flex-wrap items-center gap-3">
                        {universe && (
                            <Link
                                href={`/universes/${universe.slug}`}
                                className="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase"
                                style={{ color: 'var(--u-text-muted)' }}
                            >
                                <Swatch swatch={universe.swatch} size={16} />
                                {universe.name}
                            </Link>
                        )}
                        {post.status === 'draft' && <Chip tone="accent">Draft — only you can see this</Chip>}
                    </div>

                    <h1 className="font-display text-4xl leading-[1.08] sm:text-6xl">{post.title}</h1>

                    <div className="mt-7 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        {post.persona && (
                            <span>
                                by <span style={{ color: 'var(--u-text)' }}>{post.persona.display_name}</span> @{post.persona.handle}
                            </span>
                        )}
                        {post.published_human && (
                            <>
                                <span aria-hidden>·</span>
                                <time>{post.published_human}</time>
                            </>
                        )}
                        <span aria-hidden>·</span>
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="size-3.5" />
                            {post.reading_time} min read
                        </span>

                        {(post.can.update || post.can.delete) && (
                            <span className="ml-auto flex gap-2">
                                {post.can.update && (
                                    <Link href={`/posts/${post.slug}/edit`} className="u-btn u-btn-ghost">
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </Link>
                                )}
                                {post.can.delete && (
                                    <button type="button" onClick={destroy} className="u-btn u-btn-ghost">
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </button>
                                )}
                            </span>
                        )}
                    </div>
                </header>

                {post.cover_url && (
                    <Panel className="mb-12" grain={false}>
                        <img src={post.cover_url} alt="" className="w-full object-cover" />
                    </Panel>
                )}

                {/*
                  Body is sanitized server-side to a tag allowlist on write
                  (App\Support\HtmlSanitizer), which is what makes this safe.
                */}
                <div className="prose-u mx-auto max-w-2xl" dangerouslySetInnerHTML={{ __html: post.body }} />
            </article>

            {universe && (
                <>
                    <Rail className="my-16" />
                    <Panel className="p-7 sm:p-9">
                        <div className="flex flex-wrap items-center gap-5">
                            <Swatch swatch={universe.swatch} size={54} />
                            <div className="min-w-0 flex-1">
                                <h3 className="font-display text-2xl">{universe.name}</h3>
                                <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                    {universe.description}
                                </p>
                            </div>
                            <Link href={`/universes/${universe.slug}`} className="u-btn u-btn-primary">
                                Enter {universe.name}
                            </Link>
                        </div>
                    </Panel>
                </>
            )}

            {related.length > 0 && (
                <section className="mt-16">
                    <SectionHeading eyebrow="Same world" title="More from this universe" />
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {related.map((item) => (
                            <PostCard key={item.slug} post={item} />
                        ))}
                    </div>
                </section>
            )}
        </SiteLayout>
    );
}
