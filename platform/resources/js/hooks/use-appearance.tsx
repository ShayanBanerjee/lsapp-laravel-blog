import { useEffect, useState } from 'react';

export type Appearance = 'light' | 'dark' | 'system';

/*
 * Everything here is browser-only, and this module is imported during server
 * rendering. Touching `window` or `localStorage` at module scope crashes the
 * SSR process at import time — before a single page is rendered — so every
 * entry point guards, and the media query is resolved lazily rather than held
 * in a module-level const.
 */

const isBrowser = () => typeof window !== 'undefined';

const prefersDark = () => isBrowser() && window.matchMedia('(prefers-color-scheme: dark)').matches;

const applyTheme = (appearance: Appearance) => {
    if (!isBrowser()) return;

    const isDark = appearance === 'dark' || (appearance === 'system' && prefersDark());

    document.documentElement.classList.toggle('dark', isDark);
};

const mediaQuery = () => (isBrowser() ? window.matchMedia('(prefers-color-scheme: dark)') : null);

const handleSystemThemeChange = () => {
    const currentAppearance = localStorage.getItem('appearance') as Appearance;
    applyTheme(currentAppearance || 'system');
};

export function initializeTheme() {
    if (!isBrowser()) return;

    const savedAppearance = (localStorage.getItem('appearance') as Appearance) || 'system';

    applyTheme(savedAppearance);

    // Add the event listener for system theme changes...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

export function useAppearance() {
    const [appearance, setAppearance] = useState<Appearance>('system');

    const updateAppearance = (mode: Appearance) => {
        setAppearance(mode);
        localStorage.setItem('appearance', mode);
        applyTheme(mode);
    };

    useEffect(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;
        updateAppearance(savedAppearance || 'system');

        return () => mediaQuery()?.removeEventListener('change', handleSystemThemeChange);
    }, []);

    return { appearance, updateAppearance };
}
