import { Swatch } from '@/components/metal';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, Plus } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Switches which persona the writer is currently working as. Changing persona
 * changes the universe, and therefore the entire theme — that is the point.
 */
export function PersonaSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onPointerDown = (event: MouseEvent) => {
            if (ref.current && !ref.current.contains(event.target as Node)) setOpen(false);
        };
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') setOpen(false);
        };

        document.addEventListener('mousedown', onPointerDown);
        document.addEventListener('keydown', onKeyDown);

        return () => {
            document.removeEventListener('mousedown', onPointerDown);
            document.removeEventListener('keydown', onKeyDown);
        };
    }, [open]);

    if (!auth.user) return null;

    const active = auth.personas.find((persona) => persona.id === auth.activePersonaId) ?? auth.personas[0];

    if (!active) {
        return (
            <Link href="/personas" className="u-btn u-btn-ghost">
                <Plus className="size-4" />
                Create a persona
            </Link>
        );
    }

    return (
        <div className="relative" ref={ref}>
            <button type="button" onClick={() => setOpen((value) => !value)} aria-haspopup="menu" aria-expanded={open} className="u-btn u-btn-ghost">
                <Swatch swatch={active.universe.swatch} size={18} />
                <span className="hidden sm:inline">@{active.handle}</span>
                <ChevronDown className="size-3.5 opacity-60" />
            </button>

            {open && (
                <div
                    role="menu"
                    className="metal metal-edge absolute right-0 z-50 mt-2 w-72 overflow-hidden p-1.5"
                    style={{ boxShadow: '0 24px 60px -20px var(--u-metal-shadow)' }}
                >
                    <p className="px-3 pt-2 pb-1.5 text-[10px] font-semibold tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                        Writing as
                    </p>

                    {auth.personas.map((persona) => (
                        <button
                            key={persona.id}
                            role="menuitem"
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                if (persona.id !== active.id) {
                                    router.post(`/personas/${persona.handle}/switch`, {}, { preserveScroll: true });
                                }
                            }}
                            className="flex w-full items-center gap-3 rounded-[9px] px-3 py-2.5 text-left transition-colors"
                            style={{ color: 'var(--u-text)' }}
                            onMouseEnter={(event) => (event.currentTarget.style.backgroundColor = 'var(--u-surface-2)')}
                            onMouseLeave={(event) => (event.currentTarget.style.backgroundColor = 'transparent')}
                        >
                            <Swatch swatch={persona.universe.swatch} size={30} />
                            <span className="min-w-0 flex-1">
                                <span className="block truncate text-sm font-medium">{persona.display_name}</span>
                                <span className="block truncate text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    @{persona.handle} · {persona.universe.name}
                                </span>
                            </span>
                            {persona.id === active.id && <Check className="size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />}
                        </button>
                    ))}

                    <div className="my-1.5 h-px" style={{ backgroundColor: 'var(--u-border)' }} />

                    <Link
                        href="/personas"
                        onClick={() => setOpen(false)}
                        className="flex items-center gap-2 rounded-[9px] px-3 py-2.5 text-sm"
                        style={{ color: 'var(--u-text-muted)' }}
                    >
                        <Plus className="size-4" />
                        Manage personas
                    </Link>
                </div>
            )}
        </div>
    );
}
