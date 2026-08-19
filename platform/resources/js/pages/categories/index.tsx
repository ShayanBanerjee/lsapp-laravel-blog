import { ActionLink } from '@/components/action';
import { Panel } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import { Highlighter, PenLine } from 'lucide-react';

interface SubjectRow {
    slug: string;
    name: string;
    tagline: string | null;
    description: string | null;
    hero_image: string | null;
    prompt: string | null;
    posts_count: number | null;
    signal: { quote: string; marks: number } | null;
}

/**
 * The subject index, as an editorial contents page rather than a filter list.
 *
 * Each card carries the three things that actually decide whether someone
 * writes here: what belongs (and what does not), what a reader stopped on, and
 * a prompt for the blank page. A count alone tells you how big a room is; it
 * never tells you what is being said in it.
 */
export default function CategoriesIndex({ categories }: { categories: SubjectRow[] }) {
    const [lead, ...rest] = categories;

    return (
        <SiteLayout wide>
            <Head title="Subjects" />

            <header className="mb-12 max-w-3xl">
                <p className="mb-4 text-[11px] font-semibold tracking-[0.22em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    What it is about
                </p>
                <h1 className="font-display text-5xl leading-[1.05] sm:text-6xl">Subjects</h1>
                <p className="mt-5 text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    A universe is how a piece feels. A subject is what it is about. They are kept separate on purpose, so a technical essay can be
                    written in Abyss without the taxonomy fighting the mood.
                </p>
            </header>

            {lead && <LeadSubject subject={lead} />}

            <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                {rest.map((subject, index) => (
                    <Reveal key={subject.slug} delay={Math.min(index * 50, 300)}>
                        <SubjectCard subject={subject} />
                    </Reveal>
                ))}
            </div>
        </SiteLayout>
    );
}

/** The first subject gets the full-bleed treatment — a contents page needs a lead. */
function LeadSubject({ subject }: { subject: SubjectRow }) {
    return (
        <Panel interactive className="group overflow-hidden">
            <div className="grid lg:grid-cols-2">
                <div className="relative min-h-[240px] overflow-hidden lg:min-h-[380px]">
                    {subject.hero_image && <img src={subject.hero_image} alt="" className="img-zoom absolute inset-0 size-full object-cover" />}
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{
                            background: 'linear-gradient(to right, transparent 35%, color-mix(in srgb, var(--u-surface-1) 92%, transparent))',
                        }}
                    />
                </div>

                <div className="flex flex-col justify-center p-8 sm:p-10">
                    {subject.tagline && (
                        <p className="text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-accent)' }}>
                            {subject.tagline}
                        </p>
                    )}

                    <h2 className="font-display mt-3 text-4xl sm:text-5xl">
                        <Link href={`/categories/${subject.slug}`}>{subject.name}</Link>
                    </h2>

                    {subject.description && (
                        <p className="mt-4 max-w-lg text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {subject.description}
                        </p>
                    )}

                    {subject.signal && <Signal signal={subject.signal} />}

                    <div className="mt-7 flex flex-wrap items-center gap-3">
                        <ActionLink href={`/categories/${subject.slug}`} size="sm">
                            Read {subject.posts_count ?? 0} {subject.posts_count === 1 ? 'piece' : 'pieces'}
                        </ActionLink>
                        <ActionLink href="/write" variant="quiet" size="sm" icon={<PenLine className="size-3.5" />}>
                            Write one
                        </ActionLink>
                    </div>
                </div>
            </div>
        </Panel>
    );
}

function SubjectCard({ subject }: { subject: SubjectRow }) {
    return (
        <Panel interactive className="group flex h-full flex-col overflow-hidden">
            <Link href={`/categories/${subject.slug}`} className="flex h-full flex-col">
                <div className="relative aspect-[16/10] overflow-hidden">
                    {subject.hero_image && <img src={subject.hero_image} alt="" loading="lazy" className="img-zoom size-full object-cover" />}
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{ background: 'linear-gradient(to top, var(--u-surface-1), transparent 65%)' }}
                    />
                    <div className="absolute inset-x-0 bottom-0 p-5">
                        {subject.tagline && (
                            <p className="text-[10px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-accent)' }}>
                                {subject.tagline}
                            </p>
                        )}
                        <h2 className="font-display mt-1 text-2xl">{subject.name}</h2>
                    </div>
                </div>

                <div className="flex flex-1 flex-col p-5">
                    {subject.description && (
                        <p className="line-clamp-4 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {subject.description}
                        </p>
                    )}

                    {subject.prompt && (
                        <p
                            className="mt-4 border-l-2 pl-3 text-sm leading-snug italic"
                            style={{ borderColor: 'var(--u-border)', color: 'var(--u-text-muted)' }}
                        >
                            {subject.prompt}
                        </p>
                    )}

                    <p className="mt-auto pt-5 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        {subject.posts_count ?? 0} {subject.posts_count === 1 ? 'piece' : 'pieces'}
                    </p>
                </div>
            </Link>
        </Panel>
    );
}

function Signal({ signal }: { signal: { quote: string; marks: number } }) {
    return (
        <figure className="mt-6">
            <blockquote className="font-display text-xl leading-snug italic" style={{ color: 'var(--u-text)' }}>
                “{signal.quote}”
            </blockquote>
            <figcaption className="mt-2 flex items-center gap-1.5 text-xs" style={{ color: 'var(--u-accent)' }}>
                <Highlighter className="size-3" />
                marked by {signal.marks} {signal.marks === 1 ? 'reader' : 'readers'} in this subject
            </figcaption>
        </figure>
    );
}
