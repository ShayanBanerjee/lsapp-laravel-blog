import { Panel, Rail, SectionHeading, Swatch } from '@/components/metal';
import { PostCard } from '@/components/post-card';
import { Reveal } from '@/components/reveal';
import { UniverseCard } from '@/components/universe-card';
import { useParallax } from '@/hooks/use-motion';
import SiteLayout from '@/layouts/site-layout';
import type { PostCard as PostCardData, SharedData, UniversePreview } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, PenLine } from 'lucide-react';

interface Props {
    featured: PostCardData[];
    universes: UniversePreview[];
    stats: { posts: number; universes: number };
}

export default function Welcome({ featured, universes, stats }: Props) {
    const { auth, activeUniverse } = usePage<SharedData>().props;
    const [parallaxRef, parallax] = useParallax<HTMLDivElement>(120);

    // Backdrop follows the reader's current world, falling back to the first.
    const hero = universes.find((universe) => universe.slug === activeUniverse?.slug) ?? universes[0];

    return (
        <SiteLayout wide>
            <Head title="Write in six worlds" />

            {/* Hero */}
            <section className="relative pt-6 pb-20 text-center sm:pt-10">
                {/* Photographic backdrop, drifting slower than the page. */}
                {hero && (
                    <div
                        ref={parallaxRef}
                        aria-hidden
                        className="pointer-events-none absolute inset-x-0 -top-24 -z-10 h-[78vh] overflow-hidden will-change-transform"
                        style={{ transform: `translate3d(0, ${parallax}px, 0)` }}
                    >
                        <img src={hero.hero_image} alt="" className="size-full scale-110 object-cover opacity-45" fetchPriority="high" />
                        <div
                            className="absolute inset-0"
                            style={{
                                background:
                                    'linear-gradient(to bottom, color-mix(in srgb, var(--u-bg) 62%, transparent) 0%, color-mix(in srgb, var(--u-bg) 72%, transparent) 45%, var(--u-bg) 92%)',
                            }}
                        />
                    </div>
                )}

                <p className="mb-5 text-[11px] font-semibold tracking-[0.22em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    {stats.universes} universes · {stats.posts} pieces published
                </p>

                <h1 className="font-display mx-auto max-w-4xl text-5xl leading-[1.04] sm:text-7xl">
                    Every writer contains
                    <span className="block" style={{ color: 'var(--u-accent)' }}>
                        more than one person.
                    </span>
                </h1>

                <p className="mx-auto mt-7 max-w-2xl text-lg leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                    Inkfathom gives each of them somewhere to live. Choose a universe, take on a persona, and the whole platform — palette, texture,
                    typography, light — becomes that world.
                </p>

                <div className="mt-10 flex flex-wrap items-center justify-center gap-3">
                    {auth.user ? (
                        <Link href="/write" className="u-btn u-btn-primary px-6 py-3 text-base">
                            <PenLine className="size-4" />
                            Start a new piece
                        </Link>
                    ) : (
                        <Link href="/register" className="u-btn u-btn-primary px-6 py-3 text-base">
                            Claim your first persona
                            <ArrowRight className="size-4" />
                        </Link>
                    )}
                    <Link href="/universes" className="u-btn u-btn-ghost px-6 py-3 text-base">
                        Tour the universes
                    </Link>
                </div>

                {/* Universe strip — the whole product proposition in one row. */}
                <div className="mt-16 flex flex-wrap items-center justify-center gap-x-8 gap-y-4">
                    {universes.map((universe) => (
                        <Link
                            key={universe.slug}
                            href={`/universes/${universe.slug}`}
                            className="group flex items-center gap-2.5 text-sm transition-opacity hover:opacity-100"
                            style={{ color: 'var(--u-text-muted)', opacity: 0.75 }}
                        >
                            <Swatch swatch={universe.swatch} size={22} />
                            {universe.name}
                        </Link>
                    ))}
                </div>
            </section>

            <Rail />

            {/* How it works */}
            <section className="py-20">
                <SectionHeading eyebrow="How it works" title="One account. Many selves." />

                <div className="grid gap-5 md:grid-cols-3">
                    {[
                        {
                            step: '01',
                            title: 'Choose a universe',
                            body: 'Six worlds, each with its own material and mood — from iridescent titanium under a cosmos to oxidized brass in a jungle.',
                        },
                        {
                            step: '02',
                            title: 'Become a persona',
                            body: 'Every persona has its own handle, voice, and following. Readers follow the persona, not the account behind it.',
                        },
                        {
                            step: '03',
                            title: 'Write, and the world follows',
                            body: 'Switch persona and the entire interface re-themes around you. The universe is the writing environment, not a skin.',
                        },
                    ].map((item, index) => (
                        <Reveal key={item.step} delay={index * 90}>
                            <Panel className="h-full p-7">
                                <span className="font-display text-4xl" style={{ color: 'var(--u-accent)' }}>
                                    {item.step}
                                </span>
                                <h3 className="mt-4 text-lg font-semibold">{item.title}</h3>
                                <p className="mt-2.5 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                                    {item.body}
                                </p>
                            </Panel>
                        </Reveal>
                    ))}
                </div>
            </section>

            {/* Universes */}
            <section className="py-12">
                <SectionHeading
                    eyebrow="The worlds"
                    title="Six universes"
                    action={
                        <Link href="/universes" className="u-btn u-btn-ghost">
                            See all
                            <ArrowRight className="size-4" />
                        </Link>
                    }
                />

                <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    {universes.map((universe, index) => (
                        <Reveal key={universe.slug} delay={Math.min(index * 80, 360)}>
                            <UniverseCard universe={universe} />
                        </Reveal>
                    ))}
                </div>
            </section>

            {/* Latest */}
            {featured.length > 0 && (
                <section className="py-12">
                    <SectionHeading
                        eyebrow="Latest"
                        title="Recently published"
                        action={
                            <Link href="/posts" className="u-btn u-btn-ghost">
                                Read everything
                                <ArrowRight className="size-4" />
                            </Link>
                        }
                    />

                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {featured.map((post, index) => (
                            <Reveal
                                key={post.slug}
                                delay={Math.min(index * 80, 360)}
                                className={index === 0 ? 'sm:col-span-2 sm:row-span-2' : undefined}
                            >
                                <PostCard post={post} featured={index === 0} />
                            </Reveal>
                        ))}
                    </div>
                </section>
            )}

            {/* Closing CTA */}
            <section className="pt-12 pb-8">
                <Panel className="relative overflow-hidden px-8 py-16 text-center sm:px-16">
                    <div className="u-halo absolute inset-0" aria-hidden />
                    <div className="relative">
                        <h2 className="font-display mx-auto max-w-2xl text-4xl leading-tight sm:text-5xl">
                            Your next voice is waiting in another world.
                        </h2>
                        <p className="mx-auto mt-5 max-w-lg text-base" style={{ color: 'var(--u-text-muted)' }}>
                            Two universes are free forever. The other four come with the full set of personas and the custom theme editor.
                        </p>
                        <div className="mt-9 flex flex-wrap justify-center gap-3">
                            <Link href={auth.user ? '/write' : '/register'} className="u-btn u-btn-primary px-6 py-3 text-base">
                                {auth.user ? 'Write something' : 'Create your account'}
                            </Link>
                            <Link href="/upgrade" className="u-btn u-btn-ghost px-6 py-3 text-base">
                                See what premium unlocks
                            </Link>
                        </div>
                    </div>
                </Panel>
            </section>
        </SiteLayout>
    );
}
