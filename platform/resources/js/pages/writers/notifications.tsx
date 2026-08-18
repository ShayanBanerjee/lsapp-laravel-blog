import { EmptyState, Panel } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Highlighter, MessageSquare, Trash2 } from 'lucide-react';

interface Row {
    id: number;
    type: string;
    quote: string | null;
    read: boolean;
    when: string | null;
    post: { slug: string; title: string } | null;
    actor: { handle: string; display_name: string } | null;
}

export default function Notifications({ notifications }: { notifications: Row[] }) {
    return (
        <SiteLayout>
            <Head title="Notifications" />

            <header className="mb-10 flex flex-wrap items-end justify-between gap-4">
                <div className="max-w-2xl">
                    <h1 className="font-display text-4xl sm:text-5xl">What landed</h1>
                    <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                        Which sentences readers stopped on, and what they answered. Never a total — a number tells you something was fine, and a
                        passage tells you what worked.
                    </p>
                </div>

                {notifications.length > 0 && (
                    <button type="button" onClick={() => router.delete('/notifications')} className="u-btn u-btn-ghost">
                        <Trash2 className="size-3.5" />
                        Clear
                    </button>
                )}
            </header>

            {notifications.length === 0 ? (
                <EmptyState title="Nothing yet" body="When a reader marks a passage or answers one, it appears here with the sentence itself." />
            ) : (
                <div className="flex flex-col gap-3">
                    {notifications.map((row) => (
                        <Panel key={row.id} className="flex flex-wrap items-start gap-4 p-5">
                            {row.type === 'mark' ? (
                                <Highlighter className="mt-0.5 size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                            ) : (
                                <MessageSquare className="mt-0.5 size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                            )}

                            <div className="min-w-0 flex-1">
                                <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                    {row.type === 'mark' ? 'A reader marked this passage' : `${row.actor?.display_name ?? 'A reader'} answered`}
                                    {row.post && (
                                        <>
                                            {' in '}
                                            <Link href={`/posts/${row.post.slug}`} className="hover:underline" style={{ color: 'var(--u-accent)' }}>
                                                {row.post.title}
                                            </Link>
                                        </>
                                    )}
                                </p>

                                {row.quote && <p className="mt-2 font-display text-lg leading-snug italic">“{row.quote}”</p>}
                            </div>

                            <span className="shrink-0 text-xs whitespace-nowrap" style={{ color: 'var(--u-text-muted)' }}>
                                {row.when}
                            </span>
                        </Panel>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}
