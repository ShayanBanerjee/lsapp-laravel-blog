import { Action } from '@/components/action';
import { Chip, EmptyState, Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownToLine, Banknote, Clock, Heart, Info, TrendingUp } from 'lucide-react';

interface Amount {
    minor: number;
    formatted: string;
}

interface Props {
    balances: { lifetime: Amount; available: Amount; pending: Amount; platform_share: Amount };
    rate: { percent: string; basis_points: number };
    monthly: { month: string; label: string; net_minor: number; net: string }[];
    supporters: { name: string; total: string; total_minor: number; count: number }[];
    recent: {
        id: number;
        kind: string;
        from: string;
        gross: string;
        net: string;
        fee: string;
        message: string | null;
        post: { slug: string; title: string } | null;
        when_human: string | null;
    }[];
    payouts: { id: number; amount: string; status: string; paid_human: string | null }[];
    payoutAccount: { connected: boolean; status: string };
    thresholds: { minimum: string; hold_days: number };
    memberships: { handle: string | null; display_name: string | null; amount: string; renews_human: string | null }[];
}

export default function Earnings({ balances, rate, monthly, supporters, recent, payouts, payoutAccount, thresholds, memberships }: Props) {
    const peak = Math.max(1, ...monthly.map((m) => m.net_minor));
    const hasEarned = balances.lifetime.minor > 0;

    return (
        <SiteLayout wide>
            <Head title="Earnings" />

            <header className="mb-10">
                <p className="mb-3 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    Your work, paid for
                </p>
                <h1 className="font-display text-4xl sm:text-5xl">Earnings</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Readers can tip a piece or become a member of a voice. Everything below is stated in full, including our share — you could work it
                    out anyway, and finding it yourself would be worse than being told.
                </p>
            </header>

            <div className="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Balance label="Available" value={balances.available.formatted} tone="accent" icon={<Banknote className="size-4" />} />
                <Balance label="Clearing" value={balances.pending.formatted} icon={<Clock className="size-4" />} />
                <Balance label="Lifetime" value={balances.lifetime.formatted} icon={<TrendingUp className="size-4" />} />
                <Balance label={`Platform share (${rate.percent})`} value={balances.platform_share.formatted} muted />
            </div>

            <Panel className="mb-10 flex flex-wrap items-center gap-5 p-6">
                <Info className="size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                <div className="min-w-0 flex-1">
                    <p className="text-sm leading-relaxed">
                        We take <strong>{rate.percent}</strong> and pay the card fees out of it, so a small tip is still worth making. The rest is
                        yours. Money clears after {thresholds.hold_days} days — that is the window in which a card payment can still be reversed, and
                        paying out sooner would mean asking for it back.
                    </p>
                </div>
                <Action
                    variant="primary"
                    icon={<ArrowDownToLine className="size-4" />}
                    disabled={balances.available.minor === 0}
                    onClick={() => router.post('/earnings/withdraw', {}, { preserveScroll: true })}
                >
                    Withdraw {balances.available.formatted}
                </Action>
            </Panel>

            {!payoutAccount.connected && (
                <Panel className="mb-10 p-5">
                    <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        You have not connected a payout account yet. You can still receive money — it accrues here — but nothing can leave until an
                        account is connected. Balances under {thresholds.minimum} roll over rather than being sent, because the transfer would cost
                        more than it moves.
                    </p>
                </Panel>
            )}

            {!hasEarned ? (
                <EmptyState
                    title="Nothing yet"
                    body="When a reader tips a piece or becomes a member of one of your voices, it lands here — with the exact split shown for every payment."
                    action={
                        <Link href="/write" className="u-btn u-btn-primary">
                            Write something
                        </Link>
                    }
                />
            ) : (
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex flex-col gap-8">
                        <Panel className="p-6">
                            <Label>Last 12 months</Label>
                            {/*
                              Bars are drawn against the peak month rather than
                              a fixed scale, so a writer earning £4 a month sees
                              a readable chart rather than twelve flat lines.
                            */}
                            <div className="mt-6 flex h-40 items-end gap-2">
                                {monthly.map((month) => (
                                    <div key={month.month} className="group flex min-w-0 flex-1 flex-col items-center gap-2">
                                        <span
                                            className="w-full rounded-t-[4px] transition-[height] duration-700"
                                            style={{
                                                height: `${Math.max(2, (month.net_minor / peak) * 100)}%`,
                                                backgroundColor: month.net_minor > 0 ? 'var(--u-accent)' : 'var(--u-border)',
                                            }}
                                            title={`${month.label}: ${month.net}`}
                                        />
                                        <span className="text-[10px]" style={{ color: 'var(--u-text-muted)' }}>
                                            {month.label}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </Panel>

                        <Panel className="p-6">
                            <Label>Every payment, in full</Label>
                            <ul className="mt-5 flex flex-col gap-4">
                                {recent.map((row) => (
                                    <li key={row.id}>
                                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                                            <span className="text-sm font-medium">{row.from}</span>
                                            <Chip>{row.kind}</Chip>
                                            {row.post && (
                                                <Link
                                                    href={`/posts/${row.post.slug}`}
                                                    className="min-w-0 truncate text-sm"
                                                    style={{ color: 'var(--u-text-muted)' }}
                                                >
                                                    {row.post.title}
                                                </Link>
                                            )}
                                            <span className="ml-auto text-sm tabular-nums">
                                                <span style={{ color: 'var(--u-accent)' }}>{row.net}</span>
                                                <span style={{ color: 'var(--u-text-muted)' }}>
                                                    {' '}
                                                    of {row.gross} · fee {row.fee}
                                                </span>
                                            </span>
                                        </div>
                                        {row.message && (
                                            <p
                                                className="mt-2 border-l-2 pl-3 text-sm leading-relaxed italic"
                                                style={{ borderColor: 'var(--u-accent)', color: 'var(--u-text-muted)' }}
                                            >
                                                {row.message}
                                            </p>
                                        )}
                                        <p className="mt-1.5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {row.when_human}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        </Panel>
                    </div>

                    <aside className="flex flex-col gap-5">
                        {supporters.length > 0 && (
                            <Panel className="p-5">
                                <Label>Your readers</Label>
                                <ul className="mt-4 flex flex-col gap-3">
                                    {supporters.map((supporter, index) => (
                                        <li key={index} className="flex items-center gap-3">
                                            <Heart className="size-3.5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                                            <span className="min-w-0 flex-1 truncate text-sm">{supporter.name}</span>
                                            <span className="shrink-0 text-sm tabular-nums" style={{ color: 'var(--u-text-muted)' }}>
                                                {supporter.total}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}

                        {payouts.length > 0 && (
                            <Panel className="p-5">
                                <Label>Payouts</Label>
                                <ul className="mt-4 flex flex-col gap-2.5">
                                    {payouts.map((payout) => (
                                        <li key={payout.id} className="flex items-center gap-3 text-sm">
                                            <span className="tabular-nums">{payout.amount}</span>
                                            <Chip>{payout.status}</Chip>
                                            <span className="ml-auto text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                                {payout.paid_human ?? '—'}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}

                        {memberships.length > 0 && (
                            <Panel className="p-5">
                                <Label>You support</Label>
                                <ul className="mt-4 flex flex-col gap-2.5">
                                    {memberships.map((membership) => (
                                        <li key={membership.handle} className="text-sm">
                                            <Link href={`/@${membership.handle}`} className="font-medium">
                                                {membership.display_name}
                                            </Link>
                                            <span style={{ color: 'var(--u-text-muted)' }}> — {membership.amount}/month</span>
                                        </li>
                                    ))}
                                </ul>
                            </Panel>
                        )}
                    </aside>
                </div>
            )}

            <Rail className="my-12" />

            <p className="max-w-2xl text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                Rounding always favours you. Where our {rate.percent} share does not divide into whole pence, the spare penny goes to the writer.
            </p>
        </SiteLayout>
    );
}

function Balance({ label, value, tone, muted, icon }: { label: string; value: string; tone?: 'accent'; muted?: boolean; icon?: React.ReactNode }) {
    return (
        <Panel className="p-5">
            <span className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                {icon}
                {label}
            </span>
            <p
                className="font-display mt-2 text-3xl tabular-nums"
                style={{ color: tone === 'accent' ? 'var(--u-accent)' : muted ? 'var(--u-text-muted)' : 'var(--u-text)' }}
            >
                {value}
            </p>
        </Panel>
    );
}

function Label({ children }: { children: string }) {
    return (
        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </span>
    );
}
