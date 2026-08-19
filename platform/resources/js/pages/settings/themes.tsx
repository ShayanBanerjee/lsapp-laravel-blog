import { EmptyState, Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import { CONTRAST_RULES, contrastRatio, withAlpha } from '@/lib/contrast';
import type { UniversePreview, UniverseTheme } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Loader2, Lock, Palette, Trash2, TriangleAlert } from 'lucide-react';
import { useMemo, useState } from 'react';

type Tokens = Record<string, string>;

interface BaseUniverse extends UniversePreview {
    id: number;
    tokens: UniverseTheme;
}

interface ThemeRow {
    id: number;
    name: string;
    slug: string;
    base_universe_id: number;
    tokens: UniverseTheme;
    haloShape: string;
    base: UniversePreview;
    personas: string[];
}

interface PersonaRow {
    id: number;
    handle: string;
    universe: UniversePreview;
    custom_theme_id: number | null;
}

interface Props {
    themes: ThemeRow[];
    bases: BaseUniverse[];
    personas: PersonaRow[];
    colorKeys: string[];
    haloShapes: { value: string; label: string }[];
    canCreate: boolean;
}

/** Human labels for the token keys. Order here is the order in the editor. */
const GROUPS: { title: string; keys: string[] }[] = [
    { title: 'Page', keys: ['bg', 'bgDeep', 'surface1', 'surface2', 'border'] },
    { title: 'Type', keys: ['text', 'textMuted'] },
    { title: 'Accent', keys: ['accent', 'accentFg', 'glow'] },
    { title: 'Metal', keys: ['metalBase', 'metalSheen', 'metalEdge', 'metalShadow'] },
];

const LABELS: Record<string, string> = {
    bg: 'Background',
    bgDeep: 'Deep background',
    surface1: 'Panel',
    surface2: 'Raised panel',
    border: 'Border',
    text: 'Body text',
    textMuted: 'Muted text',
    accent: 'Accent',
    accentFg: 'On accent',
    glow: 'Glow',
    metalBase: 'Metal base',
    metalSheen: 'Sheen',
    metalEdge: 'Edge',
    metalShadow: 'Shadow',
};

export default function ThemeSettings({ themes, bases, personas, haloShapes, canCreate }: Props) {
    const [editing, setEditing] = useState<ThemeRow | null>(null);

    return (
        <SiteLayout wide>
            <Head title="Your themes" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">Your themes</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    A theme is a variation on one of the worlds — your palette, its structure. Put one on a persona and everyone who reads that voice
                    reads it in your colours.
                </p>
            </header>

            {!canCreate && (
                <Panel className="mb-8 flex flex-wrap items-center gap-4 p-5">
                    <Lock className="size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                    <p className="min-w-0 flex-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        Authoring themes is part of premium. You can look at everything here first — nothing is hidden behind the upgrade except
                        saving.
                    </p>
                    <Link href="/upgrade" className="u-btn u-btn-primary">
                        See premium
                    </Link>
                </Panel>
            )}

            {bases.length === 0 ? (
                <EmptyState
                    title="No base universes available"
                    body="A theme is always a variation on a world you can already write in. Unlock one first."
                    action={
                        <Link href="/upgrade" className="u-btn u-btn-primary">
                            See premium
                        </Link>
                    }
                />
            ) : (
                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <ThemeEditor
                        key={editing?.id ?? 'new'}
                        theme={editing}
                        bases={bases}
                        haloShapes={haloShapes}
                        canSave={canCreate}
                        onDone={() => setEditing(null)}
                    />

                    <aside className="flex flex-col gap-5">
                        <Panel className="p-5">
                            <Label>Saved themes</Label>

                            {themes.length === 0 ? (
                                <p className="mt-3 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                    Nothing saved yet. Build one on the left.
                                </p>
                            ) : (
                                <ul className="mt-3 flex flex-col gap-2">
                                    {themes.map((theme) => (
                                        <li
                                            key={theme.id}
                                            className="flex items-center gap-3 rounded-[9px] border p-2.5"
                                            style={{ borderColor: 'var(--u-border)' }}
                                        >
                                            <MiniSwatch tokens={theme.tokens} />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate text-sm font-medium">{theme.name}</span>
                                                <span className="block truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                                    from {theme.base.name}
                                                    {theme.personas.length > 0 && ` · ${theme.personas.map((h) => `@${h}`).join(', ')}`}
                                                </span>
                                            </span>
                                            <button type="button" onClick={() => setEditing(theme)} className="u-btn u-btn-ghost px-2 py-1 text-xs">
                                                Edit
                                            </button>
                                            <button
                                                type="button"
                                                aria-label={`Delete ${theme.name}`}
                                                onClick={() => {
                                                    if (
                                                        window.confirm(`Delete “${theme.name}”? Personas using it go back to their universe palette.`)
                                                    ) {
                                                        router.delete(`/settings/themes/${theme.id}`, { preserveScroll: true });
                                                    }
                                                }}
                                                className="u-btn u-btn-ghost px-2 py-1"
                                            >
                                                <Trash2 className="size-3.5" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </Panel>

                        <Panel className="p-5">
                            <Label>Who wears what</Label>
                            <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                A persona's theme applies to everyone reading it, not only to you.
                            </p>

                            <ul className="mt-4 flex flex-col gap-3">
                                {personas.map((persona) => (
                                    <li key={persona.id}>
                                        <span className="mb-1.5 block text-sm font-medium">@{persona.handle}</span>
                                        <select
                                            value={persona.custom_theme_id ?? ''}
                                            onChange={(event) =>
                                                router.put(
                                                    `/personas/${persona.handle}/theme`,
                                                    { custom_theme_id: event.target.value === '' ? null : Number(event.target.value) },
                                                    { preserveScroll: true },
                                                )
                                            }
                                            className="w-full rounded-[9px] border bg-transparent px-3 py-2 text-sm"
                                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)' }}
                                        >
                                            <option value="">{persona.universe.name} (default)</option>
                                            {themes.map((theme) => (
                                                <option key={theme.id} value={theme.id}>
                                                    {theme.name}
                                                </option>
                                            ))}
                                        </select>
                                    </li>
                                ))}
                                {personas.length === 0 && (
                                    <li className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                        Create a persona to wear a theme.
                                    </li>
                                )}
                            </ul>
                        </Panel>
                    </aside>
                </div>
            )}
        </SiteLayout>
    );
}

function ThemeEditor({
    theme,
    bases,
    haloShapes,
    canSave,
    onDone,
}: {
    theme: ThemeRow | null;
    bases: BaseUniverse[];
    haloShapes: { value: string; label: string }[];
    canSave: boolean;
    onDone: () => void;
}) {
    const initialBase = bases.find((base) => base.id === theme?.base_universe_id) ?? bases[0];
    const initialTokens = (theme?.tokens ?? initialBase.tokens) as unknown as Tokens;

    const { data, setData, post, put, processing, errors } = useForm({
        name: theme?.name ?? 'My theme',
        base_universe_id: initialBase.id,
        tokens: { ...initialTokens } as Tokens,
        haloShape: theme?.haloShape ?? 'dome',
        haloStrength: 0.2,
        grainAngle: Number.parseInt(String(initialTokens.grainAngle ?? '115'), 10) || 115,
        scheme: (initialTokens.scheme ?? 'dark') as 'light' | 'dark',
    });

    const setToken = (key: string, value: string) => setData('tokens', { ...data.tokens, [key]: value });

    /** Load a base universe's palette wholesale — the usual way to start. */
    const adoptBase = (id: number) => {
        const base = bases.find((candidate) => candidate.id === id);
        if (!base) return;

        setData((current) => ({
            ...current,
            base_universe_id: id,
            tokens: { ...(base.tokens as unknown as Tokens) },
            grainAngle: Number.parseInt(String(base.tokens.grainAngle ?? '115'), 10) || 115,
            scheme: base.tokens.scheme,
        }));
    };

    const report = useMemo(
        () =>
            CONTRAST_RULES.map((rule) => {
                const ratio = contrastRatio(data.tokens[rule.fg] ?? '#000000', data.tokens[rule.bg] ?? '#ffffff');
                return { ...rule, ratio, passes: ratio >= rule.min };
            }),
        [data.tokens],
    );

    const failing = report.filter((row) => !row.passes);

    // The preview paints from pending state, so a colour is judged by looking
    // at it rather than by reading a hex value.
    const previewVars = {
        '--u-bg': data.tokens.bg,
        '--u-bg-deep': data.tokens.bgDeep,
        '--u-surface-1': data.tokens.surface1,
        '--u-surface-2': data.tokens.surface2,
        '--u-border': data.tokens.border,
        '--u-text': data.tokens.text,
        '--u-text-muted': data.tokens.textMuted,
        '--u-accent': data.tokens.accent,
        '--u-accent-fg': data.tokens.accentFg,
        '--u-accent-soft': withAlpha(data.tokens.accent ?? '#888888', 0.18),
        '--u-metal-base': data.tokens.metalBase,
        '--u-metal-sheen': data.tokens.metalSheen,
        '--u-metal-edge': data.tokens.metalEdge,
        '--u-metal-shadow': data.tokens.metalShadow,
        '--u-grain-angle': `${data.grainAngle}deg`,
        '--u-glow': data.tokens.glow,
    } as React.CSSProperties;

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                const options = { preserveScroll: true, onSuccess: onDone };

                if (theme) {
                    put(`/settings/themes/${theme.id}`, options);
                } else {
                    post('/settings/themes', options);
                }
            }}
            className="flex flex-col gap-6"
        >
            <Panel className="p-6">
                <div className="flex flex-wrap items-end gap-4">
                    <span className="min-w-0 flex-1">
                        <Label>Theme name</Label>
                        <input
                            value={data.name}
                            onChange={(event) => setData('name', event.target.value)}
                            className="mt-2 w-full rounded-[9px] border bg-transparent px-3 py-2 text-sm"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)' }}
                        />
                    </span>
                    <span>
                        <Label>Based on</Label>
                        <select
                            value={data.base_universe_id}
                            onChange={(event) => adoptBase(Number(event.target.value))}
                            className="mt-2 rounded-[9px] border bg-transparent px-3 py-2 text-sm"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)' }}
                        >
                            {bases.map((base) => (
                                <option key={base.id} value={base.id}>
                                    {base.name}
                                </option>
                            ))}
                        </select>
                    </span>
                </div>
                {errors.name && <FieldError message={errors.name} />}
            </Panel>

            <Panel className="p-6">
                <div className="grid gap-6 sm:grid-cols-2">
                    {GROUPS.map((group) => (
                        <div key={group.title}>
                            <Label>{group.title}</Label>
                            <div className="mt-3 flex flex-col gap-2">
                                {group.keys.map((key) => (
                                    <label key={key} className="flex items-center gap-3">
                                        <input
                                            type="color"
                                            value={normalizeHex(data.tokens[key])}
                                            onChange={(event) => setToken(key, event.target.value)}
                                            aria-label={LABELS[key] ?? key}
                                            className="size-8 shrink-0 cursor-pointer rounded-[7px] border bg-transparent"
                                            style={{ borderColor: 'var(--u-border)' }}
                                        />
                                        <span className="min-w-0 flex-1 truncate text-sm">{LABELS[key] ?? key}</span>
                                        <input
                                            value={data.tokens[key] ?? ''}
                                            onChange={(event) => setToken(key, event.target.value)}
                                            aria-label={`${LABELS[key] ?? key} hex value`}
                                            spellCheck={false}
                                            className="w-24 rounded-[7px] border bg-transparent px-2 py-1 font-mono text-xs"
                                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text-muted)' }}
                                        />
                                    </label>
                                ))}
                            </div>
                        </div>
                    ))}
                </div>

                <Rail className="my-6" />

                <div className="grid gap-6 sm:grid-cols-3">
                    <label className="flex flex-col gap-2">
                        <Label>Halo</Label>
                        <select
                            value={data.haloShape}
                            onChange={(event) => setData('haloShape', event.target.value)}
                            className="rounded-[9px] border bg-transparent px-3 py-2 text-sm"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)' }}
                        >
                            {haloShapes.map((shape) => (
                                <option key={shape.value} value={shape.value}>
                                    {shape.label}
                                </option>
                            ))}
                        </select>
                    </label>

                    <label className="flex flex-col gap-2">
                        <Label>Grain angle</Label>
                        <input
                            type="range"
                            min={0}
                            max={359}
                            value={data.grainAngle}
                            onChange={(event) => setData('grainAngle', Number(event.target.value))}
                            className="accent-[var(--u-accent)]"
                        />
                        <span className="text-xs tabular-nums" style={{ color: 'var(--u-text-muted)' }}>
                            {data.grainAngle}°
                        </span>
                    </label>

                    <label className="flex flex-col gap-2">
                        <Label>Scheme</Label>
                        <select
                            value={data.scheme}
                            onChange={(event) => setData('scheme', event.target.value as 'light' | 'dark')}
                            className="rounded-[9px] border bg-transparent px-3 py-2 text-sm"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)' }}
                        >
                            <option value="dark">Dark</option>
                            <option value="light">Light</option>
                        </select>
                    </label>
                </div>
            </Panel>

            <Panel className="p-6">
                <Label>Readability</Label>
                <p className="mt-2 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Measured against WCAG AA. A palette that fails cannot be saved — it would make your writing unreadable for someone.
                </p>

                <ul className="mt-4 flex flex-col gap-1.5">
                    {report.map((row) => (
                        <li key={row.label} className="flex items-center gap-2.5 text-sm">
                            {row.passes ? (
                                <Check className="size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                            ) : (
                                <TriangleAlert className="size-4 shrink-0" style={{ color: '#f0785a' }} />
                            )}
                            <span className="min-w-0 flex-1 truncate" style={{ color: 'var(--u-text-muted)' }}>
                                {row.label}
                            </span>
                            <span className="tabular-nums" style={{ color: row.passes ? 'var(--u-text-muted)' : '#f0785a' }}>
                                {row.ratio.toFixed(2)}:1
                            </span>
                        </li>
                    ))}
                </ul>

                {errors.tokens && <FieldError message={errors.tokens} />}
            </Panel>

            <div data-scheme={data.scheme} style={previewVars} className="u-root overflow-hidden rounded-[14px] border p-8">
                <div style={{ backgroundColor: 'var(--u-bg)' }} className="-m-8 p-8">
                    <p className="text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Preview
                    </p>
                    <h2 className="font-display mt-3 text-3xl" style={{ color: 'var(--u-text)' }}>
                        The way a page will actually look
                    </h2>
                    <p className="mt-3 max-w-prose text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Body copy sits here, at the size and weight readers meet it. If this is hard to read now, it will be hard to read in an
                        article — that is the whole reason this panel exists.
                    </p>
                    <div className="mt-6 flex flex-wrap gap-3">
                        <span
                            className="rounded-[9px] px-4 py-2 text-sm font-medium"
                            style={{ backgroundColor: 'var(--u-accent)', color: 'var(--u-accent-fg)' }}
                        >
                            A button
                        </span>
                        <span
                            className="rounded-[9px] border px-4 py-2 text-sm"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text)', backgroundColor: 'var(--u-surface-1)' }}
                        >
                            A panel
                        </span>
                    </div>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <button type="submit" disabled={processing || !canSave || failing.length > 0} className="u-btn u-btn-primary disabled:opacity-50">
                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Palette className="size-4" />}
                    {theme ? 'Save changes' : 'Save theme'}
                </button>
                {theme && (
                    <button type="button" onClick={onDone} className="u-btn u-btn-ghost">
                        Start a new one
                    </button>
                )}
                {failing.length > 0 && (
                    <span className="text-sm" style={{ color: '#f0785a' }}>
                        {failing.length} contrast {failing.length === 1 ? 'check' : 'checks'} still failing.
                    </span>
                )}
            </div>
        </form>
    );
}

/** `<input type="color">` accepts only #rrggbb, so shorthand and alpha are trimmed. */
function normalizeHex(value: string | undefined): string {
    if (!value) return '#000000';

    const hex = value.replace(/^#/, '');

    if (hex.length === 3) return `#${hex[0]}${hex[0]}${hex[1]}${hex[1]}${hex[2]}${hex[2]}`;

    return `#${hex.slice(0, 6).padEnd(6, '0')}`;
}

function MiniSwatch({ tokens }: { tokens: UniverseTheme }) {
    return (
        <span
            aria-hidden
            className="size-8 shrink-0 rounded-full"
            style={{
                background: `conic-gradient(from 210deg, ${tokens.accent}, ${tokens.metalBase} 45%, ${tokens.bg} 78%, ${tokens.accent})`,
                boxShadow: 'inset 0 0 0 1px rgb(255 255 255 / 0.14)',
            }}
        />
    );
}

function Label({ children }: { children: string }) {
    return (
        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </span>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="mt-3 text-sm" style={{ color: '#f0785a' }} role="alert">
            {message}
        </p>
    );
}
