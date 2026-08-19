import { Flash } from '@/components/flash';
import { Logo } from '@/components/logo';
import { PersonaSwitcher } from '@/components/persona-switcher';
import { UniverseRoot } from '@/components/universe-root';
import { useUniverse } from '@/hooks/use-universe';
import { cn } from '@/lib/utils';
import type { SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Bell, Menu, PenLine, X } from 'lucide-react';
import { useState, type ReactNode } from 'react';

const NAV = [
    { label: 'Read', href: '/posts' },
    { label: 'Universes', href: '/universes' },
    { label: 'Courses', href: '/courses' },
    { label: 'Subjects', href: '/categories' },
    { label: 'Circles', href: '/circles' },
    { label: 'Deep Field', href: '/deep-field' },
];

export default function SiteLayout({ children, wide = false }: { children: ReactNode; wide?: boolean }) {
    const page = usePage<SharedData>();
    const { auth, unreadAlerts = 0, universeIndex = [] } = page.props;
    const universe = useUniverse();
    const [menuOpen, setMenuOpen] = useState(false);

    /*
     * The current path comes from Inertia, not from window.
     *
     * Reading window here behind a `typeof` guard would still be wrong under
     * SSR — the server would render one nav item highlighted and the browser
     * another, which is a hydration mismatch rather than a crash, and those are
     * the ones nobody notices.
     */
    const current = page.url.split('?')[0];

    return (
        <UniverseRoot className="flex min-h-screen flex-col">
            {/* Atmosphere sits behind everything and never behind body text. */}
            <div className="pointer-events-none fixed inset-0 overflow-hidden" aria-hidden>
                <div className="u-halo u-drift absolute inset-x-0 top-0 h-[70vh]" />
            </div>

            <header className="frost sticky top-0 z-50">
                <div className="mx-auto flex h-16 max-w-7xl items-center gap-3 px-5 sm:px-8">
                    <Link href="/" aria-label="Inkfathom home">
                        <Logo size={26} />
                    </Link>

                    <nav className="ml-6 hidden items-center gap-1 md:flex">
                        {(auth.user ? [NAV[0], { label: 'Following', href: '/following' }, ...NAV.slice(1)] : NAV).map((item) => (
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
                                <Link
                                    href="/activity"
                                    className="u-btn u-btn-ghost relative"
                                    aria-label={unreadAlerts > 0 ? `Activity, ${unreadAlerts} unread` : 'Activity'}
                                >
                                    <Bell className="size-4" />
                                    {unreadAlerts > 0 && (
                                        <span
                                            className="absolute -top-1 -right-1 flex min-w-[18px] items-center justify-center rounded-full px-1 text-[10px] leading-[18px] font-semibold tabular-nums"
                                            style={{ backgroundColor: 'var(--u-accent)', color: 'var(--u-accent-fg)' }}
                                        >
                                            {unreadAlerts > 99 ? '99+' : unreadAlerts}
                                        </span>
                                    )}
                                </Link>
                                <Link href="/write" className="u-btn u-btn-primary">
                                    <PenLine className="size-4" />
                                    Write
                                </Link>
                                <Link href="/library" className="u-btn u-btn-ghost" aria-label="Your library">
                                    Library
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
                                        <Link href="/following" className="u-btn u-btn-ghost">
                                            Following
                                        </Link>
                                        <Link href="/activity" className="u-btn u-btn-ghost">
                                            Activity{unreadAlerts > 0 && ` (${unreadAlerts})`}
                                        </Link>
                                        <Link href="/personas" className="u-btn u-btn-ghost">
                                            Personas
                                        </Link>
                                        <Link href="/earnings" className="u-btn u-btn-ghost">
                                            Earnings
                                        </Link>
                                        <Link href="/letters" className="u-btn u-btn-ghost">
                                            Letters
                                        </Link>
                                        <Link href="/settings/themes" className="u-btn u-btn-ghost">
                                            Themes
                                        </Link>
                                        <Link href="/settings/reading" className="u-btn u-btn-ghost">
                                            Reading settings
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
                        Inkfathom — a writing platform with {universeIndex.length} worlds.
                        {universe && <span className="ml-2 opacity-70">Currently in {universe.name}.</span>}
                    </p>
                    <div className="flex flex-wrap gap-5">
                        <Link href="/universes">Universes</Link>
                        <Link href="/courses">Courses</Link>
                        <Link href="/categories">Subjects</Link>
                        <Link href="/circles">Circles</Link>
                        <Link href="/deep-field">Deep Field</Link>
                        <Link href="/upgrade">Pricing</Link>
                        <a href="/feed.xml">RSS</a>
                    </div>
                </div>
            </footer>

            <Flash />
        </UniverseRoot>
    );
}
