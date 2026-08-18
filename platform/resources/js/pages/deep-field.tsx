import { Swatch } from '@/components/metal';
import { prefersReducedMotion, useScrollProgress } from '@/hooks/use-motion';
import { themeToCssVars } from '@/hooks/use-universe';
import type { UniversePreview, UniverseTheme } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowDown, ArrowUpRight } from 'lucide-react';
import { useCallback, useEffect, useMemo } from 'react';

interface Layer extends UniversePreview {
    altitude: string;
    measure: string;
    line: string;
    theme?: UniverseTheme;
}

/**
 * A single continuous zoom through six universes.
 *
 * Mechanism: the page is `layers × 100vh` tall purely to create scroll
 * distance. A sticky stage stays fixed while that distance is consumed, and
 * scroll progress is mapped to a `depth` value in [0, layers]. Each layer
 * renders at `scale = 2^(depth - index)`, so it grows through the viewport and
 * is replaced by the next — the classic infinite-zoom illusion, driven entirely
 * by one scalar.
 *
 * Accessibility is not an afterthought here: under prefers-reduced-motion the
 * whole mechanism is replaced by a plain vertical article carrying identical
 * text. A zoom nobody can look at without feeling sick must still be readable.
 */
export default function DeepField({ layers }: { layers: Layer[] }) {
    const reduced = useMemo(() => prefersReducedMotion(), []);

    return (
        <>
            <Head title="The Deep Field" />
            {reduced ? <StaticDescent layers={layers} /> : <ZoomDescent layers={layers} />}
        </>
    );
}

/* ------------------------------------------------------------------ *
 * The zoom
 * ------------------------------------------------------------------ */

function ZoomDescent({ layers }: { layers: Layer[] }) {
    // Driven by a rAF loop rather than scroll events — see useScrollProgress.
    const [scrollRef, progress] = useScrollProgress<HTMLDivElement>();
    const depth = progress * layers.length;

    // Which layer is "current" — drives the chrome colours and the readout.
    const activeIndex = Math.min(layers.length - 1, Math.max(0, Math.round(depth - 0.5)));
    const active = layers[activeIndex] ?? layers[0];

    const jumpTo = useCallback(
        (index: number) => {
            const node = scrollRef.current;
            if (!node) return;
            const total = node.scrollHeight - window.innerHeight;
            const target = node.offsetTop + (total * (index + 0.5)) / layers.length;
            window.scrollTo({ top: target, behavior: 'smooth' });
        },
        // scrollRef is a stable ref object, but listing it keeps the dependency
        // check honest rather than silenced.
        [layers.length, scrollRef],
    );

    // Keyboard control: the descent must be operable without a scroll wheel.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowDown' || event.key === 'PageDown') {
                event.preventDefault();
                jumpTo(Math.min(layers.length - 1, activeIndex + 1));
            } else if (event.key === 'ArrowUp' || event.key === 'PageUp') {
                event.preventDefault();
                jumpTo(Math.max(0, activeIndex - 1));
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [activeIndex, jumpTo, layers.length]);

    return (
        <div
            className="u-root relative"
            data-universe={active?.slug}
            style={{ ...themeToCssVars(active?.theme), backgroundColor: active?.swatch.bg }}
        >
            <div ref={scrollRef} style={{ height: `${layers.length * 100}vh` }}>
                <div className="sticky top-0 h-screen overflow-hidden">
                    {layers.map((layer, index) => {
                        // Distance of this layer from the current depth.
                        const local = depth - index;

                        // Cull anything outside the visible band — at six layers
                        // this is cheap, but it keeps the compositor honest.
                        if (local < -1.15 || local > 1.15) return null;

                        // Exponential growth is what sells a continuous zoom:
                        // a linear scale reads as six separate slides.
                        const scale = Math.pow(2, local);
                        const opacity = local < 0 ? Math.max(0, 1 + local / 1.1) : Math.max(0, 1 - local / 0.95);

                        return (
                            <figure
                                key={layer.slug}
                                aria-hidden={index !== activeIndex}
                                className="absolute inset-0 m-0 will-change-transform"
                                style={{ transform: `scale(${scale})`, opacity, zIndex: layers.length - index }}
                            >
                                <img src={layer.hero_image} alt="" className="size-full object-cover" decoding="async" />
                                <div
                                    className="absolute inset-0"
                                    style={{
                                        background: `radial-gradient(ellipse at center, transparent 42%, ${layer.swatch.bg}99 82%, ${layer.swatch.bg} 100%)`,
                                    }}
                                />
                            </figure>
                        );
                    })}

                    {/*
                      Legibility scrim, sized to the viewport rather than to the
                      caption. These photographs have bright regions (a galaxy
                      core, sunlit sand) that text-shadow alone cannot survive.
                      A box behind the text would show its own rectangular edge;
                      a full-viewport radial cannot, because its falloff runs off
                      every side. It also sits outside the scaled figures, so it
                      does not zoom with them.
                    */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 z-40"
                        style={{
                            background: 'radial-gradient(ellipse 58% 46% at 50% 48%, rgb(0 0 0 / 0.74), rgb(0 0 0 / 0.34) 62%, transparent 88%)',
                        }}
                    />

                    {/* Caption for the current depth, above every layer. */}
                    <div className="pointer-events-none absolute inset-0 z-50 flex items-center justify-center px-6">
                        <Caption layer={active} depth={depth} index={activeIndex} />
                    </div>

                    <DepthRail layers={layers} activeIndex={activeIndex} onJump={jumpTo} />

                    {depth < 0.35 && (
                        <div className="pointer-events-none absolute inset-x-0 bottom-8 z-50 flex flex-col items-center gap-2 text-white/70">
                            <span className="text-[11px] font-semibold tracking-[0.2em] uppercase">Scroll to descend</span>
                            <ArrowDown className="size-4 animate-bounce" />
                        </div>
                    )}
                </div>
            </div>

            <Arrival />
        </div>
    );
}

/** The text for one depth, cross-fading as you pass through. */
function Caption({ layer, depth, index }: { layer: Layer; depth: number; index: number }) {
    /*
     * Hold the caption at full strength through most of a layer, then fade it
     * out as the next one arrives, so text is never competing with a photograph
     * that is mid-transition.
     *
     * `local < 0` is clamped to full opacity deliberately: it is the state at
     * rest on the very first layer, and a symmetric fade would leave the
     * opening caption invisible until the reader had already scrolled.
     */
    const local = depth - index;
    const opacity = local < 0 ? 1 : Math.max(0, 1 - Math.pow(Math.max(0, local - 0.55) / 0.45, 2));

    return (
        <div className="relative max-w-2xl text-center transition-opacity duration-200" style={{ opacity }}>
            <div className="relative" style={{ textShadow: '0 2px 24px rgb(0 0 0 / 0.85), 0 1px 3px rgb(0 0 0 / 0.7)' }}>
                <p className="mb-3 text-[11px] font-semibold tracking-[0.24em] text-white/70 uppercase">{layer.measure}</p>
                <h2 className="font-display text-4xl leading-tight text-white sm:text-6xl">{layer.altitude}</h2>
                <p className="mx-auto mt-6 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">{layer.line}</p>

                <Link
                    href={`/universes/${layer.slug}`}
                    className="pointer-events-auto mt-8 inline-flex items-center gap-2 rounded-full border border-white/30 bg-black/35 px-5 py-2.5 text-sm text-white backdrop-blur-sm transition-colors hover:bg-black/55"
                >
                    Read what people write here
                    <ArrowUpRight className="size-4" />
                </Link>
            </div>
        </div>
    );
}

/** Depth gauge down the right edge; also the navigation. */
function DepthRail({ layers, activeIndex, onJump }: { layers: Layer[]; activeIndex: number; onJump: (index: number) => void }) {
    return (
        <nav aria-label="Depth" className="absolute top-1/2 right-4 z-50 hidden -translate-y-1/2 flex-col gap-1 sm:flex">
            {layers.map((layer, index) => {
                const current = index === activeIndex;

                return (
                    <button
                        key={layer.slug}
                        type="button"
                        onClick={() => onJump(index)}
                        aria-label={`${layer.altitude} — ${layer.name}`}
                        aria-current={current}
                        className="group flex items-center gap-2.5 rounded-full py-1.5 pr-1 pl-3 transition-colors"
                    >
                        <span
                            className="text-[10px] font-semibold tracking-[0.14em] whitespace-nowrap text-white uppercase transition-opacity"
                            style={{ opacity: current ? 0.95 : 0, textShadow: '0 1px 6px rgb(0 0 0 / 0.8)' }}
                        >
                            {layer.name}
                        </span>
                        <span
                            aria-hidden
                            className="block rounded-full transition-all duration-300"
                            style={{
                                width: current ? 11 : 6,
                                height: current ? 11 : 6,
                                backgroundColor: current ? layer.swatch.accent : 'rgb(255 255 255 / 0.45)',
                                boxShadow: current ? `0 0 12px ${layer.swatch.accent}` : 'none',
                            }}
                        />
                    </button>
                );
            })}
        </nav>
    );
}

/** What sits at the bottom of the descent. */
function Arrival() {
    return (
        <section className="relative z-10 px-6 py-24 text-center" style={{ backgroundColor: 'var(--u-bg)' }}>
            <h2 className="font-display mx-auto max-w-2xl text-4xl leading-tight sm:text-5xl" style={{ color: 'var(--u-text)' }}>
                Six worlds, and you picked one to stop at.
            </h2>
            <p className="mx-auto mt-6 max-w-xl text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                That choice is the whole idea. A writer picks a scale to work at, and everything about how the page feels follows from it. Find the
                one that sounds like you.
            </p>
            <div className="mt-10 flex flex-wrap justify-center gap-3">
                <Link href="/universes" className="u-btn u-btn-primary px-6 py-3 text-base">
                    Choose a universe
                </Link>
                <Link href="/posts" className="u-btn u-btn-ghost px-6 py-3 text-base">
                    Read something first
                </Link>
            </div>
        </section>
    );
}

/* ------------------------------------------------------------------ *
 * Reduced-motion equivalent — same words, no movement.
 * ------------------------------------------------------------------ */

function StaticDescent({ layers }: { layers: Layer[] }) {
    return (
        <div className="u-root" style={{ backgroundColor: layers[0]?.swatch.bg }}>
            <header className="mx-auto max-w-2xl px-6 pt-20 pb-12 text-center">
                <h1 className="font-display text-5xl text-white">The Deep Field</h1>
                <p className="mt-5 text-base text-white/75">
                    A descent from the space between stars to the bottom of the sea, through all six universes.
                </p>
            </header>

            <div className="mx-auto max-w-2xl px-6 pb-24">
                {layers.map((layer) => (
                    <article key={layer.slug} className="border-t border-white/12 py-12 first:border-t-0">
                        <div className="mb-5 flex items-center gap-3">
                            <Swatch swatch={layer.swatch} size={30} />
                            <span className="text-[11px] font-semibold tracking-[0.2em] text-white/60 uppercase">{layer.measure}</span>
                        </div>

                        <img src={layer.hero_image} alt="" className="mb-6 h-56 w-full rounded-[14px] object-cover" loading="lazy" />

                        <h2 className="font-display text-3xl text-white">{layer.altitude}</h2>
                        <p className="mt-4 text-base leading-relaxed text-white/80">{layer.line}</p>

                        <Link
                            href={`/universes/${layer.slug}`}
                            className="mt-5 inline-flex items-center gap-2 text-sm text-white/90 underline underline-offset-4"
                        >
                            Read what people write in {layer.name}
                            <ArrowUpRight className="size-4" />
                        </Link>
                    </article>
                ))}
            </div>

            <Arrival />
        </div>
    );
}
