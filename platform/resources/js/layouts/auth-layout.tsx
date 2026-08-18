import { LogoMark } from '@/components/logo';
import { UniverseRoot } from '@/components/universe-root';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

/**
 * Auth screens, themed like the rest of the platform.
 *
 * The split is deliberate: the left panel argues for the product while the
 * right panel gets out of the way. Sign-up is the one screen where a reader
 * has not yet seen what they are joining.
 */
export default function AuthLayout({ children, title, description }: { children: ReactNode; title: string; description: string }) {
    return (
        <UniverseRoot className="min-h-screen">
            <div className="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">
                {/* Pitch */}
                <aside className="relative hidden overflow-hidden lg:flex lg:flex-col lg:justify-between lg:p-12">
                    <img src="/images/universes/cosmos-hero.jpg" alt="" className="absolute inset-0 -z-10 size-full object-cover opacity-55" />
                    <div
                        aria-hidden
                        className="absolute inset-0 -z-10"
                        style={{
                            background:
                                'linear-gradient(115deg, color-mix(in srgb, var(--u-bg) 88%, transparent), color-mix(in srgb, var(--u-bg) 55%, transparent))',
                        }}
                    />

                    <Link href="/" className="relative inline-flex items-center gap-2.5" aria-label="Inkfathom home">
                        <LogoMark size={30} />
                        <span className="font-display text-2xl tracking-tight">Inkfathom</span>
                    </Link>

                    <div className="relative max-w-md">
                        <p className="font-display text-4xl leading-tight">Every writer contains more than one person.</p>
                        <p className="mt-5 text-base leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            Six worlds to write in. Readers who mark the exact sentence that landed. And a record of which line did the work.
                        </p>
                    </div>

                    <p className="relative text-xs" style={{ color: 'var(--u-text-muted)' }}>
                        Reading is free, and always will be.
                    </p>
                </aside>

                {/* Form */}
                <main className="flex items-center justify-center px-6 py-12 sm:px-10">
                    <div className="w-full max-w-sm">
                        <Link href="/" className="mb-9 inline-flex items-center gap-2.5 lg:hidden" aria-label="Inkfathom home">
                            <LogoMark size={26} />
                            <span className="font-display text-xl tracking-tight">Inkfathom</span>
                        </Link>

                        <h1 className="font-display text-3xl sm:text-4xl">{title}</h1>
                        <p className="mt-2.5 text-sm leading-relaxed" style={{ color: 'var(--u-text-muted)' }}>
                            {description}
                        </p>

                        <div className="mt-8">{children}</div>
                    </div>
                </main>
            </div>
        </UniverseRoot>
    );
}
