import { Chip, Panel, Swatch } from '@/components/metal';
import { usePointerSpecular } from '@/hooks/use-motion';
import type { UniversePreview } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowUpRight, Lock } from 'lucide-react';

/**
 * Renders a universe from its photograph and preview swatch only — never the
 * full token set. A locked world still shows a faithful, enticing card without
 * the paid palette ever reaching the browser.
 */
export function UniverseCard({ universe }: { universe: UniversePreview }) {
    const specularRef = usePointerSpecular<HTMLDivElement>();

    return (
        <Panel interactive grain={false} className="specular-pointer group" ref={specularRef}>
            <Link href={`/universes/${universe.slug}`} className="block">
                <div className="relative h-52 overflow-hidden sm:h-60">
                    <img src={universe.hero_image} alt="" loading="lazy" decoding="async" className="img-zoom size-full object-cover" />

                    {/* Tint the photograph toward the world's own accent, so the
                        card reads as that universe even before you enter it. */}
                    <div
                        aria-hidden
                        className="absolute inset-0"
                        style={{
                            background: `linear-gradient(to top, ${universe.swatch.bg} 4%, ${universe.swatch.bg}b0 34%, ${universe.swatch.accent}22 100%)`,
                        }}
                    />

                    <div className="absolute inset-x-4 bottom-3 flex items-end justify-between gap-3">
                        <div className="min-w-0">
                            <h3 className="font-display truncate text-3xl text-white drop-shadow-[0_2px_10px_rgba(0,0,0,0.65)]">{universe.name}</h3>
                            <p className="truncate text-xs text-white/85 italic drop-shadow-[0_1px_6px_rgba(0,0,0,0.7)]">{universe.tagline}</p>
                        </div>
                        <Swatch swatch={universe.swatch} size={34} />
                    </div>

                    {universe.locked && (
                        <span className="absolute top-3 right-3">
                            <Chip tone="accent">
                                <Lock className="size-3" />
                                Premium
                            </Chip>
                        </span>
                    )}
                </div>

                <div className="p-5 sm:p-6">
                    <p className="line-clamp-2 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                        {universe.description}
                    </p>

                    <div
                        className="mt-5 flex min-w-0 items-center gap-3 text-[11px] tracking-wide uppercase"
                        style={{ color: 'var(--u-text-muted)' }}
                    >
                        <span className="min-w-0 truncate">{universe.material}</span>
                        {typeof universe.posts_count === 'number' && (
                            <>
                                <span aria-hidden>·</span>
                                <span className="whitespace-nowrap">
                                    {universe.posts_count} {universe.posts_count === 1 ? 'piece' : 'pieces'}
                                </span>
                            </>
                        )}
                        <ArrowUpRight
                            className="ml-auto size-4 shrink-0 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                            style={{ color: 'var(--u-accent)' }}
                        />
                    </div>
                </div>
            </Link>
        </Panel>
    );
}
