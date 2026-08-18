import { EmptyState } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Bookmark, Star } from 'lucide-react';

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

interface Props {
    posts: Paginated<PostCardData>;
    kind: 'saved' | 'starred';
    counts: { saved: number; starred: number };
}

export default function LibraryIndex({ posts, kind, counts }: Props) {
    const tabs = [
        { key: 'saved', label: 'Saved', icon: Bookmark, count: counts.saved },
        { key: 'starred', label: 'Starred', icon: Star, count: counts.starred },
    ] as const;

    return (
        <SiteLayout wide>
            <Head title="Your library" />

            <header className="mb-9">
                <h1 className="font-display text-4xl sm:text-5xl">Your library</h1>
                <p className="mt-3 max-w-2xl text-base" style={{ color: 'var(--u-text-muted)' }}>
                    Everything you set aside. Private to you — no one is told what you saved.
                </p>
            </header>

            <div className="mb-9 flex flex-wrap gap-2">
                {tabs.map((tab) => {
                    const active = kind === tab.key;

                    return (
                        <Link
                            key={tab.key}
                            href="/library"
                            data={{ kind: tab.key }}
                            preserveState
                            className="u-btn u-btn-ghost"
                            style={active ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                        >
                            <tab.icon className="size-4" />
                            {tab.label}
                            <span className="tabular-nums opacity-70">{tab.count}</span>
                        </Link>
                    );
                })}
            </div>

            {posts.data.length === 0 ? (
                <EmptyState
                    title={kind === 'starred' ? 'Nothing starred yet' : 'Your library is empty'}
                    body={
                        kind === 'starred'
                            ? 'Star the pieces you want to find again quickly — the ones worth a second reading.'
                            : 'Save anything you want to come back to. It lands here, and only you can see it.'
                    }
                    action={
                        <Link href="/posts" className="u-btn u-btn-primary">
                            Find something to read
                        </Link>
                    }
                />
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
