import { Editor } from '@/components/editor';
import { Panel, Swatch } from '@/components/metal';
import type { PersonaSummary } from '@/types';
import { useForm } from '@inertiajs/react';
import { ImagePlus, Loader2, Send, X } from 'lucide-react';
import { useState, type FormEvent } from 'react';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

interface Props {
    personas: PersonaOption[];
    post?: {
        slug: string;
        title: string;
        body: string;
        status: 'draft' | 'published';
        cover_url: string | null;
        persona_id: number | null;
    };
}

export function PostForm({ personas, post }: Props) {
    const isEdit = Boolean(post);

    const {
        data,
        setData,
        post: submit,
        processing,
        errors,
        progress,
    } = useForm({
        // Inertia needs an explicit method override to send files on an update.
        _method: isEdit ? 'put' : 'post',
        title: post?.title ?? '',
        body: post?.body ?? '',
        persona_id: post?.persona_id ?? personas[0]?.id ?? '',
        status: post?.status ?? 'draft',
        cover_image: null as File | null,
    });

    const [preview, setPreview] = useState<string | null>(post?.cover_url ?? null);

    const selected = personas.find((persona) => persona.id === Number(data.persona_id));

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        submit(isEdit ? `/posts/${post!.slug}` : '/posts', { forceFormData: true });
    };

    const pickCover = (file: File | null) => {
        setData('cover_image', file);
        setPreview(file ? URL.createObjectURL(file) : (post?.cover_url ?? null));
    };

    if (personas.length === 0) {
        return (
            <Panel className="p-10 text-center">
                <h2 className="font-display text-2xl">You need a persona first</h2>
                <p className="mx-auto mt-3 max-w-md text-sm" style={{ color: 'var(--u-text-muted)' }}>
                    Every piece is published by a persona, and every persona belongs to a universe. Create one to start writing.
                </p>
                <a href="/personas" className="u-btn u-btn-primary mt-6">
                    Create a persona
                </a>
            </Panel>
        );
    }

    return (
        <form onSubmit={handleSubmit} className="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div className="flex flex-col gap-6">
                <div>
                    <input
                        type="text"
                        value={data.title}
                        onChange={(event) => setData('title', event.target.value)}
                        placeholder="Title"
                        aria-label="Title"
                        className="font-display w-full border-none bg-transparent text-4xl leading-tight outline-none sm:text-5xl"
                        style={{ color: 'var(--u-text)' }}
                    />
                    {errors.title && <Error message={errors.title} />}
                </div>

                <div>
                    <Editor value={data.body} onChange={(html) => setData('body', html)} />
                    {errors.body && <Error message={errors.body} />}
                </div>
            </div>

            <aside className="flex flex-col gap-5">
                <Panel className="p-5">
                    <Label>Publishing as</Label>
                    <div className="mt-3 flex flex-col gap-2">
                        {personas.map((persona) => {
                            const active = Number(data.persona_id) === persona.id;

                            return (
                                <button
                                    key={persona.id}
                                    type="button"
                                    onClick={() => setData('persona_id', persona.id)}
                                    className="flex items-center gap-3 rounded-[9px] border px-3 py-2.5 text-left transition-colors"
                                    style={{
                                        borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                        backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                    }}
                                >
                                    <Swatch swatch={persona.universe.swatch} size={26} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">@{persona.handle}</span>
                                        <span className="block truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {persona.universe.name}
                                        </span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                    {errors.persona_id && <Error message={errors.persona_id} />}
                    {selected && (
                        <p className="mt-3 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            This piece will be published into {selected.universe.name} and read in that world's theme.
                        </p>
                    )}
                </Panel>

                <Panel className="p-5">
                    <Label>Cover image</Label>
                    {preview ? (
                        <div className="relative mt-3">
                            <img src={preview} alt="" className="h-36 w-full rounded-[9px] object-cover" />
                            <button
                                type="button"
                                onClick={() => pickCover(null)}
                                aria-label="Remove cover image"
                                className="u-btn u-btn-ghost absolute top-2 right-2 px-2 py-1"
                            >
                                <X className="size-3.5" />
                            </button>
                        </div>
                    ) : (
                        <label
                            className="mt-3 flex h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-[9px] border border-dashed text-xs"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text-muted)' }}
                        >
                            <ImagePlus className="size-5" />
                            Choose an image
                            <input type="file" accept="image/*" className="hidden" onChange={(event) => pickCover(event.target.files?.[0] ?? null)} />
                        </label>
                    )}
                    {errors.cover_image && <Error message={errors.cover_image} />}
                    {progress && (
                        <div className="mt-3 h-1 overflow-hidden rounded-full" style={{ backgroundColor: 'var(--u-border)' }}>
                            <div className="h-full transition-all" style={{ width: `${progress.percentage}%`, backgroundColor: 'var(--u-accent)' }} />
                        </div>
                    )}
                </Panel>

                <Panel className="p-5">
                    <Label>Status</Label>
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        {(['draft', 'published'] as const).map((status) => {
                            const active = data.status === status;

                            return (
                                <button
                                    key={status}
                                    type="button"
                                    onClick={() => setData('status', status)}
                                    className="rounded-[9px] border px-3 py-2 text-sm capitalize transition-colors"
                                    style={{
                                        borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                        backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                        color: active ? 'var(--u-accent)' : 'var(--u-text-muted)',
                                    }}
                                >
                                    {status}
                                </button>
                            );
                        })}
                    </div>
                    <p className="mt-3 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        Drafts are visible only to you.
                    </p>
                </Panel>

                <button type="submit" disabled={processing} className="u-btn u-btn-primary py-3 text-base">
                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Send className="size-4" />}
                    {isEdit ? 'Save changes' : data.status === 'published' ? 'Publish' : 'Save draft'}
                </button>
            </aside>
        </form>
    );
}

function Label({ children }: { children: string }) {
    return (
        <span className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </span>
    );
}

function Error({ message }: { message: string }) {
    return (
        <p className="mt-2 text-sm" style={{ color: '#f0785a' }} role="alert">
            {message}
        </p>
    );
}
