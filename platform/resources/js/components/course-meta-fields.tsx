import { Panel, Swatch } from '@/components/metal';
import type { PersonaSummary } from '@/types';
import { router } from '@inertiajs/react';
import { ImagePlus, MailWarning } from 'lucide-react';
import { useState } from 'react';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

interface MetaData {
    title: string;
    subtitle: string;
    description: string;
    persona_id: number | string;
    level: string;
    status: 'draft' | 'published';
    cover_image: File | null;
}

/**
 * The course's own fields, shared by create and edit so the two forms cannot
 * drift apart — the failure that produces a field you can set when creating
 * and then never change again.
 */
export function CourseMetaFields({
    data,
    setData,
    errors,
    personas,
    levels,
    canPublish,
    progress,
    existingCover,
}: {
    data: MetaData;
    setData: (key: string, value: unknown) => void;
    errors: Partial<Record<string, string>>;
    personas: PersonaOption[];
    levels: string[];
    canPublish: boolean;
    progress?: { percentage?: number } | null;
    existingCover?: string | null;
}) {
    const [preview, setPreview] = useState<string | null>(existingCover ?? null);

    const pickCover = (file: File | null) => {
        setData('cover_image', file);
        setPreview(file ? URL.createObjectURL(file) : (existingCover ?? null));
    };

    return (
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            <Panel className="p-6">
                <Label htmlFor="course-title">Title</Label>
                <input
                    id="course-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="Master GitHub Copilot (GH-300)"
                    className="font-display mt-2 w-full border-none bg-transparent text-3xl leading-tight outline-none"
                    style={{ color: 'var(--u-text)' }}
                />
                {errors.title && <FieldError message={errors.title} />}

                <div className="mt-7">
                    <Label htmlFor="course-subtitle">One line about it</Label>
                    <input
                        id="course-subtitle"
                        value={data.subtitle}
                        onChange={(event) => setData('subtitle', event.target.value)}
                        placeholder="Everything on the exam, in the order it makes sense to learn it."
                        className="u-field mt-2"
                    />
                    {errors.subtitle && <FieldError message={errors.subtitle} />}
                </div>

                <div className="mt-7">
                    <Label htmlFor="course-description">The full description</Label>
                    <textarea
                        id="course-description"
                        value={data.description}
                        onChange={(event) => setData('description', event.target.value)}
                        rows={6}
                        placeholder="Who this is for, what they'll be able to do at the end, and what they need to know already."
                        className="u-field mt-2"
                    />
                    {errors.description && <FieldError message={errors.description} />}
                </div>
            </Panel>

            <aside className="flex flex-col gap-5">
                <Panel className="p-5">
                    <Label>Taught by</Label>
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
                    {errors.persona_id && <FieldError message={errors.persona_id} />}
                </Panel>

                <Panel className="p-5">
                    <Label>Level</Label>
                    <div className="mt-3 flex flex-col gap-2">
                        {levels.map((level) => {
                            const active = data.level === level;

                            return (
                                <button
                                    key={level}
                                    type="button"
                                    onClick={() => setData('level', level)}
                                    className="rounded-[9px] border px-3 py-2 text-sm capitalize transition-colors"
                                    style={{
                                        borderColor: active ? 'var(--u-accent)' : 'var(--u-border)',
                                        backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
                                        color: active ? 'var(--u-accent)' : 'var(--u-text-muted)',
                                    }}
                                >
                                    {level}
                                </button>
                            );
                        })}
                    </div>
                </Panel>

                <Panel className="p-5">
                    <Label>Cover image</Label>
                    {preview ? (
                        <img src={preview} alt="" className="mt-3 h-36 w-full rounded-[9px] object-cover" />
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
                    {errors.cover_image && <FieldError message={errors.cover_image} />}
                    {progress && (
                        <div className="mt-3 h-1 overflow-hidden rounded-full" style={{ backgroundColor: 'var(--u-border)' }}>
                            <div
                                className="h-full transition-all"
                                style={{ width: `${progress.percentage ?? 0}%`, backgroundColor: 'var(--u-accent)' }}
                            />
                        </div>
                    )}
                </Panel>

                <Panel className="p-5">
                    <Label>Status</Label>
                    <div className="mt-3 grid grid-cols-2 gap-2">
                        {(['draft', 'published'] as const).map((status) => {
                            const active = data.status === status;
                            const blocked = status === 'published' && !canPublish;

                            return (
                                <button
                                    key={status}
                                    type="button"
                                    disabled={blocked}
                                    title={blocked ? 'Confirm your email address to publish' : undefined}
                                    onClick={() => setData('status', status)}
                                    className="rounded-[9px] border px-3 py-2 text-sm capitalize transition-colors disabled:cursor-not-allowed disabled:opacity-45"
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
                    <p className="mt-3 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Publishing a course publishes every lesson in it. Drafts are visible only to you.
                    </p>
                    {errors.status && <FieldError message={errors.status} />}
                    {!canPublish && <VerifyNotice />}
                </Panel>
            </aside>
        </div>
    );
}

function VerifyNotice() {
    const [sent, setSent] = useState(false);

    return (
        <div
            className="mt-4 rounded-[9px] border p-3 text-xs leading-relaxed"
            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text-muted)' }}
        >
            <p className="flex items-start gap-2">
                <MailWarning className="mt-0.5 size-3.5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                <span>Confirm your email address to publish.</span>
            </p>
            <button
                type="button"
                disabled={sent}
                onClick={() => router.post('/email/verification-notification', {}, { preserveScroll: true, onSuccess: () => setSent(true) })}
                className="u-btn u-btn-ghost mt-3 w-full py-1.5 text-xs disabled:opacity-60"
            >
                {sent ? 'Link sent — check your inbox' : 'Send the link again'}
            </button>
        </div>
    );
}

function Label({ children, htmlFor }: { children: string; htmlFor?: string }) {
    return (
        <label htmlFor={htmlFor} className="text-[11px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
            {children}
        </label>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="mt-2 text-sm" style={{ color: '#f0785a' }} role="alert">
            {message}
        </p>
    );
}
