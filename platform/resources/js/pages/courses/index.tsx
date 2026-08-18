import { Chip, EmptyState, Panel, SectionHeading, Swatch } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { Universe } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { GraduationCap, Layers } from 'lucide-react';

interface CourseCard {
    slug: string;
    title: string;
    subtitle: string | null;
    description: string | null;
    level: string;
    cover_url: string | null;
    lessons_count: number | null;
    universe: Universe | null;
    persona: { handle: string; display_name: string } | null;
}

export default function CoursesIndex({ courses }: { courses: CourseCard[] }) {
    return (
        <SiteLayout>
            <Head title="Tutorials" />

            <header className="mb-12 max-w-3xl">
                <h1 className="font-display text-4xl sm:text-6xl">Learning paths</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Long-form tutorials in several parts, with a contents panel that keeps your place. Leave in the middle of module three and come
                    back to exactly there — and mark the sentences that landed, the same as anywhere else here.
                </p>
            </header>

            {courses.length === 0 ? (
                <EmptyState title="No tutorials yet" body="Learning paths will appear here once they are published." />
            ) : (
                <div className="grid gap-4 md:grid-cols-2">
                    {courses.map((course) => (
                        <Panel key={course.slug} interactive className="flex flex-col gap-4 p-6">
                            <div className="flex items-center gap-3">
                                {course.universe && <Swatch swatch={course.universe.swatch} size={34} />}
                                <Chip tone="accent">{course.level}</Chip>
                                {course.lessons_count !== null && (
                                    <span className="ml-auto flex items-center gap-1.5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        <Layers className="size-3.5" />
                                        {course.lessons_count} {course.lessons_count === 1 ? 'lesson' : 'lessons'}
                                    </span>
                                )}
                            </div>

                            <div className="min-w-0">
                                <Link href={`/learn/${course.slug}`} className="font-display text-2xl leading-tight hover:underline">
                                    {course.title}
                                </Link>
                                {course.subtitle && (
                                    <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                        {course.subtitle}
                                    </p>
                                )}
                            </div>

                            {course.description && (
                                <p className="line-clamp-3 min-w-0 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                    {course.description}
                                </p>
                            )}

                            <Link href={`/learn/${course.slug}`} className="u-btn u-btn-primary mt-auto self-start">
                                <GraduationCap className="size-4" />
                                Start
                            </Link>
                        </Panel>
                    ))}
                </div>
            )}

            <SectionHeading eyebrow="Why this shape" title="Addressable, not linear" />
            <p className="max-w-2xl text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                A video course makes you scrub a timeline to find the one part that mattered. Text is addressable: you can land on the third section
                of module two instantly, at your own speed, and the platform can remember you did.
            </p>
        </SiteLayout>
    );
}
