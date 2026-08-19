import { AdSlot, type Ad } from '@/components/ad-slot';
import { Chip, EmptyState, Panel } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import type { SharedData, UniversePreview } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Layers, Plus } from 'lucide-react';

export interface CourseCard {
    slug: string;
    title: string;
    subtitle: string | null;
    description: string | null;
    cover_url: string | null;
    level: string;
    status: string;
    published_human: string | null;
    lesson_count: number | null;
    completed_count: number | null;
    persona: { handle: string; display_name: string } | null;
    universe: UniversePreview | null;
}

interface Paginated<T> {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
}

export default function CoursesIndex({ courses, ads }: { courses: Paginated<CourseCard>; ads: Ad[] }) {
    const { auth } = usePage<SharedData>().props;

    return (
        <SiteLayout wide>
            <Head title="Courses" />

            <header className="mb-9 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="mb-3 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Learning paths
                    </p>
                    <h1 className="font-display text-4xl sm:text-5xl">Courses</h1>
                    <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        Long-form teaching, in order. Every lesson is still a piece of writing — you can mark a passage, answer it, or write to the
                        author, exactly as anywhere else.
                    </p>
                </div>

                {auth.user && (
                    <Link href="/courses/new" className="u-btn u-btn-primary">
                        <Plus className="size-4" />
                        Build a course
                    </Link>
                )}
            </header>

            {courses.data.length === 0 ? (
                <EmptyState
                    title="No courses yet"
                    body="Nobody has published a learning path here yet. If you know a subject well enough to teach it in order, this is the place."
                    action={
                        auth.user ? (
                            <Link href="/courses/new" className="u-btn u-btn-primary">
                                Build the first one
                            </Link>
                        ) : (
                            <Link href="/register" className="u-btn u-btn-primary">
                                Start writing
                            </Link>
                        )
                    }
                />
            ) : (
                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {courses.data.map((course, index) => (
                        <Reveal key={course.slug} delay={Math.min(index * 60, 300)}>
                            <CourseCardView course={course} />
                        </Reveal>
                    ))}
                </div>
            )}

            {ads.length > 0 && (
                <div className="mt-12">
                    <AdSlot ads={ads} />
                </div>
            )}
        </SiteLayout>
    );
}

export function CourseCardView({ course }: { course: CourseCard }) {
    const total = course.lesson_count ?? 0;
    const done = course.completed_count ?? 0;
    const percent = total > 0 ? Math.round((done / total) * 100) : 0;

    return (
        <Panel interactive className="h-full">
            <Link href={`/courses/${course.slug}`} className="flex h-full flex-col">
                {course.cover_url && (
                    <div className="relative aspect-[16/9] overflow-hidden rounded-t-[13px]">
                        <img src={course.cover_url} alt="" className="size-full object-cover" loading="lazy" />
                    </div>
                )}

                <div className="flex flex-1 flex-col p-5">
                    <div className="mb-3 flex flex-wrap items-center gap-2">
                        <Chip tone="accent">{course.level}</Chip>
                        {course.universe && <Chip>{course.universe.name}</Chip>}
                        {course.status === 'draft' && <Chip>Draft</Chip>}
                    </div>

                    <h2 className="font-display text-xl leading-snug">{course.title}</h2>

                    {course.subtitle && (
                        <p className="mt-2 line-clamp-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {course.subtitle}
                        </p>
                    )}

                    <div className="mt-auto pt-5">
                        {/* Progress only appears once there is progress — an
                            empty bar on every card is decoration, not information. */}
                        {done > 0 && (
                            <div className="mb-3">
                                <div className="h-1 overflow-hidden rounded-full" style={{ backgroundColor: 'var(--u-border)' }}>
                                    <div className="h-full" style={{ width: `${percent}%`, backgroundColor: 'var(--u-accent)' }} />
                                </div>
                                <p className="mt-1.5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    {done} of {total} done
                                </p>
                            </div>
                        )}

                        <div className="flex items-center gap-3 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                            {course.persona && <span className="font-medium">@{course.persona.handle}</span>}
                            <span className="ml-auto inline-flex items-center gap-1.5">
                                <Layers className="size-3.5" />
                                {total} {total === 1 ? 'lesson' : 'lessons'}
                            </span>
                        </div>
                    </div>
                </div>
            </Link>
        </Panel>
    );
}
