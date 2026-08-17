import { Flash } from '@/components/flash';
import { PersonaSwitcher } from '@/components/persona-switcher';
import { UniverseRoot } from '@/components/universe-root';
import { useUniverse } from '@/hooks/use-universe';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Menu, PenLine, Sparkles, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';

const NAV = [
    { label: 'Read', href: '/posts' },
    { label: 'Universes', href: '/universes' },
];

export default function SiteLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const { auth, url } = usePage<SharedData>().props as SharedData & { url?: string };
    const universe = useUniverse();
    const [menuOpen, setMenuOpen] = useState(false);
    const current = typeof window !== 'undefined' ? window.location.pathname : (url ?? '');

    return (
        <UniverseRoot className="flex min-h-screen flex-col">
            {/* Atmosphere sits behind everything and never behind body text. */}
            <div className="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden>
                <div className="u-halo u-drift absolute inset-x-0 top-0 h-[70vh]" />
            </div>

            <header className="frost sticky top-0 z-50">
                <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-5 sm:px-8">
                    <Link href="/" className="font-display flex items-center gap-2.5 text-xl tracking-tight">
                        <Sparkles className="size-[18px]" style={{ color: 'var(--u-accent)' }} />
                        Aetheris
                    </Link>

                    <nav className="ml-6 hidden items-center gap-1 md:flex">
                        {NAV.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className="rounded-[9px] px-3 py-2 text-sm transition-colors"
                                style={{
                                    color: current.startsWith(item.href) ? 'var(--u-text)' : 'var(--u-text-muted)',
                                    backgroundColor: current.startsWith(item.href) ? 'var(--u-surface-2)' : 'transparent',
                                }}
                            >
                                {item.label}
                            </Link>
                        ))}
                        {!auth.user?.is_premium && (
                            <Link href="/upgrade" className="rounded-[9px] px-3 py-2 text-sm" style={{ color: 'var(--u-accent)' }}>
                                Upgrade
                            </Link>
                        )}
                    </nav>

                    <div className="ml-auto hidden items-center gap-2 md:flex">
                        {auth.user ? (
                            <>
                                <PersonaSwitcher />
                                <Link href="/write" className="u-btn u-btn-primary">
                                    <PenLine className="size-4" />
                                    Write
                                </Link>
                                <Link href="/dashboard" className="u-btn u-btn-ghost">
                                    Desk
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/login" className="u-btn u-btn-ghost">
                                    Log in
                                </Link>
                                <Link href="/register" className="u-btn u-btn-primary">
                                    Start writing
                                </Link>
                            </>
                        )}
                    </div>

                    <button
                        type="button"
                        className="u-btn u-btn-ghost ml-auto md:hidden"
                        aria-label="Toggle menu"
                        aria-expanded={menuOpen}
                        onClick={() => setMenuOpen((value) => !value)}
                    >
                        {menuOpen ? <X className="size-4" /> : <Menu className="size-4" />}
                    </button>
                </div>

                {menuOpen && (
                    <div className="border-t px-5 py-4 md:hidden" style={{ borderColor: 'var(--u-border)' }}>
                        <div className="flex flex-col gap-1">
                            {NAV.map((item) => (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className="rounded-[9px] px-3 py-2.5 text-sm"
                                    onClick={() => setMenuOpen(false)}
                                >
                                    {item.label}
                                </Link>
                            ))}
                            <Link href="/upgrade" className="rounded-[9px] px-3 py-2.5 text-sm" style={{ color: 'var(--u-accent)' }}>
                                Upgrade
                            </Link>
                            <div className="mt-3 flex flex-col gap-2">
                                {auth.user ? (
                                    <>
                                        <Link href="/write" className="u-btn u-btn-primary">
                                            <PenLine className="size-4" /> Write
                                        </Link>
                                        <Link href="/dashboard" className="u-btn u-btn-ghost">
                                            Desk
                                        </Link>
                                        <Link href="/personas" className="u-btn u-btn-ghost">
                                            Personas
                                        </Link>
                                    </>
                                ) : (
                                    <>
                                        <Link href="/login" className="u-btn u-btn-ghost">
                                            Log in
                                        </Link>
                                        <Link href="/register" className="u-btn u-btn-primary">
                                            Start writing
                                        </Link>
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </header>

            <main className={cn('relative z-10 mx-auto w-full flex-1 px-5 py-10 sm:px-8 sm:py-14', wide ? 'max-w-7xl' : 'max-w-6xl')}>
                {children}
            </main>

            <footer className="relative z-10 mt-8 border-t" style={{ borderColor: 'var(--u-border)' }}>
                <div
                    className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm sm:px-8"
                    style={{ color: 'var(--u-text-muted)' }}
                >
                    <p>
                        Aetheris — a writing platform with six worlds.
                        {universe && <span className="ml-2 opacity-70">Currently in {universe.name}.</span>}
                    </p>
                    <div className="flex gap-5">
                        <Link href="/universes">Universes</Link>
                        <Link href="/upgrade">Pricing</Link>
                    </div>
                </div>
            </footer>

            <Flash />
        </UniverseRoot>
    );
}
