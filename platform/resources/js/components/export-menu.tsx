import { Panel } from '@/components/metal';
import { router } from '@inertiajs/react';
import { FileDown, FlaskConical, Landmark, Loader2, Send } from 'lucide-react';
import { useState } from 'react';

export interface ExportOption {
    format: string;
    label: string;
    url: string;
}

export interface CrosspostTarget {
    key: string;
    label: string;
}

/**
 * Taking a piece elsewhere.
 *
 * Plain links rather than fetch-and-save: the server sets a filename and a
 * content type, and a browser handles a download better than any amount of
 * JavaScript reimplementing one.
 */
export function ExportMenu({
    exports,
    canDeposit,
    crosspost = [],
    canStudio = false,
    slug,
}: {
    exports: ExportOption[];
    canDeposit: boolean;
    crosspost?: CrosspostTarget[];
    canStudio?: boolean;
    slug: string;
}) {
    const [depositing, setDepositing] = useState(false);
    const [sending, setSending] = useState<string | null>(null);

    if (exports.length === 0 && !canDeposit && crosspost.length === 0 && !canStudio) return null;

    return (
        <Panel className="p-5">
            <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                Take it with you
            </span>

            <div className="mt-4 flex flex-wrap gap-2">
                {exports.map((option) => (
                    <a key={option.format} href={option.url} className="u-btn u-btn-ghost text-sm">
                        <FileDown className="size-3.5" />
                        {option.label}
                    </a>
                ))}
            </div>

            {/*
              Markdown export doubles as the Obsidian integration — Obsidian has
              no cloud API, so a vault-shaped file is the whole of what can
              honestly exist.
            */}
            <p className="mt-4 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                Markdown carries YAML frontmatter, so it drops straight into an Obsidian vault.
            </p>

            {canStudio && (
                <div className="mt-5 border-t pt-5" style={{ borderColor: 'var(--u-border)' }}>
                    <a href={`/posts/${slug}/studio`} className="u-btn u-btn-ghost text-sm">
                        <FlaskConical className="size-3.5" />
                        Prepare for a journal
                    </a>
                    <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        IEEE, ACM, Springer Nature, Nature and arXiv — the manuscript in their LaTeX class, plus the metadata and checklist their
                        portal expects.
                    </p>
                </div>
            )}

            {crosspost.length > 0 && (
                <div className="mt-5 border-t pt-5" style={{ borderColor: 'var(--u-border)' }}>
                    <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Cross-post
                    </span>
                    <div className="mt-3 flex flex-wrap gap-2">
                        {crosspost.map((target) => (
                            <button
                                key={target.key}
                                type="button"
                                disabled={sending !== null}
                                onClick={() => {
                                    setSending(target.key);
                                    router.post(`/posts/${slug}/share/${target.key}`, {}, { preserveScroll: true, onFinish: () => setSending(null) });
                                }}
                                className="u-btn u-btn-ghost text-sm disabled:opacity-50"
                            >
                                {sending === target.key ? <Loader2 className="size-3.5 animate-spin" /> : <Send className="size-3.5" />}
                                {target.label}
                            </button>
                        ))}
                    </div>
                    <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Each one creates a draft over there, with the canonical link pointing back here.
                    </p>
                </div>
            )}

            {canDeposit && (
                <div className="mt-5 border-t pt-5" style={{ borderColor: 'var(--u-border)' }}>
                    <button
                        type="button"
                        disabled={depositing}
                        onClick={() => {
                            setDepositing(true);
                            router.post(`/posts/${slug}/deposit`, {}, { preserveScroll: true, onFinish: () => setDepositing(false) });
                        }}
                        className="u-btn u-btn-ghost text-sm"
                    >
                        {depositing ? <Loader2 className="size-3.5 animate-spin" /> : <Landmark className="size-3.5" />}
                        Deposit with Zenodo
                    </button>
                    <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Creates a draft deposition with a reserved DOI. You publish it on Zenodo — a published record cannot be withdrawn, so that
                        step is deliberately not automatic.
                    </p>
                </div>
            )}
        </Panel>
    );
}
