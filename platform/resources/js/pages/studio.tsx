import { ActionLink } from '@/components/action';
import { Chip, Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpRight, Check, Download, FlaskConical, Info, Lock, X } from 'lucide-react';
import { useState } from 'react';

interface VenueRow {
    key: string;
    name: string;
    publisher: string;
    scope: string;
    document_class: string;
    editorial_system: string;
    guidelines_url: string;
    portal_url: string;
    checklist: string[];
}

interface Props {
    post: { slug: string; title: string; word_count: number; reading_time: number | null; published: boolean };
    venues: VenueRow[];
    author: { name: string; orcid: string | null };
    readiness: { label: string; ok: boolean; hint: string }[];
    canPrepare: boolean;
}

export default function Studio({ post, venues, author, readiness, canPrepare }: Props) {
    const [open, setOpen] = useState<string | null>(venues[0]?.key ?? null);

    return (
        <SiteLayout wide>
            <Head title={`Research studio — ${post.title}`} />

            <header className="mb-10">
                <Link href={`/posts/${post.slug}`} className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                    ← {post.title}
                </Link>
                <h1 className="font-display mt-4 text-4xl sm:text-5xl">Research studio</h1>
                <p className="mt-3 max-w-3xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Take this piece to a journal or conference. Each venue below gives you the manuscript in that publisher's own LaTeX class, the
                    metadata their portal will ask for field by field, a cover letter to rewrite, and a checklist from their author guidelines.
                </p>
            </header>

            {/*
              Said once, plainly, at the top. A tool that implied it could submit
              on your behalf would be lying, and a researcher would find out at
              the worst possible moment.
            */}
            <Panel className="mb-10 flex flex-wrap items-start gap-4 p-6">
                <Info className="mt-0.5 size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                <div className="min-w-0 flex-1">
                    <p className="text-sm font-semibold">Nothing here submits on your behalf, and nothing can</p>
                    <p className="mt-1.5 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        IEEE, ACM, Springer Nature and Nature all receive manuscripts through editorial systems — ScholarOne, Editorial Manager,
                        Snapp, eJournalPress — and none of them offers a third-party submission API. Any product with a "submit to IEEE" button is
                        either doing something else or not doing anything. What this removes is the day of reformatting; you upload the package
                        yourself, and we link you straight to the right portal.
                    </p>
                </div>
            </Panel>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div className="flex flex-col gap-4">
                    {venues.map((venue) => (
                        <Panel key={venue.key} className="overflow-hidden">
                            <button
                                type="button"
                                onClick={() => setOpen(open === venue.key ? null : venue.key)}
                                aria-expanded={open === venue.key}
                                className="w-full p-6 text-left"
                            >
                                <div className="flex flex-wrap items-center gap-3">
                                    <h2 className="font-display-sm text-xl">{venue.name}</h2>
                                    <Chip>{venue.document_class}</Chip>
                                    <span className="ml-auto text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        {open === venue.key ? 'Hide' : 'Details'}
                                    </span>
                                </div>
                                <p className="mt-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                    {venue.scope}
                                </p>
                            </button>

                            {open === venue.key && (
                                <div className="border-t px-6 pb-6" style={{ borderColor: 'var(--u-border)' }}>
                                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                        <Fact label="Publisher" value={venue.publisher} />
                                        <Fact label="Submitted through" value={venue.editorial_system} />
                                    </dl>

                                    <Rail className="my-5" />

                                    <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                        Before you upload
                                    </span>
                                    <ul className="mt-3 flex flex-col gap-2">
                                        {venue.checklist.map((item) => (
                                            <li key={item} className="flex gap-2.5 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                                <span
                                                    aria-hidden
                                                    className="mt-2 size-1 shrink-0 rounded-full"
                                                    style={{ backgroundColor: 'var(--u-accent)' }}
                                                />
                                                {item}
                                            </li>
                                        ))}
                                    </ul>

                                    <div className="mt-6 flex flex-wrap items-center gap-3">
                                        {canPrepare ? (
                                            <a href={`/posts/${post.slug}/studio/${venue.key}`} className="u-btn u-btn-primary">
                                                <Download className="size-4" />
                                                Prepare package
                                            </a>
                                        ) : (
                                            <ActionLink href="/upgrade" icon={<Lock className="size-4" />}>
                                                Premium
                                            </ActionLink>
                                        )}

                                        <a href={venue.portal_url} target="_blank" rel="noopener noreferrer" className="u-btn u-btn-ghost">
                                            Open the portal
                                            <ArrowUpRight className="size-3.5" />
                                        </a>

                                        <a
                                            href={venue.guidelines_url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-xs"
                                            style={{ color: 'var(--u-text-muted)' }}
                                        >
                                            Author guidelines (authoritative)
                                        </a>
                                    </div>
                                </div>
                            )}
                        </Panel>
                    ))}
                </div>

                <aside className="flex flex-col gap-5">
                    <Panel className="p-5">
                        <span
                            className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase"
                            style={{ color: 'var(--u-text-muted)' }}
                        >
                            <FlaskConical className="size-3.5" />
                            This manuscript
                        </span>

                        <dl className="mt-4 flex flex-col gap-2.5 text-sm">
                            <Row label="Author" value={author.name} />
                            <Row label="ORCID" value={author.orcid ?? 'Not linked'} />
                            <Row label="Words" value={post.word_count.toLocaleString()} />
                        </dl>
                    </Panel>

                    <Panel className="p-5">
                        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                            Readiness
                        </span>
                        {/*
                          Named checks, not a percentage. A single readiness
                          score would be invented; these are the specific things
                          that actually stop a submission.
                        */}
                        <ul className="mt-4 flex flex-col gap-3">
                            {readiness.map((item) => (
                                <li key={item.label} className="flex gap-2.5">
                                    {item.ok ? (
                                        <Check className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                                    ) : (
                                        <X className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--u-text-muted)' }} />
                                    )}
                                    <span className="min-w-0">
                                        <span className="block text-sm">{item.label}</span>
                                        {!item.ok && (
                                            <span className="mt-0.5 block text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                                {item.hint}
                                            </span>
                                        )}
                                    </span>
                                </li>
                            ))}
                        </ul>

                        {!author.orcid && (
                            <ActionLink href="/settings/profile" variant="ghost" size="sm" className="mt-5 w-full">
                                Link your ORCID
                            </ActionLink>
                        )}
                    </Panel>
                </aside>
            </div>
        </SiteLayout>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                {label}
            </dt>
            <dd className="mt-1 text-sm">{value}</dd>
        </div>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline gap-3">
            <dt style={{ color: 'var(--u-text-muted)' }}>{label}</dt>
            <dd className="ml-auto min-w-0 truncate text-right">{value}</dd>
        </div>
    );
}
