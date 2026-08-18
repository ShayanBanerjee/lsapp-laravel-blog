import { Panel, Rail, SectionHeading } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Download, Link2, Loader2, RefreshCw, Unlink } from 'lucide-react';
import type { FormEvent } from 'react';

interface Connection {
    connected: boolean;
    last_synced_human: string | null;
    last_error: string | null;
}

interface Props {
    connections: Record<string, Connection>;
    markCount: number;
}

export default function Integrations({ connections, markCount }: Props) {
    const readwise = connections.readwise;

    const { data, setData, post, processing, errors, reset } = useForm({ service: 'readwise', token: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/settings/integrations', { preserveScroll: true, onSuccess: () => reset('token') });
    };

    return (
        <SiteLayout>
            <Head title="Integrations" />

            <header className="mb-10 max-w-2xl">
                <h1 className="font-display text-4xl">Integrations</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    Your marks and your writing are yours. These are the ways to get them out.
                </p>
            </header>

            <SectionHeading eyebrow="Works right now" title="Export" />

            <Panel className="mb-12 max-w-2xl p-6">
                <p className="text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Every piece can be downloaded as Markdown with YAML frontmatter, from the Share menu when you are reading it. The same file drops
                    straight into an Obsidian vault, Bear, Logseq or anything else that reads Markdown.
                </p>
                <p className="mt-4 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Obsidian has no cloud API — a vault is a folder of files on your disk. A file in their format is not a lesser version of an
                    integration; it is the integration. No account, no token, nothing to connect.
                </p>
                <p className="mt-4 flex items-center gap-2 text-sm" style={{ color: 'var(--u-accent)' }}>
                    <Download className="size-4" />
                    Available on every piece you can read.
                </p>
            </Panel>

            <SectionHeading eyebrow="Needs your token" title="Readwise" />

            <Panel className="max-w-2xl p-6">
                <p className="mb-5 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Readwise stores highlights with their source, which is exactly what a mark is here — so nothing has to be flattened or invented to
                    move between the two. Sending pushes every passage you have marked.
                </p>

                {readwise?.connected ? (
                    <div className="flex flex-col gap-4">
                        <p className="flex items-center gap-2 text-sm" style={{ color: 'var(--u-accent)' }}>
                            <Check className="size-4" />
                            Connected
                            {readwise.last_synced_human && (
                                <span style={{ color: 'var(--u-text-muted)' }}>· last sent {readwise.last_synced_human}</span>
                            )}
                        </p>

                        {readwise.last_error && (
                            <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                {readwise.last_error}
                            </p>
                        )}

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={() => router.post('/settings/integrations/sync', {}, { preserveScroll: true })}
                                className="u-btn u-btn-primary"
                            >
                                <RefreshCw className="size-4" />
                                Send {markCount} {markCount === 1 ? 'mark' : 'marks'}
                            </button>

                            <button
                                type="button"
                                onClick={() => router.delete('/settings/integrations/readwise', { preserveScroll: true })}
                                className="u-btn u-btn-ghost"
                            >
                                <Unlink className="size-4" />
                                Disconnect
                            </button>
                        </div>
                    </div>
                ) : (
                    <form onSubmit={submit} className="flex flex-col gap-3">
                        <label htmlFor="readwise-token" className="text-sm">
                            Access token
                        </label>
                        <input
                            id="readwise-token"
                            type="password"
                            value={data.token}
                            onChange={(event) => setData('token', event.target.value)}
                            placeholder="From readwise.io/access_token"
                            className="u-field"
                            autoComplete="off"
                        />
                        {errors.token && <p className="text-sm text-red-400">{errors.token}</p>}

                        <button type="submit" disabled={processing || data.token === ''} className="u-btn u-btn-primary self-start">
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Link2 className="size-4" />}
                            Connect
                        </button>
                    </form>
                )}
            </Panel>

            <Rail className="my-12" />

            <SectionHeading eyebrow="Considered and rejected" title="What is not here, and why" />
            <ul className="flex max-w-2xl flex-col gap-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                <li>
                    <strong>Medium</strong> — the publishing API was deprecated in 2023. There is nothing left to call.
                </li>
                <li>
                    <strong>Pocket</strong> — shut down in 2025.
                </li>
                <li>
                    <strong>Direct submission to IEEE or Springer</strong> — manuscripts go through editorial systems that publish no third-party
                    submission API. A button promising it could not work, so there is not one.
                </li>
            </ul>
        </SiteLayout>
    );
}
