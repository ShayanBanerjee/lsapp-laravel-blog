import { Panel } from '@/components/metal';
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

export default function PostCreate({
    personas,
    prompts = [],
    canPublish = true,
}: {
    personas: PersonaOption[];
    prompts?: string[];
    canPublish?: boolean;
}) {
    return (
        <SiteLayout wide>
            <Head title="Write" />

            <header className="mb-9">
                <p className="text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    New piece
                </p>
            </header>

            {prompts.length > 0 && (
                <Panel className="mb-8 p-6">
                    <p className="mb-4 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        If you don't know where to start
                    </p>
                    <ul className="flex flex-col gap-3">
                        {prompts.map((prompt) => (
                            <li key={prompt} className="font-display text-lg leading-snug italic">
                                {prompt}
                            </li>
                        ))}
                    </ul>
                </Panel>
            )}

            <PostForm personas={personas} canPublish={canPublish} />
        </SiteLayout>
    );
}
