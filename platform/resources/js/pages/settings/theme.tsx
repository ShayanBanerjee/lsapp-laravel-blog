import { Chip, EmptyState, Panel, SectionHeading } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { Universe } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Palette, RotateCcw, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface SavedTheme {
    id: number;
    name: string;
    universe: string | null;
    overrides: Record<string, string>;
    is_active: boolean;
}

interface Props {
    isPremium: boolean;
    themes: SavedTheme[];
    editableTokens: string[];
    base: Universe | null;
    universes: { slug: string; name: string }[];
}

/** Turn `metalBase` into `Metal base` for a label. */
function humanize(token: string) {
    return token.replace(/([A-Z])/g, ' $1').replace(/^./, (c) => c.toUpperCase());
}

export default function ThemeEditor({ isPremium, themes, editableTokens, base, universes }: Props) {
    const baseTheme = (base?.theme ?? {}) as Record<string, string>;

    const [draft, setDraft] = useState<Record<string, string>>(() =>
        Object.fromEntries(editableTokens.map((token) => [token, baseTheme[token] ?? '#000000'])),
    );

    const { data, setData, processing, errors } = useForm({
        name: 'My palette',
        universe: universes[0]?.slug ?? '',
        overrides: {} as Record<string, string>,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        // Send the current swatches, not form state — the pickers are the truth.
        router.post('/settings/theme', { ...data, overrides: draft }, { preserveScroll: true });
    };

    if (!isPremium) {
        return (
            <SiteLayout>
                <Head title="Theme editor" />
                <EmptyState
                    title="The theme editor is part of premium"
                    body="Premium unlocks the four paid worlds, unlimited personas, short handles, and this — a palette of your own layered over any universe you can write in."
                    action={
                        <Link href="/upgrade" className="u-btn u-btn-primary">
                            See premium
                        </Link>
                    }
                />
            </SiteLayout>
        );
    }

    return (
        <SiteLayout>
            <Head title="Theme editor" />

            <header className="mb-10 max-w-2xl">
                <h1 className="font-display text-4xl">Theme editor</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    A palette of your own, layered over a universe you can write in. The preview below is drawn with the real tokens, so what you see
                    is what the platform becomes.
                </p>
            </header>

            <form onSubmit={submit} className="mb-12 grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                <div className="min-w-0">
                    <SectionHeading eyebrow="Colours" title="Tokens" />

                    <div className="grid gap-3 sm:grid-cols-2">
                        {editableTokens.map((token) => (
                            <label key={token} className="flex min-w-0 items-center gap-3 text-sm">
                                <input
                                    type="color"
                                    value={draft[token] ?? '#000000'}
                                    onChange={(event) => setDraft({ ...draft, [token]: event.target.value })}
                                    className="size-9 shrink-0 cursor-pointer rounded-md border-0 bg-transparent p-0"
                                    aria-label={humanize(token)}
                                />
                                <span className="min-w-0 flex-1 truncate">{humanize(token)}</span>
                                <span className="shrink-0 font-mono text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    {draft[token]}
                                </span>
                            </label>
                        ))}
                    </div>

                    <div className="mt-6 flex flex-wrap items-end gap-3">
                        <div className="min-w-0">
                            <label htmlFor="theme-name" className="mb-2 block text-sm">
                                Name
                            </label>
                            <input
                                id="theme-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                className="u-field"
                            />
                        </div>

                        <div className="min-w-0">
                            <label htmlFor="theme-universe" className="mb-2 block text-sm">
                                Based on
                            </label>
                            <select
                                id="theme-universe"
                                value={data.universe}
                                onChange={(event) => setData('universe', event.target.value)}
                                className="u-field"
                            >
                                {universes.map((universe) => (
                                    <option key={universe.slug} value={universe.slug}>
                                        {universe.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <button type="submit" disabled={processing} className="u-btn u-btn-primary">
                            <Palette className="size-4" />
                            Save palette
                        </button>
                    </div>

                    {errors.overrides && <p className="mt-3 text-sm text-red-400">{errors.overrides}</p>}
                </div>

                {/* Preview painted with the draft tokens directly. */}
                <div
                    className="min-w-0 rounded-[14px] border p-6"
                    style={{
                        background: draft.bg,
                        borderColor: draft.border,
                        color: draft.text,
                    }}
                >
                    <p className="text-[11px] tracking-[0.18em] uppercase" style={{ color: draft.textMuted }}>
                        Preview
                    </p>
                    <h2 className="font-display mt-2 text-2xl" style={{ color: draft.text }}>
                        Every writer contains more than one person.
                    </h2>
                    <p className="mt-3 text-sm leading-relaxed" style={{ color: draft.textMuted }}>
                        Readers mark the exact sentence that landed, so writers finally know which line did the work.
                    </p>
                    <span
                        className="mt-5 inline-block rounded-[10px] px-4 py-2 text-sm"
                        style={{ background: draft.accent, color: draft.accentFg }}
                    >
                        Start writing
                    </span>
                </div>
            </form>

            <SectionHeading eyebrow="Saved" title="Your palettes" />

            {themes.length === 0 ? (
                <EmptyState title="No palettes yet" body="Save one above and it appears here, ready to apply." />
            ) : (
                <div className="flex flex-col gap-3">
                    {themes.map((theme) => (
                        <Panel key={theme.id} className="flex flex-wrap items-center gap-4 p-5">
                            <span className="flex shrink-0 gap-1">
                                {Object.values(theme.overrides)
                                    .slice(0, 5)
                                    .map((colour, index) => (
                                        <span key={index} className="size-6 rounded-md" style={{ background: colour }} />
                                    ))}
                            </span>

                            <span className="min-w-0 flex-1 truncate">{theme.name}</span>

                            {theme.is_active && <Chip tone="accent">Active</Chip>}

                            <span className="flex shrink-0 gap-2">
                                {theme.is_active ? (
                                    <button
                                        type="button"
                                        onClick={() => router.delete('/settings/theme/active', { preserveScroll: true })}
                                        className="u-btn u-btn-ghost"
                                    >
                                        <RotateCcw className="size-3.5" />
                                        Revert
                                    </button>
                                ) : (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/settings/theme/${theme.id}/activate`, {}, { preserveScroll: true })}
                                        className="u-btn u-btn-ghost"
                                    >
                                        <Check className="size-3.5" />
                                        Apply
                                    </button>
                                )}

                                <button
                                    type="button"
                                    onClick={() => router.delete(`/settings/theme/${theme.id}`, { preserveScroll: true })}
                                    className="u-btn u-btn-ghost"
                                    aria-label={`Delete ${theme.name}`}
                                >
                                    <Trash2 className="size-3.5" />
                                </button>
                            </span>
                        </Panel>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}
