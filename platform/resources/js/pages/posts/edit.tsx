import { PostForm } from '@/components/post-form';
import SiteLayout from '@/layouts/site-layout';
import type { PersonaSummary, PostCard } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PersonaOption {
    id: number;
    handle: string;
    display_name: string;
    universe: PersonaSummary['universe'];
}

interface Props {
    post: PostCard & { body: string; persona_id: number | null };
    personas: PersonaOption[];
}

export default function PostEdit({ post, personas }: Props) {
    return (
        <SiteLayout wide>
            <Head title={`Editing ${post.title}`} />

            <header className="mb-9">
                <Link href={`/posts/${post.slug}`} className="inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                    <ArrowLeft className="size-4" />
                    Back to the piece
                </Link>
            </header>

            <PostForm
                personas={personas}
                post={{
                    slug: post.slug,
                    title: post.title,
                    body: post.body,
                    status: (post.status ?? 'draft') as 'draft' | 'published',
                    cover_url: post.cover_url,
                    persona_id: post.persona_id,
                }}
            />
        </SiteLayout>
    );
}
