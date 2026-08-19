import { Chip, EmptyState, Panel, SectionHeading, Swatch } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { UniversePreview } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Loader2, Lock, Plus, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface PersonaRow {
    id: number;
    handle: string;
    display_name: string;
    bio: string | null;
    posts_count: number;
    universe: UniversePreview;
    locked: boolean;
}

interface Props {
    personas: PersonaRow[];
    universes: UniversePreview[];
    canCreate: boolean;
    personaLimit: number | null;
    handles: { vanityMaxLength: number; canClaimVanity: boolean };
}

export default function PersonasIndex({ personas, universes, canCreate, personaLimit, handles }: Props) {
    const [creating, setCreating] = useState(false);

    return (
        <SiteLayout wide>
            <Head title="Personas" />

            <SectionHeading
                eyebrow="Your selves"
                title="Personas"
                action={
                    canCreate ? (
                        <button type="button" onClick={() => setCreating((value) => !value)} className="u-btn u-btn-primary">
                            <Plus className="size-4" />
                            New persona
                        </button>
                    ) : (
                        <Link href="/upgrade" className="u-btn u-btn-primary">
                            <Lock className="size-4" />
                            Unlock more personas
                        </Link>
                    )
                }
            />

            <p className="mb-9 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                Each persona lives in one universe and carries its own handle, voice and following. Readers follow the persona, not the account behind
                it.
                {personaLimit !== null && (
                    <span className="mt-2 block">
                        Your plan includes {personaLimit} {personaLimit === 1 ? 'persona' : 'personas'}.
                    </span>
                )}
            </p>

            {creating && canCreate && <CreateForm universes={universes} handles={handles} onDone={() => setCreating(false)} />}

            {personas.length === 0 ? (
                <EmptyState
                    title="No personas yet"
                    body="You need at least one persona before you can publish. Pick the world you want to write in first."
                    action={
                        canCreate ? (
                            <button type="button" onClick={() => setCreating(true)} className="u-btn u-btn-primary">
                                Create your first persona
                            </button>
                        ) : undefined
                    }
                />
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {personas.map((persona) => (
                        <Panel key={persona.id} className="flex flex-col p-6">
                            <div className="flex items-start gap-4">
                                <Swatch swatch={persona.universe.swatch} size={44} />
                                <div className="min-w-0 flex-1">
                                    <h3 className="font-display truncate text-xl">{persona.display_name}</h3>
                                    <p className="truncate text-sm" style={{ color: 'var(--u-accent)' }}>
                                        @{persona.handle}
                                    </p>
                                </div>
                            </div>

                            {persona.bio && (
                                <p className="mt-4 line-clamp-3 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                    {persona.bio}
                                </p>
                            )}

                            <div className="mt-4 flex flex-wrap gap-2">
                                <Chip>{persona.universe.name}</Chip>
                                <Chip>
                                    {persona.posts_count} {persona.posts_count === 1 ? 'piece' : 'pieces'}
                                </Chip>
                                {persona.locked && (
                                    <Chip tone="accent">
                                        <Lock className="size-3" />
                                        Locked
                                    </Chip>
                                )}
                            </div>

                            <div className="mt-6 flex gap-2 pt-4" style={{ borderTop: '1px solid var(--u-border)' }}>
                                {!persona.locked && (
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/personas/${persona.handle}/switch`, {}, { preserveScroll: true })}
                                        className="u-btn u-btn-ghost flex-1"
                                    >
                                        Write as this
                                    </button>
                                )}
                                <button
                                    type="button"
                                    aria-label={`Retire ${persona.handle}`}
                                    onClick={() => {
                                        if (window.confirm(`Retire @${persona.handle}? Their ${persona.posts_count} published pieces stay yours.`)) {
                                            router.delete(`/personas/${persona.handle}`, { preserveScroll: true });
                                        }
                                    }}
                                    className="u-btn u-btn-ghost"
                                >
                                    <Trash2 className="size-4" />
                                </button>
                            </div>
                        </Panel>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}

function CreateForm({
    universes,
    handles,
    onDone,
}: {
    universes: UniversePreview[];
    handles: { vanityMaxLength: number; canClaimVanity: boolean };
    onDone: () => void;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        handle: '',
        display_name: '',
        bio: '',
        universe_id: '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/personas', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onDone();
            },
        });
    };

    return (
        <Panel className="mb-9 p-6 sm:p-8">
            <h3 className="font-display text-2xl">New persona</h3>

            <form onSubmit={submit} className="mt-6 flex flex-col gap-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label htmlFor="handle" className="mb-2 block text-sm">
                            Handle
                        </label>
                        <input
                            id="handle"
                            value={data.handle}
                            onChange={(event) => setData('handle', event.target.value)}
                            placeholder="longlight"
                            className="u-field"
                            aria-describedby="handle-help"
                        />
                        <p id="handle-help" className="mt-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                            {handles.canClaimVanity
                                ? `Yours to pick — including short ones, down to ${handles.vanityMaxLength} characters.`
                                : `${handles.vanityMaxLength} characters or fewer is a premium handle. Anything longer is free.`}
                        </p>
                        {errors.handle && <FieldError message={errors.handle} />}
                    </div>
                    <div>
                        <label htmlFor="display_name" className="mb-2 block text-sm">
                            Display name
                        </label>
                        <input
                            id="display_name"
                            value={data.display_name}
                            onChange={(event) => setData('display_name', event.target.value)}
                            placeholder="Long Light"
                            className="u-field"
                        />
                        {errors.display_name && <FieldError message={errors.display_name} />}
                    </div>
                </div>

                <div>
                    <label htmlFor="bio" className="mb-2 block text-sm">
                        Bio
                    </label>
                    <textarea
                        id="bio"
                        value={data.bio}
                        onChange={(event) => setData('bio', event.target.value)}
                        rows={3}
                        placeholder="What does this voice write about?"
                        className="u-field resize-y"
                    />
                    {errors.bio && <FieldError message={errors.bio} />}
                </div>

                <div>
                    <span className="mb-3 block text-sm">Universe</span>
                    <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {universes.map((universe) => {
                            const active = data.universe_id !== '' && Number(data.universe_id) === universe.id;

                            return (
                                <button
                                    key={universe.slug}
                                    type="button"
                                    disabled={universe.locked}
                                    aria-pressed={active}
                                    onClick={() => setData('universe_id', String(universe.id ?? ''))}
                                    className="flex items-center gap-3 rounded-[9px] border px-3 py-2.5 text-left transition-colors disabled:opacity-45"
                                    style={{
                                        borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                        backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                    }}
                                >
                                    <Swatch swatch={universe.swatch} size={26} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">{universe.name}</span>
                                        <span className="block truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {universe.locked ? 'Premium' : universe.material}
                                        </span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {errors.universe_id && <FieldError message={errors.universe_id} />}
                </div>

                <div className="flex gap-2">
                    <button type="submit" disabled={processing} className="u-btn u-btn-primary">
                        {processing && <Loader2 className="size-4 animate-spin" />}
                        Create persona
                    </button>
                    <button type="button" onClick={onDone} className="u-btn u-btn-ghost">
                        Cancel
                    </button>
                </div>
            </form>
        </Panel>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="mt-1.5 text-sm" style={{ color: '#f0785a' }} role="alert">
            {message}
        </p>
    );
}
