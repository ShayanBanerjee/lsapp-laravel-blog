import { cn } from '@/lib/utils';
import { Check, Link2, Share2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/**
 * Sharing, built on the fact that text is quotable.
 *
 * When the reader has a passage selected we share *that sentence* plus the
 * link, because a quote travels better than a headline — which is the whole
 * argument for text over video.
 */
export function ShareMenu({ url, title, quote, className }: { url: string; title: string; quote?: string | null; className?: string }) {
    const [open, setOpen] = useState(false);
    const [copied, setCopied] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) return;

        const onDown = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && setOpen(false);

        document.addEventListener('mousedown', onDown);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('mousedown', onDown);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const text = quote ? `“${quote}”` : title;
    const enc = { url: encodeURIComponent(url), text: encodeURIComponent(text), title: encodeURIComponent(title) };

    const targets = [
        { label: 'X', href: `https://twitter.com/intent/tweet?text=${enc.text}&url=${enc.url}` },
        { label: 'LinkedIn', href: `https://www.linkedin.com/sharing/share-offsite/?url=${enc.url}` },
        { label: 'Facebook', href: `https://www.facebook.com/sharer/sharer.php?u=${enc.url}` },
        { label: 'Reddit', href: `https://www.reddit.com/submit?url=${enc.url}&title=${enc.title}` },
        { label: 'WhatsApp', href: `https://api.whatsapp.com/send?text=${enc.text}%20${enc.url}` },
        { label: 'Telegram', href: `https://t.me/share/url?url=${enc.url}&text=${enc.text}` },
        { label: 'Bluesky', href: `https://bsky.app/intent/compose?text=${enc.text}%20${enc.url}` },
        { label: 'Email', href: `mailto:?subject=${enc.title}&body=${enc.text}%0A%0A${enc.url}` },
    ];

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(quote ? `${text}\n\n${url}` : url);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        } catch {
            // Clipboard is permission-gated; fall back to the share sheet.
            setOpen(true);
        }
    };

    // Prefer the OS share sheet on devices that have one.
    const native = async () => {
        if (typeof navigator !== 'undefined' && navigator.share) {
            try {
                await navigator.share({ title, text, url });

                return true;
            } catch {
                return true; // user cancelled — not an error
            }
        }

        return false;
    };

    return (
        <div className={cn('relative', className)} ref={ref}>
            <button
                type="button"
                className="u-btn u-btn-ghost"
                aria-haspopup="menu"
                aria-expanded={open}
                onClick={async () => {
                    if (await native()) return;
                    setOpen((v) => !v);
                }}
            >
                <Share2 className="size-3.5" />
                Share
            </button>

            {open && (
                <div role="menu" className="metal metal-edge absolute right-0 z-50 mt-2 w-60 overflow-hidden p-1.5">
                    {quote && (
                        <p className="px-3 pt-2 pb-2 text-xs italic" style={{ color: 'var(--u-text-muted)' }}>
                            Sharing your selected passage
                        </p>
                    )}
                    <button
                        type="button"
                        onClick={copy}
                        className="flex w-full items-center gap-2.5 rounded-[9px] px-3 py-2.5 text-left text-sm"
                        style={{ color: 'var(--u-text)' }}
                    >
                        {copied ? <Check className="size-4" style={{ color: 'var(--u-accent)' }} /> : <Link2 className="size-4" />}
                        {copied ? 'Copied' : 'Copy link'}
                    </button>
                    <div className="my-1.5 h-px" style={{ backgroundColor: 'var(--u-border)' }} />
                    {targets.map((t) => (
                        <a
                            key={t.label}
                            href={t.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="block rounded-[9px] px-3 py-2.5 text-sm"
                            style={{ color: 'var(--u-text-muted)' }}
                        >
                            {t.label}
                        </a>
                    ))}
                </div>
            )}
        </div>
    );
}
