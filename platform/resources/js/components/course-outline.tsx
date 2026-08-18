import { Link } from '@inertiajs/react';
import { Check, Circle } from 'lucide-react';

export interface OutlineLesson {
    slug: string;
    title: string;
    reading_time: number | null;
    completed: boolean;
}

export interface OutlineModule {
    title: string;
    summary: string | null;
    lessons: OutlineLesson[];
}

/**
 * The contents panel.
 *
 * This is the feature, not decoration: it is what lets a reader leave in the
 * middle of module three and come back to exactly there — the thing a video
 * timeline cannot do.
 */
export function CourseOutline({
    modules,
    courseSlug,
    currentSlug,
}: {
    modules: OutlineModule[];
    courseSlug: string;
    currentSlug?: string;
}) {
    let counter = 0;

    return (
        <nav aria-label="Course contents" className="flex flex-col gap-7">
            {modules.map((module) => (
                <div key={module.title}>
                    <h2 className="mb-2 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        {module.title}
                    </h2>

                    <ul className="flex flex-col gap-0.5">
                        {module.lessons.map((lesson) => {
                            counter += 1;
                            const current = lesson.slug === currentSlug;

                            return (
                                <li key={lesson.slug}>
                                    <Link
                                        href={`/learn/${courseSlug}/${lesson.slug}`}
                                        aria-current={current ? 'page' : undefined}
                                        className="flex items-start gap-2.5 rounded-md px-2 py-1.5 text-sm transition-colors"
                                        style={
                                            current
                                                ? { background: 'var(--u-accent-soft)', color: 'var(--u-accent)' }
                                                : { color: 'var(--u-text-muted)' }
                                        }
                                    >
                                        {lesson.completed ? (
                                            <Check className="mt-0.5 size-3.5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                                        ) : (
                                            <Circle className="mt-0.5 size-3.5 shrink-0 opacity-40" />
                                        )}

                                        {/* min-w-0 so the title can actually shrink and wrap */}
                                        <span className="min-w-0 flex-1 leading-snug">
                                            <span className="mr-1.5 tabular-nums opacity-50">{counter}.</span>
                                            {lesson.title}
                                        </span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

/** Completed / total across every module. */
export function outlineProgress(modules: OutlineModule[]) {
    const lessons = modules.flatMap((module) => module.lessons);

    return { done: lessons.filter((lesson) => lesson.completed).length, total: lessons.length };
}
