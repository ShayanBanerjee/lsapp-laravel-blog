import { EmptyState } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    category: { slug: string; name: string; description: string | null; posts_count: number | null };
    posts: Paginated<PostCardData>;
}

export default function CategoryShow({ category, posts }: Props) {
    return (
        <SiteLayout wide>
            <Head title={category.name}>
                <meta name="description" content={category.description ?? `Writing about ${category.name}.`} />
            </Head>

            <Link href="/categories" className="mb-8 inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                <ArrowLeft className="size-4" />
                All subjects
            </Link>

            <header className="mb-10 max-w-3xl">
                <h1 className="font-display text-4xl sm:text-6xl">{category.name}</h1>
                {category.description && (
                    <p className="mt-4 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        {category.description}
                    </p>
                )}
            </header>

            {posts.data.length === 0 ? (
                <EmptyState title="Nothing here yet" body="No one has published in this subject yet. The first piece sets its tone." />
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {posts.data.map((post, index) => (
                        <Reveal key={post.slug} delay={Math.min(index * 60, 300)}>
                            <PostCard post={post} />
                        </Reveal>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}
