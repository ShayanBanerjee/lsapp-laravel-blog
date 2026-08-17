import { EmptyState, Panel } from '@/components/metal';
import { Reveal } from '@/components/reveal';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';

interface LetterRow {
    id: number;
    body: string;
    from: string;
    post: { slug: string; title: string } | null;
    received_human: string | null;
    unread: boolean;
}

export default function LettersIndex({ letters }: { letters: LetterRow[] }) {
    return (
        <SiteLayout>
            <Head title="Letters" />

            <header className="mb-10">
                <h1 className="font-display text-4xl sm:text-5xl">Letters</h1>
                <p className="mt-3 text-base" style={{ color: 'var(--u-text-muted)' }}>
                    Private notes from readers. Nobody else can see these, which is exactly why people mean them.
                </p>
            </header>

            {letters.length === 0 ? (
                <EmptyState
                    title="No letters yet"
                    body="When a reader wants to tell you something without an audience watching, it arrives here. Keep writing — these tend to come from the pieces you were least sure about."
                    action={
                        <Link href="/write" className="u-btn u-btn-primary">
                            Write something
                        </Link>
                    }
                />
            ) : (
                <div className="flex flex-col gap-4">
                    {letters.map((letter, index) => (
                        <Reveal key={letter.id} delay={Math.min(index * 60, 300)}>
                            <Panel className="p-6">
                                <div className="mb-4 flex flex-wrap items-center gap-3">
                                    <Mail className="size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                                    <span className="text-sm font-medium">{letter.from}</span>
                                    {letter.unread && (
                                        <span
                                            className="rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase"
                                            style={{ backgroundColor: 'var(--u-accent-soft)', color: 'var(--u-accent)' }}
                                        >
                                            New
                                        </span>
                                    )}
                                    <span className="ml-auto text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        {letter.received_human}
                                    </span>
                                </div>

                                <p className="text-base leading-relaxed whitespace-pre-line">{letter.body}</p>

                                {letter.post && (
                                    <p className="mt-4 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                        about{' '}
                                        <Link href={`/posts/${letter.post.slug}`} style={{ color: 'var(--u-accent)' }}>
                                            {letter.post.title}
                                        </Link>
                                    </p>
                                )}
                            </Panel>
                        </Reveal>
                    ))}
                </div>
            )}
        </SiteLayout>
    );
}
