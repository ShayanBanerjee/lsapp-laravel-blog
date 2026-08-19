import { Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, ExternalLink, Highlighter, Loader2, Plug, Send, Unplug } from 'lucide-react';
import { useState } from 'react';

interface Field {
    key: string;
    label: string;
    help: string;
    secret: boolean;
}

interface CatalogueEntry {
    key: string;
    label: string;
    blurb: string;
    credentials_url: string | null;
    fields: Field[];
    can_publish: boolean;
    takes_highlights: boolean;
}

interface ConnectionRow {
    provider: string;
    connected_human: string | null;
    last_used_human: string | null;
}

interface Props {
    catalogue: CatalogueEntry[];
    connections: Record<string, ConnectionRow>;
    obsidian: { label: string; blurb: string };
}

export default function Integrations({ catalogue, connections, obsidian }: Props) {
    return (
        <SiteLayout>
            <Head title="Connected services" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">Connected services</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Your writing should not be stuck here. Connect the tools you already use and send pieces — or the passages you mark — wherever you
                    keep them.
                </p>
                {/*
                  Stated up front, because it is the thing that makes a
                  cross-post button safe to press.
                */}
                <p className="mt-3 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Every destination receives a <strong>draft</strong>. Nothing here publishes to another audience or sends an email on your behalf.
                </p>
            </header>

            <div className="flex flex-col gap-4">
                {catalogue.map((entry) => (
                    <ServiceRow key={entry.key} entry={entry} connection={connections[entry.key]} />
                ))}
            </div>

            <Rail className="my-10" />

            <Panel className="p-6">
                <h2 className="font-display text-xl">{obsidian.label}</h2>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    {obsidian.blurb}
                </p>
            </Panel>

            <p className="mt-8 max-w-2xl text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                Medium and Pocket are not listed: Medium withdrew its publishing API in 2023 and Pocket shut down in 2025. There is nothing left to
                connect to in either case.
            </p>
        </SiteLayout>
    );
}

function ServiceRow({ entry, connection }: { entry: CatalogueEntry; connection?: ConnectionRow }) {
    const [open, setOpen] = useState(false);
    const connected = Boolean(connection);

    const { data, setData, post, processing, errors, reset } = useForm<{ credentials: Record<string, string> }>({
        credentials: Object.fromEntries(entry.fields.map((field) => [field.key, ''])),
    });

    return (
        <Panel className="p-5 sm:p-6">
            <div className="flex flex-wrap items-start gap-4">
                <div className="min-w-0 flex-1">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        {entry.label}
                        {connected && (
                            <span
                                className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] tracking-wide uppercase"
                                style={{ backgroundColor: 'var(--u-accent-soft)', color: 'var(--u-accent)' }}
                            >
                                <Check className="size-3" />
                                Connected
                            </span>
                        )}
                    </h2>
                    <p className="mt-1.5 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        {entry.blurb}
                    </p>
                    {connected && connection?.last_used_human && (
                        <p className="mt-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                            Last used {connection.last_used_human}
                        </p>
                    )}
                </div>

                <div className="flex shrink-0 flex-wrap gap-2">
                    {connected && entry.takes_highlights && (
                        <button
                            type="button"
                            onClick={() => router.post(`/integrations/${entry.key}/highlights`, {}, { preserveScroll: true })}
                            className="u-btn u-btn-ghost text-sm"
                        >
                            <Highlighter className="size-3.5" />
                            Send my marks
                        </button>
                    )}

                    {connected ? (
                        <button
                            type="button"
                            onClick={() => {
                                if (window.confirm(`Disconnect ${entry.label}? The stored credential is deleted.`)) {
                                    router.delete(`/settings/integrations/${entry.key}`, { preserveScroll: true });
                                }
                            }}
                            className="u-btn u-btn-ghost text-sm"
                        >
                            <Unplug className="size-3.5" />
                            Disconnect
                        </button>
                    ) : (
                        <button type="button" onClick={() => setOpen((value) => !value)} className="u-btn u-btn-primary text-sm">
                            <Plug className="size-3.5" />
                            {open ? 'Cancel' : 'Connect'}
                        </button>
                    )}
                </div>
            </div>

            {open && !connected && (
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        post(`/settings/integrations/${entry.key}`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                reset();
                                setOpen(false);
                            },
                        });
                    }}
                    className="mt-6 flex flex-col gap-4 border-t pt-6"
                    style={{ borderColor: 'var(--u-border)' }}
                >
                    {entry.fields.map((field) => (
                        <label key={field.key} className="flex flex-col gap-1.5">
                            <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                {field.label}
                            </span>
                            <input
                                type={field.secret ? 'password' : 'text'}
                                value={data.credentials[field.key] ?? ''}
                                onChange={(event) => setData('credentials', { ...data.credentials, [field.key]: event.target.value })}
                                autoComplete="off"
                                spellCheck={false}
                                className="u-field"
                            />
                            <span className="text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                {field.help}
                            </span>
                        </label>
                    ))}

                    {errors.credentials && (
                        <p className="text-sm" style={{ color: '#f0785a' }} role="alert">
                            {errors.credentials}
                        </p>
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <button type="submit" disabled={processing} className="u-btn u-btn-primary text-sm">
                            {processing ? <Loader2 className="size-3.5 animate-spin" /> : <Send className="size-3.5" />}
                            Verify and connect
                        </button>

                        {entry.credentials_url && (
                            <a
                                href={entry.credentials_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1.5 text-xs"
                                style={{ color: 'var(--u-text-muted)' }}
                            >
                                Where to find this
                                <ExternalLink className="size-3" />
                            </a>
                        )}
                    </div>

                    <p className="text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        We check the credential works before saving it, store it encrypted, and never send it back to your browser.
                    </p>
                </form>
            )}
        </Panel>
    );
}
