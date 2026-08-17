import { PostForm } from '@/components/post-form';
import SiteLayout from '@/layouts/site-layout';
import type { PersonaSummary } from '@/types';
import { Head } from '@inertiajs/react';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

export default function PostCreate({ personas }: { personas: PersonaOption[] }) {
    return (
        <SiteLayout wide>
            <Head title="Write" />

            <header className="mb-9">
                <p className="text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    New piece
                </p>
            </header>

            <PostForm personas={personas} />
        </SiteLayout>
    );
}
