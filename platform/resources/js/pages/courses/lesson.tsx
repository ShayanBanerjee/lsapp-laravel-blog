import { Action } from '@/components/action';
import { CourseOutline, CourseProgress, type OutlineModule } from '@/components/course-outline';
import { Panel, Rail } from '@/components/metal';
import { Readable, type OwnHighlight, type Passage, type SelectionAnchor } from '@/components/readable';
import { SeoHead, type SeoPayload } from '@/components/seo-head';
import { ShareMenu } from '@/components/share-menu';
import { useReadingProgress } from '@/hooks/use-reading-progress';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Clock, ListTree, PartyPopper, X } from 'lucide-react';
import { useEffect, useState, type Ref } from 'react';
import type { CourseCard } from './index';

interface Props {
    course: CourseCard;
    outline: OutlineModule[];
    lesson: {
        id: number;
        slug: string;
        title: string;
        body: string;
        reading_time: number | null;
        completed: boolean;
        number: number;
        total: number;
    };
    previous: { title: string; url: string } | null;
    next: { title: string; url: string } | null;
    progress: { done: number; total: number; remaining_minutes: number; finishes_course: boolean };
    passages: Passage[];
    myHighlights: OwnHighlight[];
    seo: SeoPayload;
}

/**
 * One lesson, with the contents beside it.
 *
 * The panel is `position: sticky` rather than a scroll-synced fixed element:
 * it stays put while the lesson scrolls, keeps its own scrollbar when a long
 * course overflows, and needs no JavaScript at all to do either. On small
 * screens the same list is a sheet — a 30-item outline above the text would
 * put the actual lesson below the fold on every page turn.
 */
export default function Lesson({ course, outline, lesson, previous, next, progress, passages, myHighlights, seo }: Props) {
    const { auth } = usePage<SharedData>().props;
    const signedIn = Boolean(auth.user);
    const [contentsOpen, setContentsOpen] = useState(false);

    // Identical to the post page: lessons are posts, so the endpoints are the
    // same ones and the marking layer is not reimplemented here.
    const mark = (anchor: SelectionAnchor) => router.post(`/posts/${lesson.slug}/highlights`, anchor, { preserveScroll: true, preserveState: false });

    const unmark = (id: number) => router.delete(`/highlights/${id}`, { preserveScroll: true, preserveState: false });

    const toggleDone = () => router.post(`/courses/${course.slug}/${lesson.slug}/progress`, {}, { preserveScroll: true, preserveState: false });

    /**
     * Mark this lesson finished and go straight on.
     *
     * Done-then-next is the commonest interaction in the whole course reader,
     * and splitting it across two clicks is friction on the exact action the
     * product wants to encourage.
     */
    const doneAndContinue = () => {
        router.post(
            `/courses/${course.slug}/${lesson.slug}/progress`,
            {},
            {
                onSuccess: () => {
                    if (next) router.visit(next.url);
                },
            },
        );
    };

    const { barRef, articleRef } = useReadingProgress();

    /*
     * Keyboard navigation. A course is read in sequence, so the sequence should
     * be operable from the keyboard — and the handler stands down while a field
     * is focused, so it never hijacks someone typing a response.
     */
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            const target = event.target as HTMLElement | null;

            if (target && /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName)) return;
            if (target?.isContentEditable) return;
            if (event.metaKey || event.ctrlKey || event.altKey) return;

            if ((event.key === 'ArrowRight' || event.key === 'j') && next) {
                router.visit(next.url);
            } else if ((event.key === 'ArrowLeft' || event.key === 'k') && previous) {
                router.visit(previous.url);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [next, previous]);

    const done = progress.done;

    return (
        <SiteLayout wide>
            <Head title={`${lesson.title} — ${course.title}`} />
            <SeoHead seo={seo} />

            {/*
              Reading progress for this lesson. Driven straight to the DOM —
              a scroll-linked value in React state would reconcile the tree on
              every frame, and stuttering while somebody reads is the one thing
              a reading view must not do.
            */}
            <div className="fixed inset-x-0 top-16 z-[55] h-[2px]" aria-hidden>
                <span
                    ref={barRef as Ref<HTMLSpanElement>}
                    className="block h-full origin-left"
                    style={{ backgroundColor: 'var(--u-accent)', transform: 'scaleX(0)' }}
                />
            </div>

            <div className="grid gap-10 lg:grid-cols-[280px_minmax(0,1fr)]">
                {/* Desktop contents panel. */}
                <aside className="hidden lg:block">
                    <div className="sticky top-24 max-h-[calc(100vh-8rem)] overflow-y-auto pr-2">
                        <Link
                            href={`/courses/${course.slug}`}
                            className="mb-1 flex items-center gap-2 text-sm"
                            style={{ color: 'var(--u-text-muted)' }}
                        >
                            <ArrowLeft className="size-3.5" />
                            {course.title}
                        </Link>

                        <div className="mt-5 mb-6">
                            <CourseProgress done={done} total={lesson.total} />
                        </div>

                        <CourseOutline modules={outline} />
                    </div>
                </aside>

                <div className="min-w-0">
                    {/* Mobile: the contents open as a sheet rather than pushing
                        the lesson down the page. */}
                    <div className="mb-6 flex items-center gap-3 lg:hidden">
                        <button type="button" onClick={() => setContentsOpen(true)} className="u-btn u-btn-ghost">
                            <ListTree className="size-4" />
                            Contents
                        </button>
                        <span className="text-xs" style={{ color: 'var(--u-text-muted)' }}>
                            Lesson {lesson.number} of {lesson.total}
                            {progress.remaining_minutes > 0 && ` · ${progress.remaining_minutes} min left`}
                        </span>
                    </div>

                    <article ref={articleRef as Ref<HTMLElement>}>
                        <header className="mb-10">
                            <p className="mb-3 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                Lesson {lesson.number} of {lesson.total}
                                {progress.remaining_minutes > 0 && (
                                    <span style={{ color: 'var(--u-accent)' }}> · {progress.remaining_minutes} min left in this course</span>
                                )}
                            </p>
                            <h1 className="font-display text-3xl leading-tight sm:text-5xl">{lesson.title}</h1>

                            <div className="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                {lesson.reading_time != null && (
                                    <span className="inline-flex items-center gap-1.5">
                                        <Clock className="size-3.5" />
                                        {lesson.reading_time} min
                                    </span>
                                )}

                                <span className="ml-auto flex flex-wrap items-center gap-2">
                                    {signedIn && (
                                        <button
                                            type="button"
                                            onClick={toggleDone}
                                            aria-pressed={lesson.completed}
                                            className="u-btn u-btn-ghost"
                                            style={lesson.completed ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                        >
                                            <Check className="size-3.5" />
                                            {lesson.completed ? 'Done' : 'Mark done'}
                                        </button>
                                    )}
                                    <ShareMenu url={seo.canonical} title={lesson.title} />
                                </span>
                            </div>
                        </header>

                        {signedIn && (
                            <p className="mb-8 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                Select any passage to mark it — the same as anywhere else here.
                            </p>
                        )}

                        <Readable html={lesson.body} passages={passages} own={myHighlights} canMark={signedIn} onMark={mark} onUnmark={unmark} />
                    </article>

                    <Rail className="my-12" />

                    {/*
                      The end of a lesson is the moment a course is either
                      continued or abandoned, so it gets a real affordance
                      rather than a link. `finishes_course` turns it into a
                      finishing line — a course you complete and nothing
                      acknowledges is a course you do not recommend.
                    */}
                    {signedIn && (
                        <section className="mb-12">
                            {progress.finishes_course ? (
                                <Panel className="p-7 text-center sm:p-9">
                                    <PartyPopper className="mx-auto size-7" style={{ color: 'var(--u-accent)' }} />
                                    <h2 className="font-display mt-4 text-3xl">One lesson left</h2>
                                    <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                        Mark this done and you have finished {course.title}.
                                    </p>
                                    <Action onClick={toggleDone} size="lg" className="mt-6" icon={<Check className="size-4" />}>
                                        Finish the course
                                    </Action>
                                </Panel>
                            ) : (
                                <Panel className="flex flex-wrap items-center gap-5 p-6">
                                    <div className="min-w-0 flex-1">
                                        <h2 className="text-base font-semibold">{lesson.completed ? 'Done with this one' : 'Finished reading?'}</h2>
                                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                            {next ? `Next: ${next.title}` : 'That was the last lesson in this module.'}
                                        </p>
                                    </div>

                                    {next ? (
                                        <Action onClick={doneAndContinue} icon={<ArrowRight className="size-4" />}>
                                            {lesson.completed ? 'Next lesson' : 'Mark done and continue'}
                                        </Action>
                                    ) : (
                                        <Action
                                            onClick={toggleDone}
                                            variant={lesson.completed ? 'ghost' : 'primary'}
                                            icon={<Check className="size-4" />}
                                        >
                                            {lesson.completed ? 'Done' : 'Mark done'}
                                        </Action>
                                    )}
                                </Panel>
                            )}
                        </section>
                    )}

                    <nav className="grid gap-4 sm:grid-cols-2" aria-label="Lesson navigation">
                        {previous ? (
                            <Link href={previous.url} className="group">
                                <Panel interactive className="h-full p-5">
                                    <span className="flex items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        <ArrowLeft className="size-3.5" />
                                        Previous
                                    </span>
                                    <span className="font-display mt-2 block text-lg leading-snug">{previous.title}</span>
                                </Panel>
                            </Link>
                        ) : (
                            <span />
                        )}

                        {next ? (
                            <Link href={next.url} className="group">
                                <Panel interactive className="h-full p-5 text-right">
                                    <span className="flex items-center justify-end gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        Next
                                        <ArrowRight className="size-3.5" />
                                    </span>
                                    <span className="font-display mt-2 block text-lg leading-snug">{next.title}</span>
                                </Panel>
                            </Link>
                        ) : (
                            <Link href={`/courses/${course.slug}`}>
                                <Panel interactive className="h-full p-5 text-right">
                                    <span className="block text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        That's the last one
                                    </span>
                                    <span className="font-display mt-2 block text-lg leading-snug">Back to the course</span>
                                </Panel>
                            </Link>
                        )}
                    </nav>

                    {/* Discoverable, not hidden in a help page nobody opens. */}
                    <p className="mt-8 hidden text-center text-xs lg:block" style={{ color: 'var(--u-text-muted)' }}>
                        Tip: <kbd className="u-kbd">←</kbd> and <kbd className="u-kbd">→</kbd> move between lessons.
                    </p>
                </div>
            </div>

            {contentsOpen && (
                <div className="fixed inset-0 z-[60] lg:hidden">
                    <button
                        type="button"
                        aria-label="Close contents"
                        onClick={() => setContentsOpen(false)}
                        className="absolute inset-0"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--u-bg-deep) 80%, transparent)' }}
                    />
                    <div
                        className="absolute inset-y-0 left-0 w-[86%] max-w-sm overflow-y-auto p-6"
                        style={{ backgroundColor: 'var(--u-surface-1)', borderRight: '1px solid var(--u-border)' }}
                    >
                        <div className="mb-6 flex items-center justify-between gap-3">
                            <Link href={`/courses/${course.slug}`} className="text-sm font-medium">
                                {course.title}
                            </Link>
                            <button type="button" onClick={() => setContentsOpen(false)} className="u-btn u-btn-ghost px-2 py-1" aria-label="Close">
                                <X className="size-4" />
                            </button>
                        </div>

                        <div className="mb-6">
                            <CourseProgress done={done} total={lesson.total} />
                        </div>

                        <CourseOutline modules={outline} />
                    </div>
                </div>
            )}
        </SiteLayout>
    );
}
