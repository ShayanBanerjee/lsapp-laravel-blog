import { CourseMetaFields } from '@/components/course-meta-fields';
import { Editor } from '@/components/editor';
import { Panel, Rail } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { PersonaSummary } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Eye, Loader2, Plus, Save, Trash2 } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import type { CourseCard } from './index';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

interface LessonRow {
    id: number;
    slug: string;
    title: string;
    body: string;
    position: number;
    reading_time: number | null;
}

interface ModuleRow {
    id: number;
    title: string;
    summary: string | null;
    position: number;
    lessons: LessonRow[];
}

interface Props {
    course: CourseCard & { subtitle: string | null; description: string | null; persona_id: number | null };
    modules: ModuleRow[];
    personas: PersonaOption[];
    levels: string[];
    canPublish: boolean;
}

export default function CourseEdit({ course, modules, personas, levels, canPublish }: Props) {
    const meta = useForm({
        // Inertia needs an explicit override to send a file on an update;
        // the route is POST for the same reason.
        title: course.title,
        subtitle: course.subtitle ?? '',
        description: course.description ?? '',
        persona_id: course.persona_id ?? personas[0]?.id ?? '',
        level: course.level,
        status: (course.status ?? 'draft') as 'draft' | 'published',
        cover_image: null as File | null,
    });

    const saveMeta = (event: FormEvent) => {
        event.preventDefault();
        meta.post(`/courses/${course.slug}`, { forceFormData: true, preserveScroll: true });
    };

    /** Rewrite the whole order from the moved list — see the controller. */
    const moveModule = (index: number, direction: -1 | 1) => {
        const next = [...modules];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];

        router.post(`/courses/${course.slug}/reorder`, { kind: 'modules', ids: next.map((module) => module.id) }, { preserveScroll: true });
    };

    const moveLesson = (moduleId: number, lessons: LessonRow[], index: number, direction: -1 | 1) => {
        const next = [...lessons];
        const target = index + direction;
        if (target < 0 || target >= next.length) return;
        [next[index], next[target]] = [next[target], next[index]];

        router.post(
            `/courses/${course.slug}/reorder`,
            { kind: 'lessons', module_id: moduleId, ids: next.map((lesson) => lesson.id) },
            { preserveScroll: true },
        );
    };

    return (
        <SiteLayout wide>
            <Head title={`Editing ${course.title}`} />

            <header className="mb-9 flex flex-wrap items-center justify-between gap-4">
                <h1 className="font-display text-3xl sm:text-4xl">Editing “{course.title}”</h1>
                <div className="flex flex-wrap gap-2">
                    <Link href={`/courses/${course.slug}`} className="u-btn u-btn-ghost">
                        <Eye className="size-4" />
                        View
                    </Link>
                    <button
                        type="button"
                        onClick={() => {
                            if (window.confirm(`Delete “${course.title}” and every lesson in it? This cannot be undone.`)) {
                                router.delete(`/courses/${course.slug}`);
                            }
                        }}
                        className="u-btn u-btn-ghost"
                    >
                        <Trash2 className="size-4" />
                        Delete course
                    </button>
                </div>
            </header>

            <form onSubmit={saveMeta} className="mb-12 flex flex-col gap-6">
                <CourseMetaFields
                    data={meta.data}
                    setData={meta.setData as (key: string, value: unknown) => void}
                    errors={meta.errors}
                    personas={personas}
                    levels={levels}
                    canPublish={canPublish}
                    progress={meta.progress}
                    existingCover={course.cover_url}
                />
                <div>
                    <button type="submit" disabled={meta.processing} className="u-btn u-btn-primary">
                        {meta.processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                        Save course details
                    </button>
                </div>
            </form>

            <Rail className="mb-12" />

            <section>
                <h2 className="font-display mb-2 text-2xl">Modules and lessons</h2>
                <p className="mb-7 max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Modules group lessons; lessons are the pages readers work through. Order here is reading order.
                </p>

                <div className="flex flex-col gap-5">
                    {modules.map((module, index) => (
                        <ModuleEditor
                            key={module.id}
                            course={course}
                            module={module}
                            isFirst={index === 0}
                            isLast={index === modules.length - 1}
                            onMove={(direction) => moveModule(index, direction)}
                            onMoveLesson={(lessonIndex, direction) => moveLesson(module.id, module.lessons, lessonIndex, direction)}
                        />
                    ))}
                </div>

                <NewModuleForm slug={course.slug} />
            </section>
        </SiteLayout>
    );
}

function ModuleEditor({
    course,
    module,
    isFirst,
    isLast,
    onMove,
    onMoveLesson,
}: {
    course: CourseCard;
    module: ModuleRow;
    isFirst: boolean;
    isLast: boolean;
    onMove: (direction: -1 | 1) => void;
    onMoveLesson: (index: number, direction: -1 | 1) => void;
}) {
    const [editingTitle, setEditingTitle] = useState(false);
    const [addingLesson, setAddingLesson] = useState(false);
    const [openLesson, setOpenLesson] = useState<string | null>(null);

    const form = useForm({ title: module.title, summary: module.summary ?? '' });

    return (
        <Panel className="p-5 sm:p-6">
            <div className="flex flex-wrap items-start gap-3">
                <div className="min-w-0 flex-1">
                    {editingTitle ? (
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.put(`/courses/${course.slug}/modules/${module.id}`, {
                                    preserveScroll: true,
                                    onSuccess: () => setEditingTitle(false),
                                });
                            }}
                            className="flex flex-col gap-3"
                        >
                            <input value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} className="u-field" />
                            <input
                                value={form.data.summary}
                                onChange={(e) => form.setData('summary', e.target.value)}
                                placeholder="One line on what this module covers"
                                className="u-field"
                            />
                            <div className="flex gap-2">
                                <button type="submit" className="u-btn u-btn-primary px-3 py-1.5 text-sm">
                                    Save
                                </button>
                                <button type="button" onClick={() => setEditingTitle(false)} className="u-btn u-btn-ghost px-3 py-1.5 text-sm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    ) : (
                        <>
                            <h3 className="font-display text-xl">{module.title}</h3>
                            {module.summary && (
                                <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                    {module.summary}
                                </p>
                            )}
                        </>
                    )}
                </div>

                <div className="flex shrink-0 gap-1">
                    <button
                        type="button"
                        onClick={() => onMove(-1)}
                        disabled={isFirst}
                        aria-label={`Move ${module.title} up`}
                        className="u-btn u-btn-ghost px-2 py-1 disabled:opacity-30"
                    >
                        <ChevronUp className="size-3.5" />
                    </button>
                    <button
                        type="button"
                        onClick={() => onMove(1)}
                        disabled={isLast}
                        aria-label={`Move ${module.title} down`}
                        className="u-btn u-btn-ghost px-2 py-1 disabled:opacity-30"
                    >
                        <ChevronDown className="size-3.5" />
                    </button>
                    {!editingTitle && (
                        <button type="button" onClick={() => setEditingTitle(true)} className="u-btn u-btn-ghost px-3 py-1 text-xs">
                            Rename
                        </button>
                    )}
                    <button
                        type="button"
                        aria-label={`Delete ${module.title}`}
                        onClick={() => {
                            if (window.confirm(`Delete “${module.title}” and its ${module.lessons.length} lesson(s)?`)) {
                                router.delete(`/courses/${course.slug}/modules/${module.id}`, { preserveScroll: true });
                            }
                        }}
                        className="u-btn u-btn-ghost px-2 py-1"
                    >
                        <Trash2 className="size-3.5" />
                    </button>
                </div>
            </div>

            <ol className="mt-5 flex flex-col gap-2">
                {module.lessons.map((lesson, index) => (
                    <li key={lesson.id}>
                        <div className="flex items-center gap-2 rounded-[9px] border px-3 py-2" style={{ borderColor: 'var(--u-border)' }}>
                            <span className="min-w-0 flex-1 truncate text-sm">{lesson.title}</span>
                            <span className="shrink-0 text-xs tabular-nums" style={{ color: 'var(--u-text-muted)' }}>
                                {lesson.reading_time} min
                            </span>
                            <button
                                type="button"
                                onClick={() => onMoveLesson(index, -1)}
                                disabled={index === 0}
                                aria-label={`Move ${lesson.title} up`}
                                className="u-btn u-btn-ghost px-1.5 py-1 disabled:opacity-30"
                            >
                                <ChevronUp className="size-3" />
                            </button>
                            <button
                                type="button"
                                onClick={() => onMoveLesson(index, 1)}
                                disabled={index === module.lessons.length - 1}
                                aria-label={`Move ${lesson.title} down`}
                                className="u-btn u-btn-ghost px-1.5 py-1 disabled:opacity-30"
                            >
                                <ChevronDown className="size-3" />
                            </button>
                            <button
                                type="button"
                                onClick={() => setOpenLesson(openLesson === lesson.slug ? null : lesson.slug)}
                                className="u-btn u-btn-ghost px-3 py-1 text-xs"
                            >
                                {openLesson === lesson.slug ? 'Close' : 'Edit'}
                            </button>
                            <button
                                type="button"
                                aria-label={`Delete ${lesson.title}`}
                                onClick={() => {
                                    if (window.confirm(`Delete “${lesson.title}”?`)) {
                                        router.delete(`/courses/${course.slug}/modules/${module.id}/lessons/${lesson.slug}`, {
                                            preserveScroll: true,
                                        });
                                    }
                                }}
                                className="u-btn u-btn-ghost px-1.5 py-1"
                            >
                                <Trash2 className="size-3" />
                            </button>
                        </div>

                        {openLesson === lesson.slug && (
                            <LessonForm
                                key={lesson.slug}
                                action={`/courses/${course.slug}/modules/${module.id}/lessons/${lesson.slug}`}
                                method="put"
                                initial={{ title: lesson.title, body: lesson.body }}
                                onDone={() => setOpenLesson(null)}
                            />
                        )}
                    </li>
                ))}
            </ol>

            {addingLesson ? (
                <LessonForm
                    action={`/courses/${course.slug}/modules/${module.id}/lessons`}
                    method="post"
                    initial={{ title: '', body: '' }}
                    onDone={() => setAddingLesson(false)}
                />
            ) : (
                <button type="button" onClick={() => setAddingLesson(true)} className="u-btn u-btn-ghost mt-4">
                    <Plus className="size-4" />
                    Add a lesson
                </button>
            )}
        </Panel>
    );
}

function LessonForm({
    action,
    method,
    initial,
    onDone,
}: {
    action: string;
    method: 'post' | 'put';
    initial: { title: string; body: string };
    onDone: () => void;
}) {
    const { data, setData, post, put, processing, errors, reset } = useForm(initial);

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                const options = {
                    preserveScroll: true,
                    onSuccess: () => {
                        if (method === 'post') reset();
                        onDone();
                    },
                };

                if (method === 'put') {
                    put(action, options);
                } else {
                    post(action, options);
                }
            }}
            className="mt-4 flex flex-col gap-4 rounded-[9px] border p-4"
            style={{ borderColor: 'var(--u-border)' }}
        >
            <div>
                <input
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="Lesson title"
                    aria-label="Lesson title"
                    className="u-field"
                />
                {errors.title && <FieldError message={errors.title} />}
            </div>

            <div>
                <Editor value={data.body} onChange={(html) => setData('body', html)} />
                {errors.body && <FieldError message={errors.body} />}
            </div>

            <div className="flex gap-2">
                <button type="submit" disabled={processing} className="u-btn u-btn-primary px-4 py-2 text-sm">
                    {processing ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                    {method === 'put' ? 'Save lesson' : 'Add lesson'}
                </button>
                <button type="button" onClick={onDone} className="u-btn u-btn-ghost px-4 py-2 text-sm">
                    Cancel
                </button>
            </div>
        </form>
    );
}

function NewModuleForm({ slug }: { slug: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({ title: '', summary: '' });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                post(`/courses/${slug}/modules`, { preserveScroll: true, onSuccess: () => reset() });
            }}
            className="mt-6 flex flex-wrap items-start gap-3"
        >
            <div className="min-w-[220px] flex-1">
                <input
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="New module title"
                    aria-label="New module title"
                    className="u-field"
                />
                {errors.title && <FieldError message={errors.title} />}
            </div>
            <div className="min-w-[220px] flex-1">
                <input
                    value={data.summary}
                    onChange={(event) => setData('summary', event.target.value)}
                    placeholder="One line on what it covers (optional)"
                    aria-label="Module summary"
                    className="u-field"
                />
            </div>
            <button type="submit" disabled={processing} className="u-btn u-btn-primary">
                <Plus className="size-4" />
                Add module
            </button>
        </form>
    );
}

function FieldError({ message }: { message: string }) {
    return (
        <p className="mt-2 text-sm" style={{ color: '#f0785a' }} role="alert">
            {message}
        </p>
    );
}
