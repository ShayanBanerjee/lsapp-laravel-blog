import { CourseMetaFields } from '@/components/course-meta-fields';
import { Panel } from '@/components/metal';
import SiteLayout from '@/layouts/site-layout';
import type { PersonaSummary } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { Loader2, Plus } from 'lucide-react';
import type { FormEvent } from 'react';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

interface Props {
    personas: PersonaOption[];
    levels: string[];
    canPublish: boolean;
}

export default function CourseCreate({ personas, levels, canPublish }: Props) {
    const { data, setData, post, processing, errors, progress } = useForm({
        title: '',
        subtitle: '',
        description: '',
        persona_id: personas[0]?.id ?? '',
        level: levels[0] ?? 'beginner',
        // A course is created as a draft and stays that way until it has
        // lessons — publishing an empty syllabus is never what anyone meant.
        status: 'draft' as 'draft' | 'published',
        cover_image: null as File | null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/courses', { forceFormData: true });
    };

    if (personas.length === 0) {
        return (
            <SiteLayout>
                <Head title="Build a course" />
                <Panel className="p-10 text-center">
                    <h1 className="font-display text-2xl">You need a persona first</h1>
                    <p className="mx-auto mt-3 max-w-md text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        A course is taught by a voice, and every voice belongs to a universe.
                    </p>
                    <Link href="/personas" className="u-btn u-btn-primary mt-6">
                        Create a persona
                    </Link>
                </Panel>
            </SiteLayout>
        );
    }

    return (
        <SiteLayout>
            <Head title="Build a course" />

            <header className="mb-9">
                <p className="mb-3 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    New course
                </p>
                <h1 className="font-display text-4xl sm:text-5xl">What are you teaching?</h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Set the shape of it here. You'll add modules and lessons next — nothing is public until you publish it.
                </p>
            </header>

            <form onSubmit={submit} className="flex flex-col gap-6">
                <CourseMetaFields
                    data={data}
                    setData={setData as (key: string, value: unknown) => void}
                    errors={errors}
                    personas={personas}
                    levels={levels}
                    canPublish={canPublish}
                    progress={progress}
                />

                <div>
                    <button type="submit" disabled={processing} className="u-btn u-btn-primary py-3">
                        {processing ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                        Create and add lessons
                    </button>
                </div>
            </form>
        </SiteLayout>
    );
}
