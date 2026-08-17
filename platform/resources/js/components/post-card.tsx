import { Chip, Panel, Swatch } from '@/components/metal';
import { usePointerSpecular } from '@/hooks/use-motion';
import type { PostCard as PostCardData } from '@/types';
import { Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';

export function PostCard({ post, featured = false }: { post: PostCardData; featured?: boolean }) {
    const specularRef = usePointerSpecular<HTMLDivElement>();

    return (
        <Panel
            interactive
            grain={false}
            ref={specularRef}
            className={featured ? 'specular-pointer group sm:col-span-2 sm:row-span-2' : 'specular-pointer group'}
        >
            <Link href={`/posts/${post.slug}`} className="flex h-full flex-col">
                <div className={featured ? 'relative h-64 overflow-hidden sm:h-80' : 'relative h-44 overflow-hidden'}>
                    {post.cover_url ? (
                        <img src={post.cover_url} alt="" loading="lazy" decoding="async" className="img-zoom size-full object-cover" />
                    ) : (
                        <div
                            aria-hidden
                            className="size-full"
                            style={{
                                backgroundImage: post.universe
                                    ? `radial-gradient(ellipse 90% 130% at 22% 8%, ${post.universe.swatch.accent}3a, transparent 62%), linear-gradient(155deg, ${post.universe.swatch.metalBase}66, transparent 70%)`
                                    : undefined,
                            }}
                        />
                    )}

                    {/* Fade the photo into the card body so the seam disappears
                        in light and dark universes alike. */}
                    <div
                        aria-hidden
                        className="absolute inset-x-0 bottom-0 h-2/3"
                        style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent)' }}
                    />

                    {post.universe && (
                        <span
                            className="absolute top-3 left-3 inline-flex items-center gap-2 rounded-full px-2.5 py-1 text-[10px] font-semibold tracking-[0.14em] text-white uppercase backdrop-blur-sm"
                            style={{ backgroundColor: 'rgb(0 0 0 / 0.42)' }}
                        >
                            <Swatch swatch={post.universe.swatch} size={13} />
                            {post.universe.name}
                        </span>
                    )}

                    {post.status === 'draft' && (
                        <span className="absolute top-3 right-3">
                            <Chip tone="accent">Draft</Chip>
                        </span>
                    )}
                </div>

                <div className="-mt-8 flex flex-1 flex-col px-5 pb-5 sm:px-6 sm:pb-6">
                    <h3 className={featured ? 'font-display text-3xl leading-tight sm:text-4xl' : 'font-display text-xl leading-tight'}>
                        {post.title}
                    </h3>

                    {post.excerpt && (
                        <p
                            className={featured ? 'mt-3 line-clamp-4 text-base leading-relaxed' : 'mt-2.5 line-clamp-3 text-sm leading-relaxed'}
                            style={{ color: 'var(--u-text-muted)' }}
                        >
                            {post.excerpt}
                        </p>
                    )}

                    <div className="mt-auto flex items-center gap-3 pt-5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        {post.persona && <span className="font-medium">@{post.persona.handle}</span>}
                        {post.published_human && (
                            <>
                                <span aria-hidden>·</span>
                                <span>{post.published_human}</span>
                            </>
                        )}
                        <span className="ml-auto inline-flex items-center gap-1.5">
                            <Clock className="size-3.5" />
                            {post.reading_time} min
                        </span>
                    </div>
                </div>
            </Link>
        </Panel>
    );
}
