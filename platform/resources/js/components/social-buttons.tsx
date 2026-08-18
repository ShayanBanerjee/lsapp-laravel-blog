import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * Social sign-in buttons.
 *
 * Rendered only for providers the server reports as configured, so a missing
 * credential means no button rather than a button that fails.
 */
const BRAND: Record<string, { label: string; icon: React.ReactNode }> = {
    google: {
        label: 'Google',
        icon: (
            <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
                <path
                    fill="#4285F4"
                    d="M23.06 12.25c0-.85-.08-1.67-.22-2.45H12v4.63h6.2a5.3 5.3 0 0 1-2.3 3.48v2.89h3.72c2.18-2 3.44-4.96 3.44-8.55Z"
                />
                <path
                    fill="#34A853"
                    d="M12 24c3.11 0 5.72-1.03 7.62-2.79l-3.72-2.89c-1.03.69-2.35 1.1-3.9 1.1-3 0-5.55-2.03-6.46-4.76H1.7v2.98A11.5 11.5 0 0 0 12 24Z"
                />
                <path fill="#FBBC05" d="M5.54 14.66a6.9 6.9 0 0 1 0-4.4V7.28H1.7a11.5 11.5 0 0 0 0 10.36l3.84-2.98Z" />
                <path
                    fill="#EA4335"
                    d="M12 4.75c1.69 0 3.21.58 4.4 1.72l3.3-3.3C17.71 1.24 15.1 0 12 0 7.5 0 3.6 2.56 1.7 6.28l3.84 2.98C6.45 6.78 9 4.75 12 4.75Z"
                />
            </svg>
        ),
    },
    facebook: {
        label: 'Facebook',
        icon: (
            <svg viewBox="0 0 24 24" className="size-4" aria-hidden>
                <path
                    fill="#1877F2"
                    d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c-3.01 0-4.87 1.83-4.87 5.15v2.02H4.5v3.49h2.76V24A12.02 12.02 0 0 0 24 12.07Z"
                />
            </svg>
        ),
    },
    github: {
        label: 'GitHub',
        icon: (
            <svg viewBox="0 0 24 24" className="size-4 fill-current" aria-hidden>
                <path d="M12 0C5.37 0 0 5.37 0 12a12 12 0 0 0 8.21 11.39c.6.11.82-.26.82-.58v-2.03c-3.34.73-4.04-1.61-4.04-1.61-.55-1.39-1.34-1.76-1.34-1.76-1.09-.75.08-.73.08-.73 1.2.08 1.84 1.24 1.84 1.24 1.07 1.83 2.81 1.3 3.5 1 .11-.78.42-1.3.76-1.6-2.67-.3-5.47-1.33-5.47-5.93 0-1.31.47-2.38 1.24-3.22-.13-.3-.54-1.52.12-3.18 0 0 1.01-.32 3.3 1.23a11.5 11.5 0 0 1 6.01 0c2.29-1.55 3.3-1.23 3.3-1.23.66 1.66.25 2.88.12 3.18.77.84 1.24 1.91 1.24 3.22 0 4.61-2.8 5.62-5.48 5.92.43.37.81 1.1.81 2.22v3.29c0 .32.22.7.83.58A12 12 0 0 0 24 12c0-6.63-5.37-12-12-12Z" />
            </svg>
        ),
    },
};

export function SocialButtons({ action = 'Continue' }: { action?: string }) {
    const { socialProviders } = usePage<SharedData>().props;

    if (!socialProviders || socialProviders.length === 0) return null;

    return (
        <div className="mt-6">
            <div className="flex items-center gap-3">
                <span className="h-px flex-1" style={{ backgroundColor: 'var(--u-border)' }} />
                <span className="text-[11px] tracking-[0.16em] uppercase" style={{ color: 'var(--u-text-muted)' }}>
                    or
                </span>
                <span className="h-px flex-1" style={{ backgroundColor: 'var(--u-border)' }} />
            </div>

            <div className="mt-5 flex flex-col gap-2.5">
                {socialProviders.map((provider) => {
                    const brand = BRAND[provider];
                    if (!brand) return null;

                    return (
                        // A full page navigation, not an Inertia visit — the
                        // OAuth redirect leaves the app entirely.
                        <a key={provider} href={`/auth/${provider}/redirect`} className="u-btn u-btn-ghost w-full py-2.5">
                            {brand.icon}
                            {action} with {brand.label}
                        </a>
                    );
                })}
            </div>
        </div>
    );
}
