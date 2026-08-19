import { AdSlot, type Ad } from '@/components/ad-slot';
import { CourseOutline, CourseProgress, type OutlineModule } from '@/components/course-outline';
import { Chip, Panel, Rail } from '@/components/metal';
import { SeoHead, type SeoPayload } from '@/components/seo-head';
import { ShareMenu } from '@/components/share-menu';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import { Clock, Layers, PartyPopper, PenLine, PlayCircle } from 'lucide-react';
import type { CourseCard } from './index';

interface Props {
    course: CourseCard & {
        estimated_minutes: number;
        remaining_minutes: number;
        is_complete: boolean;
        can: { update: boolean };
    };
    outline: OutlineModule[];
    resume: { title: string; url: string; restarting: boolean } | null;
    ads: Ad[];
    seo: SeoPayload;
}

export default function CourseShow({ course, outline, resume, ads, seo }: Props) {
    const total = course.lesson_count ?? 0;
    const done = course.completed_count ?? 0;

    return (
        <SiteLayout wide>
            <Head title={course.title} />
            <SeoHead seo={seo} />

            <Panel className="mb-10 overflow-hidden">
                {course.cover_url && (
                    <div className="relative h-48 sm:h-72">
                        <img src={course.cover_url} alt="" className="size-full object-cover" />
                        <div className="absolute inset-0" style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 65%)' }} />
                    </div>
                )}

                <div className="p-6 sm:p-9">
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <Chip tone="accent">{course.level}</Chip>
                        {course.universe && <Chip>{course.universe.name}</Chip>}
                        {course.status === 'draft' && <Chip>Draft — only you can see this</Chip>}
                    </div>

                    <h1 className="font-display text-4xl leading-tight sm:text-6xl">{course.title}</h1>

                    {course.subtitle && (
                        <p className="mt-4 max-w-3xl text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {course.subtitle}
                        </p>
                    )}

                    <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        {course.persona && (
                            <Link href={`/@${course.persona.handle}`} className="hover:underline">
                                by <span style={{ color: 'var(--u-text)' }}>{course.persona.display_name}</span>
                            </Link>
                        )}
                        <span className="inline-flex items-center gap-1.5">
                            <Layers className="size-4" />
                            {total} {total === 1 ? 'lesson' : 'lessons'}
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="size-4" />
                            {done > 0 && !course.is_complete && course.remaining_minutes > 0
                                ? `${course.remaining_minutes} min left of ${course.estimated_minutes}`
                                : `about ${course.estimated_minutes} min`}
                        </span>
                    </div>

                    {course.description && (
                        <>
                            <Rail className="my-7" />
                            <p className="max-w-3xl text-base leading-relaxed whitespace-pre-line" style={{ color: 'var(--u-text-muted)' }}>
                                {course.description}
                            </p>
                        </>
                    )}

                    {course.is_complete && (
                        <Panel className="mt-8 flex flex-wrap items-center gap-4 p-5">
                            <PartyPopper className="size-5 shrink-0" style={{ color: 'var(--u-accent)' }} />
                            <p className="min-w-0 flex-1 text-sm">
                                <strong>You finished this course.</strong>
                                <span style={{ color: 'var(--u-text-muted)' }}>
                                    {' '}
                                    Every lesson is marked done — your marks and notes stay where you left them.
                                </span>
                            </p>
                        </Panel>
                    )}

                    <div className="mt-8 flex flex-wrap items-center gap-3">
                        {resume && (
                            <Link href={resume.url} className="u-btn u-btn-primary py-3">
                                <PlayCircle className="size-4" />
                                {done === 0 ? 'Start the course' : resume.restarting ? 'Read it again' : 'Continue'}
                            </Link>
                        )}
                        <ShareMenu url={seo.canonical} title={course.title} />
                        {course.can.update && (
                            <Link href={`/courses/${course.slug}/edit`} className="u-btn u-btn-ghost">
                                <PenLine className="size-4" />
                                Edit
                            </Link>
                        )}
                    </div>

                    {done > 0 && (
                        <div className="mt-7 max-w-sm">
                            <CourseProgress done={done} total={total} />
                        </div>
                    )}
                </div>
            </Panel>

            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_300px]">
                <div>
                    <h2 className="font-display mb-6 text-2xl">What you'll work through</h2>
                    <Panel className="p-6 sm:p-8">
                        <CourseOutline modules={outline} />
                    </Panel>
                </div>

                <aside>{ads.length > 0 && <AdSlot ads={ads} />}</aside>
            </div>
        </SiteLayout>
    );
}
