import { useMagnetic } from '@/hooks/use-motion';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { Loader2 } from 'lucide-react';
import type { ComponentPropsWithoutRef, ReactNode } from 'react';

type Variant = 'primary' | 'ghost' | 'quiet';
type Size = 'sm' | 'md' | 'lg';

const VARIANT: Record<Variant, string> = {
    primary: 'u-btn u-btn-primary',
    ghost: 'u-btn u-btn-ghost',
    quiet: 'u-btn u-btn-quiet',
};

const SIZE: Record<Size, string> = {
    sm: 'u-btn-sm',
    md: '',
    lg: 'u-btn-lg',
};

interface Common {
    variant?: Variant;
    size?: Size;
    /** Pointer-following lean, in pixels. 0 turns it off. */
    magnetic?: number;
    loading?: boolean;
    icon?: ReactNode;
    children?: ReactNode;
    className?: string;
}

/**
 * The platform's button.
 *
 * The motion lives in CSS (`.u-btn`), not here — this component only supplies
 * the magnetic offsets and keeps variants in one place. That split matters
 * because plenty of buttons in the app are plain `<a>` or `<Link>` elements
 * carrying the same classes, and they should look and behave identically
 * without importing anything.
 *
 * `loading` swaps the icon rather than the label: a button whose text is
 * replaced by a spinner loses its meaning at the exact moment the user is
 * waiting to be reassured about what they clicked.
 */
export function Action({
    variant = 'primary',
    size = 'md',
    magnetic = 4,
    loading = false,
    icon,
    children,
    className,
    ...props
}: Common & Omit<ComponentPropsWithoutRef<'button'>, 'size'>) {
    const ref = useMagnetic<HTMLButtonElement>(magnetic);

    return (
        <button ref={ref} className={cn(VARIANT[variant], SIZE[size], className)} {...props}>
            {loading ? <Loader2 className="size-4 animate-spin" /> : icon}
            {children}
        </button>
    );
}

/** The same surface as an Inertia link. */
export function ActionLink({
    variant = 'primary',
    size = 'md',
    magnetic = 4,
    icon,
    children,
    className,
    href,
    ...props
}: Common & { href: string } & Omit<ComponentPropsWithoutRef<typeof Link>, 'href' | 'className' | 'size'>) {
    const ref = useMagnetic<HTMLAnchorElement>(magnetic);

    return (
        <Link ref={ref} href={href} className={cn(VARIANT[variant], SIZE[size], className)} {...props}>
            {icon}
            {children}
        </Link>
    );
}
