import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, X } from 'lucide-react';
import { useEffect, useState } from 'react';

export function Flash() {
    const { flash } = usePage<SharedData>().props;
    const message = flash?.success ?? flash?.error ?? null;
    const isError = Boolean(flash?.error);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!message) return;

        setVisible(true);
        const timer = window.setTimeout(() => setVisible(false), 5000);

        return () => window.clearTimeout(timer);
    }, [message]);

    if (!message || !visible) return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 bottom-6 z-[60] flex justify-center px-4" role="status" aria-live="polite">
            <div className="metal metal-edge u-rise pointer-events-auto flex max-w-md items-start gap-3 px-4 py-3">
                {isError ? (
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" style={{ color: '#f0a04b' }} />
                ) : (
                    <CheckCircle2 className="mt-0.5 size-4 shrink-0" style={{ color: 'var(--u-accent)' }} />
                )}
                <p className="text-sm leading-snug">{message}</p>
                <button type="button" onClick={() => setVisible(false)} aria-label="Dismiss" className="ml-1 opacity-50 hover:opacity-100">
                    <X className="size-4" />
                </button>
            </div>
        </div>
    );
}
