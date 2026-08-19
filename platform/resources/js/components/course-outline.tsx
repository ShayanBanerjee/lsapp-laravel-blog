import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Check, Circle } from 'lucide-react';

export interface OutlineLesson {
    id: number;
    slug: string;
    title: string;
    reading_time: number | null;
    url: string;
    completed: boolean;
    current: boolean;
}

export interface OutlineModule {
    id: number;
    title: string;
    summary: string | null;
    done: number;
    total: number;
    minutes: number;
    lessons: OutlineLesson[];
}

/**
 * The contents panel.
 *
 * Rendered from a payload the server assembled in one pass, so turning a page
 * inside a course costs the same as any other page — see CoursePresenter for
 * why that matters when the panel is on screen for the whole course.
 *
 * The current lesson is marked with `aria-current="page"` rather than colour
 * alone: in a list of thirty near-identical rows, "which one am I on" is
 * exactly the question a screen reader user is asking too.
 */
export function CourseOutline({ modules, className }: { modules: OutlineModule[]; className?: string }) {
    let counter = 0;

    return (
        <nav className={cn('flex flex-col gap-6', className)} aria-label="Course contents">
            {modules.map((module, moduleIndex) => {
                const finished = module.total > 0 && module.done >= module.total;

                return (
                    <section key={module.id}>
                        <h2
                            className="flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase"
                            style={{ color: finished ? 'var(--u-accent)' : 'var(--u-text-muted)' }}
                        >
                            {/*
                          A long course reads as a set of finishable parts
                          rather than one bar that barely moves, so each module
                          carries its own state.
                        */}
                            {finished && <Check className="size-3" />}
                            {moduleIndex + 1}. {module.title}
                            {module.total > 0 && (
                                <span className="ml-auto tabular-nums opacity-70">
                                    {module.done}/{module.total}
                                </span>
                            )}
                        </h2>

                        {module.summary && (
                            <p className="mt-1.5 text-xs leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                {module.summary}
                            </p>
                        )}

                        <ol className="mt-3 flex flex-col gap-0.5">
                            {module.lessons.map((lesson) => {
                                counter += 1;

                                return (
                                    <li key={lesson.id}>
                                        <Link
                                            href={lesson.url}
                                            aria-current={lesson.current ? 'page' : undefined}
                                            className="flex items-start gap-2.5 rounded-[9px] px-2.5 py-2 text-sm transition-colors"
                                            style={{
                                                backgroundColor: lesson.current ? 'var(--u-accent-soft)' : 'transparent',
                                                color: lesson.current ? 'var(--u-accent)' : 'var(--u-text-muted)',
                                            }}
                                        >
                                            <span aria-hidden className="mt-0.5 shrink-0">
                                                {lesson.completed ? (
                                                    <Check className="size-3.5" style={{ color: 'var(--u-accent)' }} />
                                                ) : (
                                                    <Circle className="size-3.5 opacity-40" />
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                <span className="block leading-snug">{lesson.title}</span>
                                                {lesson.reading_time != null && (
                                                    <span className="mt-0.5 block text-[11px] opacity-70">{lesson.reading_time} min</span>
                                                )}
                                            </span>
                                            <span className="sr-only">
                                                Lesson {counter}
                                                {lesson.completed ? ', completed' : ''}
                                            </span>
                                        </Link>
                                    </li>
                                );
                            })}

                            {module.lessons.length === 0 && (
                                <li className="px-2.5 py-2 text-xs italic" style={{ color: 'var(--u-text-muted)' }}>
                                    No lessons in this module yet.
                                </li>
                            )}
                        </ol>
                    </section>
                );
            })}
        </nav>
    );
}

/** Thin progress readout, shared by the syllabus and the lesson panel. */
export function CourseProgress({ done, total }: { done: number; total: number }) {
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;

    return (
        <div>
            <div
                className="h-1.5 overflow-hidden rounded-full"
                style={{ backgroundColor: 'var(--u-border)' }}
                role="progressbar"
                aria-valuenow={percent}
                aria-valuemin={0}
                aria-valuemax={100}
                aria-label="Course progress"
            >
                <div className="h-full transition-[width] duration-500" style={{ width: `${percent}%`, backgroundColor: 'var(--u-accent)' }} />
            </div>
            <p className="mt-2 text-xs tabular-nums" style={{ color: 'var(--u-text-muted)' }}>
                {done} of {total} lessons done · {percent}%
            </p>
        </div>
    );
}
