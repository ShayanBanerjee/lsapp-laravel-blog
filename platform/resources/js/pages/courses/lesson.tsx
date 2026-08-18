import { AdSlot, type Ad } from '@/components/ad-slot';
import { CourseOutline, outlineProgress, type OutlineLesson, type OutlineModule } from '@/components/course-outline';
import { Panel, Rail } from '@/components/metal';
import { Readable, type OwnHighlight, type Passage, type SelectionAnchor } from '@/components/readable';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, Universe } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check, Clock, List } from 'lucide-react';

interface Props {
    course: { slug: string; title: string; universe: Universe | null };
    outline: OutlineModule[];
    lesson: {
        slug: string;
        title: string;
        body: string;
        reading_time: number | null;
        marks: number | null;
        completed: boolean;
    };
    position: { index: number; total: number; previous: OutlineLesson | null; next: OutlineLesson | null };
    marked: Passage[];
    highlights: OwnHighlight[];
    ads: Ad[];
}

export default function CourseLesson({ course, outline, lesson, position, marked, highlights, ads }: Props) {
    const { auth } = usePage<SharedData>().props;
    const signedIn = Boolean(auth.user);
    const { done, total } = outlineProgress(outline);

    // A lesson is a post, so marking uses the post endpoints unchanged.
    const mark = (anchor: SelectionAnchor) => router.post(`/posts/${lesson.slug}/highlights`, anchor, { preserveScroll: true, preserveState: false });

    const unmark = (id: number) => router.delete(`/highlights/${id}`, { preserveScroll: true, preserveState: false });

    const toggleComplete = () =>
        router.post(`/learn/${course.slug}/${lesson.slug}/progress`, {}, { preserveScroll: true, preserveState: false });

    return (
        <SiteLayout wide>
            <Head title={lesson.title} />

            {/* min-w-0 on the content column, or long code lines blow the page out sideways */}
            <div className="grid gap-10 lg:grid-cols-[248px_minmax(0,1fr)] lg:gap-14">
                <aside className="min-w-0">
                    {/* Sticky on desktop; a plain disclosure on mobile, where a
                        sticky panel would eat most of the screen. */}
                    <div className="hidden lg:sticky lg:top-24 lg:block">
                        <Link href={`/learn/${course.slug}`} className="mb-5 inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            <ArrowLeft className="size-4" />
                            <span className="min-w-0 truncate">{course.title}</span>
                        </Link>

                        <Progress done={done} total={total} />

                        <div className="mt-6 max-h-[calc(100vh-16rem)] overflow-y-auto pr-1">
                            <CourseOutline modules={outline} courseSlug={course.slug} currentSlug={lesson.slug} />
                        </div>
                    </div>

                    <details className="lg:hidden">
                        <summary className="u-btn u-btn-ghost cursor-pointer list-none">
                            <List className="size-4" />
                            Contents — {position.index} of {position.total}
                        </summary>
                        <Panel className="mt-4 p-5">
                            <Progress done={done} total={total} />
                            <div className="mt-5">
                                <CourseOutline modules={outline} courseSlug={course.slug} currentSlug={lesson.slug} />
                            </div>
                        </Panel>
                    </details>
                </aside>

                <div className="min-w-0">
                    <article>
                        <header className="mb-10">
                            <p className="mb-3 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                                Lesson {position.index} of {position.total}
                            </p>

                            <h1 className="font-display text-3xl leading-tight sm:text-5xl">{lesson.title}</h1>

                            <div className="mt-4 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                {lesson.reading_time && (
                                    <span className="flex items-center gap-1.5">
                                        <Clock className="size-3.5" />
                                        {lesson.reading_time} min read
                                    </span>
                                )}
                                {signedIn && (
                                    <button
                                        type="button"
                                        onClick={toggleComplete}
                                        aria-pressed={lesson.completed}
                                        className="u-btn u-btn-ghost ml-auto"
                                        style={lesson.completed ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                    >
                                        <Check className="size-3.5" />
                                        {lesson.completed ? 'Completed' : 'Mark complete'}
                                    </button>
                                )}
                            </div>
                        </header>

                        {signedIn && (
                            <p className="mb-8 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                Select any passage to mark it — marking works here exactly as it does on any other piece.
                            </p>
                        )}

                        <Readable html={lesson.body} passages={marked} own={highlights} canMark={signedIn} onMark={mark} onUnmark={unmark} />
                    </article>

                    <Rail className="my-12" />

                    <nav className="flex flex-wrap items-center justify-between gap-4">
                        {position.previous ? (
                            <Link href={`/learn/${course.slug}/${position.previous.slug}`} className="u-btn u-btn-ghost min-w-0">
                                <ArrowLeft className="size-4 shrink-0" />
                                <span className="min-w-0 truncate">{position.previous.title}</span>
                            </Link>
                        ) : (
                            <span />
                        )}

                        {position.next && (
                            <Link href={`/learn/${course.slug}/${position.next.slug}`} className="u-btn u-btn-primary ml-auto min-w-0">
                                <span className="min-w-0 truncate">{position.next.title}</span>
                                <ArrowRight className="size-4 shrink-0" />
                            </Link>
                        )}
                    </nav>

                    {/* Between pieces, never inside one. */}
                    {ads.length > 0 && <AdSlot ads={ads} className="mt-12" />}
                </div>
            </div>
        </SiteLayout>
    );
}

function Progress({ done, total }: { done: number; total: number }) {
    const pct = total === 0 ? 0 : Math.round((done / total) * 100);

    return (
        <div>
            <div className="mb-2 flex items-center justify-between text-xs" style={{ color: 'var(--u-text-muted)' }}>
                <span>Progress</span>
                <span className="tabular-nums">
                    {done}/{total}
                </span>
            </div>
            <div className="h-1 w-full overflow-hidden rounded-full" style={{ background: 'var(--u-surface-2)' }}>
                <div
                    className="h-full rounded-full transition-[width] duration-500"
                    style={{ width: `${pct}%`, background: 'var(--u-accent)' }}
                    role="progressbar"
                    aria-valuenow={done}
                    aria-valuemin={0}
                    aria-valuemax={total}
                    aria-label="Lessons completed"
                />
            </div>
        </div>
    );
}
