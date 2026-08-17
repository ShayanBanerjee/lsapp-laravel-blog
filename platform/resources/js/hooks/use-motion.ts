import { useEffect, useRef, useState } from 'react';

/**
 * Motion primitives.
 *
 * Every hook here checks prefers-reduced-motion and degrades to a static,
 * fully-visible result rather than an animated one. Reduced motion must never
 * mean "content missing" — it means "content, without the movement".
 */

export function prefersReducedMotion(): boolean {
    if (typeof window === 'undefined') return false;

    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Reveals an element the first time it scrolls into view.
 * Returns [ref, visible] — visible starts true under reduced motion.
 */
export function useReveal<T extends HTMLElement = HTMLDivElement>(threshold = 0.15) {
    const ref = useRef<T>(null);
    const [visible, setVisible] = useState(() => prefersReducedMotion());

    useEffect(() => {
        const node = ref.current;

        if (!node || prefersReducedMotion()) {
            setVisible(true);

            return;
        }

        // If IntersectionObserver is unavailable, show the content rather than
        // leaving it permanently transparent.
        if (typeof IntersectionObserver === 'undefined') {
            setVisible(true);

            return;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    observer.disconnect();
                }
            },
            { threshold, rootMargin: '0px 0px -8% 0px' },
        );

        observer.observe(node);

        return () => observer.disconnect();
    }, [threshold]);

    return [ref, visible] as const;
}

/**
 * Vertical parallax driven by scroll position, in pixels of offset.
 * `strength` is how far the element drifts across a full viewport of scroll.
 */
export function useParallax<T extends HTMLElement = HTMLDivElement>(strength = 90) {
    const ref = useRef<T>(null);
    const [offset, setOffset] = useState(0);

    useEffect(() => {
        const node = ref.current;

        if (!node || prefersReducedMotion()) return;

        let frame = 0;

        const update = () => {
            frame = 0;
            const rect = node.getBoundingClientRect();
            const progress = (rect.top + rect.height / 2) / window.innerHeight - 0.5;
            setOffset(-progress * strength);
        };

        const onScroll = () => {
            // Coalesce scroll events into one write per frame.
            if (!frame) frame = window.requestAnimationFrame(update);
        };

        update();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, [strength]);

    return [ref, offset] as const;
}

/**
 * Tracks the pointer within an element and writes its position to CSS custom
 * properties (--mx / --my, as percentages). Used to make the specular highlight
 * on metal actually follow the cursor instead of sweeping on a timer.
 */
export function usePointerSpecular<T extends HTMLElement = HTMLDivElement>() {
    const ref = useRef<T>(null);

    useEffect(() => {
        const node = ref.current;

        if (!node || prefersReducedMotion()) return;

        // Touch devices have no hover; skip the listener entirely there.
        if (!window.matchMedia('(hover: hover)').matches) return;

        let frame = 0;
        let last: { x: number; y: number } | null = null;

        const write = () => {
            frame = 0;
            if (!last) return;
            node.style.setProperty('--mx', `${last.x}%`);
            node.style.setProperty('--my', `${last.y}%`);
        };

        const onMove = (event: PointerEvent) => {
            const rect = node.getBoundingClientRect();
            last = {
                x: ((event.clientX - rect.left) / rect.width) * 100,
                y: ((event.clientY - rect.top) / rect.height) * 100,
            };
            if (!frame) frame = window.requestAnimationFrame(write);
        };

        const onLeave = () => {
            node.style.removeProperty('--mx');
            node.style.removeProperty('--my');
        };

        node.addEventListener('pointermove', onMove);
        node.addEventListener('pointerleave', onLeave);

        return () => {
            node.removeEventListener('pointermove', onMove);
            node.removeEventListener('pointerleave', onLeave);
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, []);

    return ref;
}

/** Counts from 0 to `value` once the element is in view. */
export function useCountUp(value: number, duration = 900) {
    const [ref, visible] = useReveal<HTMLSpanElement>(0.4);
    const [display, setDisplay] = useState(() => (prefersReducedMotion() ? value : 0));

    useEffect(() => {
        if (!visible || prefersReducedMotion()) {
            setDisplay(value);

            return;
        }

        let frame = 0;
        const start = performance.now();

        const tick = (now: number) => {
            const progress = Math.min(1, (now - start) / duration);
            // easeOutCubic
            setDisplay(Math.round(value * (1 - Math.pow(1 - progress, 3))));

            if (progress < 1) frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => window.cancelAnimationFrame(frame);
    }, [visible, value, duration]);

    return [ref, display] as const;
}
