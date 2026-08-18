import { CourseOutline, outlineProgress, type OutlineModule } from '@/components/course-outline';
import { Chip, Panel, Rail, Swatch } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { Universe } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, GraduationCap } from 'lucide-react';

interface Props {
    course: {
        slug: string;
        title: string;
        subtitle: string | null;
        description: string | null;
        level: string;
        cover_url: string | null;
        universe: Universe | null;
        persona: { handle: string; display_name: string } | null;
    };
    outline: OutlineModule[];
}

export default function CourseShow({ course, outline }: Props) {
    const { done, total } = outlineProgress(outline);
    const first = outline.flatMap((module) => module.lessons)[0];
    const resume = outline.flatMap((module) => module.lessons).find((lesson) => !lesson.completed) ?? first;

    return (
        <SiteLayout>
            <Head title={course.title} />

            <Link href="/learn" className="mb-8 inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                <ArrowLeft className="size-4" />
                All tutorials
            </Link>

            <header className="mb-12 max-w-3xl">
                <div className="mb-5 flex flex-wrap items-center gap-3">
                    {course.universe && <Swatch swatch={course.universe.swatch} size={40} />}
                    <Chip tone="accent">{course.level}</Chip>
                    {course.persona && (
                        <span className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            by {course.persona.display_name}
                        </span>
                    )}
                </div>

                <h1 className="font-display text-4xl sm:text-6xl">{course.title}</h1>

                {course.subtitle && (
                    <p className="mt-4 text-lg" style={{ color: 'var(--u-text-muted)' }}>
                        {course.subtitle}
                    </p>
                )}

                {course.description && <p className="mt-5 text-base leading-relaxed">{course.description}</p>}

                <div className="mt-8 flex flex-wrap items-center gap-4">
                    {resume && (
                        <Link href={`/learn/${course.slug}/${resume.slug}`} className="u-btn u-btn-primary">
                            <GraduationCap className="size-4" />
                            {done > 0 ? 'Resume' : 'Start'}
                        </Link>
                    )}

                    {total > 0 && (
                        <span className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            {done} of {total} complete
                        </span>
                    )}
                </div>
            </header>

            <Rail className="mb-10" />

            <Panel className="max-w-2xl p-7">
                <CourseOutline modules={outline} courseSlug={course.slug} />
            </Panel>
        </SiteLayout>
    );
}
