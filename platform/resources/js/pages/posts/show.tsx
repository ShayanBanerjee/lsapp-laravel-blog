import { AdSlot, type Ad } from '@/components/ad-slot';
import { Chip, Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Readable, type OwnHighlight, type Passage, type SelectionAnchor } from '@/components/readable';
import { Reveal } from '@/components/reveal';
import { SeoHead, type SeoPayload } from '@/components/seo-head';
import { ShareMenu } from '@/components/share-menu';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, Universe } from '@/types';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Bookmark, Clock, Download, Highlighter, Mail, Pencil, Quote, Star, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ResponseRow {
    id: number;
    body: string;
    author: string;
    handle: string | null;
    quote: string | null;
    highlight_id: number | null;
    created_human: string | null;
    can_delete: boolean;
}

interface Props {
    post: PostCardData & { body: string; marks: number | null; can: { update: boolean; delete: boolean } };
    universe: Universe | null;
    related: PostCardData[];
    passages: Passage[];
    myHighlights: OwnHighlight[];
    responses: ResponseRow[];
    bookmarks: { saved: boolean; starred: boolean };
    seo: SeoPayload;
    ads: Ad[];
}

export default function PostShow({ post, universe, related, passages, myHighlights, responses, bookmarks, seo, ads }: Props) {
    const { auth } = usePage<SharedData>().props;
    const signedIn = Boolean(auth.user);

    const mark = (anchor: SelectionAnchor) => router.post(`/posts/${post.slug}/highlights`, anchor, { preserveScroll: true, preserveState: false });

    const unmark = (id: number) => router.delete(`/highlights/${id}`, { preserveScroll: true, preserveState: false });

    const totalMarks = passages.reduce((sum, passage) => sum + passage.marks, 0);

    // If the reader has text selected, Share offers that sentence rather than
    // the headline — a quote travels further than a title.
    const [sharedQuote, setSharedQuote] = useState<string | null>(null);

    useEffect(() => {
        const onSelect = () => {
            const text = window.getSelection()?.toString().trim() ?? '';
            setSharedQuote(text.length > 8 && text.length < 400 ? text : null);
        };

        document.addEventListener('selectionchange', onSelect);

        return () => document.removeEventListener('selectionchange', onSelect);
    }, []);

    return (
        <SiteLayout>
            <SeoHead seo={seo} />

            <Link href="/posts" className="mb-8 inline-flex items-center gap-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                <ArrowLeft className="size-4" />
                All pieces
            </Link>

            <article>
                <header className="mb-10">
                    <div className="mb-5 flex flex-wrap items-center gap-3">
                        {universe && (
                            <Link
                                href={`/universes/${universe.slug}`}
                                className="inline-flex items-center gap-2 text-[11px] font-semibold tracking-[0.16em] uppercase"
                                style={{ color: 'var(--u-text-muted)' }}
                            >
                                <Swatch swatch={universe.swatch} size={16} />
                                {universe.name}
                            </Link>
                        )}
                        {post.status === 'draft' && <Chip tone="accent">Draft — only you can see this</Chip>}
                        {post.categories?.map((category) => (
                            <Link key={category.slug} href={`/categories/${category.slug}`}>
                                <Chip>{category.name}</Chip>
                            </Link>
                        ))}
                    </div>

                    <h1 className="font-display text-4xl leading-[1.08] sm:text-6xl">{post.title}</h1>

                    <div className="mt-7 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                        {post.persona && (
                            <span>
                                by <span style={{ color: 'var(--u-text)' }}>{post.persona.display_name}</span> @{post.persona.handle}
                            </span>
                        )}
                        {post.published_human && (
                            <>
                                <span aria-hidden>·</span>
                                <time>{post.published_human}</time>
                            </>
                        )}
                        <span aria-hidden>·</span>
                        <span className="inline-flex items-center gap-1.5">
                            <Clock className="size-3.5" />
                            {post.reading_time} min read
                        </span>
                        {totalMarks > 0 && (
                            <>
                                <span aria-hidden>·</span>
                                <span className="inline-flex items-center gap-1.5" style={{ color: 'var(--u-accent)' }}>
                                    <Highlighter className="size-3.5" />
                                    {totalMarks} {totalMarks === 1 ? 'mark' : 'marks'}
                                </span>
                            </>
                        )}

                        <span className="ml-auto flex flex-wrap items-center gap-2">
                            {signedIn && (
                                <>
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/posts/${post.slug}/bookmark`, { kind: 'saved' }, { preserveScroll: true })}
                                        className="u-btn u-btn-ghost"
                                        aria-pressed={bookmarks.saved}
                                        style={bookmarks.saved ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                    >
                                        <Bookmark className={bookmarks.saved ? 'size-3.5 fill-current' : 'size-3.5'} />
                                        {bookmarks.saved ? 'Saved' : 'Save'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => router.post(`/posts/${post.slug}/bookmark`, { kind: 'starred' }, { preserveScroll: true })}
                                        className="u-btn u-btn-ghost"
                                        aria-pressed={bookmarks.starred}
                                        aria-label={bookmarks.starred ? 'Unstar' : 'Star'}
                                        style={bookmarks.starred ? { borderColor: 'var(--u-accent)', color: 'var(--u-accent)' } : undefined}
                                    >
                                        <Star className={bookmarks.starred ? 'size-3.5 fill-current' : 'size-3.5'} />
                                    </button>
                                </>
                            )}

                            <ShareMenu url={seo.canonical} title={post.title} quote={sharedQuote} />

                            {/* Markdown out. A vault is a folder of files, so a
                                file in their format is the whole integration. */}
                            <a href={`/posts/${post.slug}/export.md`} className="u-btn u-btn-ghost" title="Download as Markdown">
                                <Download className="size-3.5" />
                                Markdown
                            </a>
                        </span>

                        {(post.can.update || post.can.delete) && (
                            <span className="flex gap-2">
                                {post.can.update && (
                                    <Link href={`/posts/${post.slug}/edit`} className="u-btn u-btn-ghost">
                                        <Pencil className="size-3.5" />
                                        Edit
                                    </Link>
                                )}
                                {post.can.delete && (
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (window.confirm(`Delete “${post.title}”? This cannot be undone.`)) {
                                                router.delete(`/posts/${post.slug}`);
                                            }
                                        }}
                                        className="u-btn u-btn-ghost"
                                    >
                                        <Trash2 className="size-3.5" />
                                        Delete
                                    </button>
                                )}
                            </span>
                        )}
                    </div>
                </header>

                {post.cover_url && (
                    <Panel className="mb-12" grain={false}>
                        <img src={post.cover_url} alt="" className="w-full object-cover" />
                    </Panel>
                )}

                {signedIn && (
                    <p className="mx-auto mb-8 max-w-2xl text-center text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        Select any passage to mark it. Marks tell the writer which sentence landed.
                    </p>
                )}

                <Readable html={post.body} passages={passages} own={myHighlights} canMark={signedIn} onMark={mark} onUnmark={unmark} />
            </article>

            {passages.length > 0 && (
                <section className="mx-auto mt-16 max-w-2xl">
                    <Rail className="mb-10" />
                    <h2 className="mb-5 text-[11px] font-semibold tracking-[0.18em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        What readers stopped on
                    </h2>
                    <div className="flex flex-col gap-3">
                        {passages.slice(0, 5).map((passage) => (
                            <Panel key={`${passage.block_index}-${passage.start_offset}`} className="p-5">
                                <p className="font-display text-lg leading-snug italic">“{passage.quote}”</p>
                                <p className="mt-2.5 text-xs" style={{ color: 'var(--u-accent)' }}>
                                    marked by {passage.marks} {passage.marks === 1 ? 'reader' : 'readers'}
                                </p>
                            </Panel>
                        ))}
                    </div>
                </section>
            )}

            <section className="mx-auto mt-16 max-w-2xl">
                <Rail className="mb-10" />
                <SectionHeading eyebrow="Responses" title={`${responses.length} ${responses.length === 1 ? 'response' : 'responses'}`} />

                {signedIn ? (
                    <ResponseForm slug={post.slug} />
                ) : (
                    <Panel className="mb-8 p-6 text-center">
                        <p className="text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            <Link href="/login" style={{ color: 'var(--u-accent)' }}>
                                Log in
                            </Link>{' '}
                            to mark passages and respond.
                        </p>
                    </Panel>
                )}

                <div className="mt-8 flex flex-col gap-3">
                    {responses.map((response) => (
                        <Panel key={response.id} className="p-5">
                            {response.quote && (
                                <p
                                    className="mb-3 border-l-2 pl-3 text-sm italic"
                                    style={{ borderColor: 'var(--u-accent)', color: 'var(--u-text-muted)' }}
                                >
                                    <Quote className="mr-1 inline size-3" />
                                    {response.quote}
                                </p>
                            )}
                            <p className="text-sm leading-relaxed whitespace-pre-line">{response.body}</p>
                            <div className="mt-3 flex items-center gap-2 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                <span style={{ color: 'var(--u-text)' }}>{response.author}</span>
                                {response.handle && <span>@{response.handle}</span>}
                                <span aria-hidden>·</span>
                                <span>{response.created_human}</span>
                                {response.can_delete && (
                                    <button
                                        type="button"
                                        onClick={() => router.delete(`/responses/${response.id}`, { preserveScroll: true })}
                                        className="ml-auto opacity-60 hover:opacity-100"
                                        aria-label="Delete response"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </button>
                                )}
                            </div>
                        </Panel>
                    ))}
                </div>
            </section>

            {signedIn && !post.can.update && <LetterForm slug={post.slug} />}

            <AdSlot ads={ads} className="mx-auto mt-14 max-w-2xl" />

            {universe && (
                <>
                    <Rail className="my-16" />
                    <Panel className="p-7 sm:p-9">
                        <div className="flex flex-wrap items-center gap-5">
                            <Swatch swatch={universe.swatch} size={54} />
                            <div className="min-w-0 flex-1">
                                <h3 className="font-display text-2xl">{universe.name}</h3>
                                <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                                    {universe.description}
                                </p>
                            </div>
                            <Link href={`/universes/${universe.slug}`} className="u-btn u-btn-primary">
                                Enter {universe.name}
                            </Link>
                        </div>
                    </Panel>
                </>
            )}

            {related.length > 0 && (
                <section className="mt-16">
                    <SectionHeading eyebrow="Same world" title="More from this universe" />
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {related.map((item, index) => (
                            <Reveal key={item.slug} delay={index * 70}>
                                <PostCard post={item} />
                            </Reveal>
                        ))}
                    </div>
                </section>
            )}
        </SiteLayout>
    );
}

function ResponseForm({ slug }: { slug: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                post(`/posts/${slug}/responses`, { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <textarea
                value={data.body}
                onChange={(event) => setData('body', event.target.value)}
                rows={3}
                placeholder="Say something worth the writer's time."
                aria-label="Your response"
                className="u-field resize-y"
            />
            {errors.body && (
                <p className="mt-2 text-sm" style={{ color: '#f0785a' }} role="alert">
                    {errors.body}
                </p>
            )}
            <button type="submit" disabled={processing || data.body.trim().length < 2} className="u-btn u-btn-primary mt-3">
                Post response
            </button>
        </form>
    );
}

/** A private note to the writer. No audience, therefore no performance. */
function LetterForm({ slug }: { slug: string }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ body: '' });

    return (
        <section className="mx-auto mt-14 max-w-2xl">
            <Panel className="p-6 sm:p-8">
                <div className="flex flex-wrap items-center gap-4">
                    <Mail className="size-6 shrink-0" style={{ color: 'var(--u-accent)' }} />
                    <div className="min-w-0 flex-1">
                        <h3 className="font-display text-xl">Send the writer a letter</h3>
                        <p className="mt-1 text-sm" style={{ color: 'var(--u-text-muted)' }}>
                            Private, and nobody else will ever see it. This is the thing writers say they miss most.
                        </p>
                    </div>
                    {!open && (
                        <button type="button" onClick={() => setOpen(true)} className="u-btn u-btn-ghost">
                            Write one
                        </button>
                    )}
                </div>

                {open && (
                    <form
                        className="mt-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            post(`/posts/${slug}/letters`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    reset();
                                    setOpen(false);
                                },
                            });
                        }}
                    >
                        <textarea
                            value={data.body}
                            onChange={(event) => setData('body', event.target.value)}
                            rows={4}
                            placeholder="What did this piece do for you?"
                            aria-label="Your letter"
                            className="u-field resize-y"
                        />
                        {errors.body && (
                            <p className="mt-2 text-sm" style={{ color: '#f0785a' }} role="alert">
                                {errors.body}
                            </p>
                        )}
                        <div className="mt-3 flex gap-2">
                            <button type="submit" disabled={processing} className="u-btn u-btn-primary">
                                Send privately
                            </button>
                            <button type="button" onClick={() => setOpen(false)} className="u-btn u-btn-ghost">
                                Cancel
                            </button>
                        </div>
                    </form>
                )}
            </Panel>
        </section>
    );
}
