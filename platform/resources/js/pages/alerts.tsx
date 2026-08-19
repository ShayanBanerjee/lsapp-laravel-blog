import { EmptyState, Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';
import { CheckCheck, Highlighter, Mail, MessageSquareQuote, UserPlus } from 'lucide-react';

type AlertType = 'mark' | 'response' | 'letter' | 'follow';

interface AlertRow {
    id: number;
    type: AlertType;
    count: number;
    preview: string | null;
    unread: boolean;
    actor: { handle: string; display_name: string } | null;
    post: { slug: string; title: string } | null;
    when_human: string | null;
}

const ICONS: Record<AlertType, typeof Highlighter> = {
    mark: Highlighter,
    response: MessageSquareQuote,
    letter: Mail,
    follow: UserPlus,
};

/**
 * Wording is written per type and per count, rather than assembled from
 * fragments — "1 people marked" is the kind of seam that makes a product feel
 * automated at exactly the moment it is trying to say someone read you.
 */
function headline(alert: AlertRow): string {
    const who = alert.count > 1 ? `${alert.count} readers` : (alert.actor?.display_name ?? 'A reader');

    switch (alert.type) {
        case 'mark':
            return alert.count > 1 ? `${who} marked passages in` : `${who} marked a passage in`;
        case 'response':
            return alert.count > 1 ? `${who} answered` : `${who} answered`;
        case 'letter':
            return alert.count > 1 ? `${who} wrote to you` : `${who} wrote to you`;
        case 'follow':
            return alert.count > 1 ? `${who} started following you` : `${who} started following you`;
    }
}

export default function Alerts({ alerts }: { alerts: AlertRow[] }) {
    const unread = alerts.filter((alert) => alert.unread).length;

    return (
        <SiteLayout>
            <Head title="Activity" />

            <header className="mb-9 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="font-display text-4xl sm:text-5xl">Activity</h1>
                    <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Who stopped on a sentence, who answered, who wrote. Repeats on the same piece are counted together rather than stacked up.
                    </p>
                </div>

                {unread > 0 && (
                    <button type="button" onClick={() => router.post('/activity/read', {}, { preserveScroll: true })} className="u-btn u-btn-ghost">
                        <CheckCheck className="size-4" />
                        Mark all read
                    </button>
                )}
            </header>

            {alerts.length === 0 ? (
                <EmptyState
                    title="Nothing yet"
                    body="When someone marks a passage, answers a piece, writes you a letter or follows a voice, it lands here."
                    action={
                        <Link href="/write" className="u-btn u-btn-primary">
                            Write something
                        </Link>
                    }
                />
            ) : (
                <Panel className="divide-y" style={{ borderColor: 'var(--u-border)' }}>
                    {alerts.map((alert, index) => {
                        const Icon = ICONS[alert.type];

                        return (
                            <article key={alert.id} className="relative p-5 sm:p-6">
                                {index > 0 && <Rail className="absolute inset-x-0 top-0" />}

                                <div className="flex gap-4">
                                    <span
                                        aria-hidden
                                        className="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full"
                                        style={{
                                            backgroundColor: alert.unread ? 'var(--u-accent-soft)' : 'var(--u-surface-2)',
                                            color: alert.unread ? 'var(--u-accent)' : 'var(--u-text-muted)',
                                        }}
                                    >
                                        <Icon className="size-4" />
                                    </span>

                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm">
                                            <span style={{ color: 'var(--u-text)' }}>{headline(alert)}</span>
                                            {alert.post && (
                                                <>
                                                    {' '}
                                                    <Link
                                                        href={`/posts/${alert.post.slug}`}
                                                        className="font-medium"
                                                        style={{ color: 'var(--u-accent)' }}
                                                    >
                                                        {alert.post.title}
                                                    </Link>
                                                </>
                                            )}
                                            {alert.type === 'letter' && '.'}
                                        </p>

                                        {alert.preview && (
                                            <p
                                                className="mt-2 border-l-2 pl-3 text-sm leading-relaxed italic"
                                                style={{ borderColor: 'var(--u-accent)', color: 'var(--u-text-muted)' }}
                                            >
                                                {alert.preview}
                                            </p>
                                        )}

                                        <p className="mt-2 flex flex-wrap items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {alert.when_human}
                                            {alert.actor && (
                                                <>
                                                    <span aria-hidden>·</span>
                                                    <Link href={`/@${alert.actor.handle}`}>@{alert.actor.handle}</Link>
                                                </>
                                            )}
                                            {alert.type === 'letter' && (
                                                <>
                                                    <span aria-hidden>·</span>
                                                    <Link href="/letters">Read your letters</Link>
                                                </>
                                            )}
                                        </p>
                                    </div>

                                    {alert.unread && (
                                        <span
                                            aria-label="Unread"
                                            className="mt-2 size-2 shrink-0 rounded-full"
                                            style={{ backgroundColor: 'var(--u-accent)' }}
                                        />
                                    )}
                                </div>
                            </article>
                        );
                    })}
                </Panel>
            )}
        </SiteLayout>
    );
}
