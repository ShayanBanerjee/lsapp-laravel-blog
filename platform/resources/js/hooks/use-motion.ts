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
 * Tracks an element's geometry every frame *while it is on screen*, and
 * otherwise not at all.
 *
 * Deliberately not driven by the `scroll` event. Scroll events are unreliable
 * as an animation clock: iOS Safari delays them through momentum scrolling,
 * some embedded webviews coalesce them heavily, and programmatic scrolling does
 * not always emit them. A rAF loop gated by IntersectionObserver reads the true
 * position every frame, costs nothing when the element is off screen, and is
 * what scroll-animation libraries settled on for the same reasons.
 *
 * `compute` must be cheap — it runs once per frame while visible.
 */
function useFrameTracked<T extends HTMLElement>(compute: (node: T) => number, onValue: (value: number) => void, enabled = true) {
    const ref = useRef<T>(null);
    const computeRef = useRef(compute);
    const onValueRef = useRef(onValue);

    computeRef.current = compute;
    onValueRef.current = onValue;

    useEffect(() => {
        const node = ref.current;
        if (!node || !enabled) return;

        let frame = 0;
        let visible = false;
        let last = Number.NaN;

        const tick = () => {
            const value = computeRef.current(node);

            // Only re-render when the value actually moved.
            if (value !== last) {
                last = value;
                onValueRef.current(value);
            }

            if (visible) frame = window.requestAnimationFrame(tick);
        };

        const start = () => {
            if (frame) return;
            frame = window.requestAnimationFrame(tick);
        };

        const stop = () => {
            if (frame) window.cancelAnimationFrame(frame);
            frame = 0;
        };

        // Without IntersectionObserver, fall back to always running rather than
        // never running — a slightly hot loop beats a dead animation.
        if (typeof IntersectionObserver === 'undefined') {
            visible = true;
            start();

            return stop;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                visible = entry.isIntersecting;
                if (visible) start();
                else stop();
            },
            { threshold: 0 },
        );

        observer.observe(node);
        // Seed once so the element is correct before it is ever scrolled.
        onValueRef.current(computeRef.current(node));

        return () => {
            observer.disconnect();
            stop();
        };
    }, [enabled]);

    return ref;
}

/**
 * Vertical parallax, in pixels of offset. `strength` is how far the element
 * drifts across a full viewport of scroll.
 */
export function useParallax<T extends HTMLElement = HTMLDivElement>(strength = 90) {
    const [offset, setOffset] = useState(0);
    const enabled = !prefersReducedMotion();

    const ref = useFrameTracked<T>(
        (node) => {
            const rect = node.getBoundingClientRect();
            const progress = (rect.top + rect.height / 2) / window.innerHeight - 0.5;

            // Round to whole pixels: sub-pixel churn re-renders every frame for
            // no visible benefit.
            return Math.round(-progress * strength);
        },
        setOffset,
        enabled,
    );

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

/**
 * Leans an element very slightly toward the pointer.
 *
 * Writes `--u-mx` / `--u-my` as pixel offsets, which `.u-btn` consumes in its
 * transform. Two decisions keep this from being a gimmick:
 *
 * - **The pull is capped low** (`strength`, default 4px). Past roughly six
 *   pixels the element stops feeling responsive and starts feeling like it is
 *   avoiding the cursor.
 * - **Writes are coalesced into one per animation frame.** `pointermove` fires
 *   far more often than the screen refreshes, and setting a custom property on
 *   every event is how a hover effect ends up costing more than the page.
 *
 * No-ops entirely on touch (`hover: none`) and under reduced motion, where the
 * offsets stay at zero and CSS forces the transform off anyway.
 */
export function useMagnetic<T extends HTMLElement = HTMLButtonElement>(strength = 4) {
    const ref = useRef<T>(null);

    useEffect(() => {
        const node = ref.current;

        if (!node) return;
        if (prefersReducedMotion()) return;
        if (typeof window === 'undefined' || !window.matchMedia('(hover: hover)').matches) return;

        let frame = 0;
        let x = 0;
        let y = 0;

        const write = () => {
            frame = 0;
            node.style.setProperty('--u-mx', `${x.toFixed(2)}px`);
            node.style.setProperty('--u-my', `${y.toFixed(2)}px`);
        };

        const schedule = () => {
            if (!frame) frame = window.requestAnimationFrame(write);
        };

        const onMove = (event: PointerEvent) => {
            const rect = node.getBoundingClientRect();

            // Offset from the centre, normalised to -1..1, then scaled. Using
            // the centre rather than the edge keeps the pull symmetrical on
            // wide buttons.
            x = ((event.clientX - rect.left) / rect.width - 0.5) * 2 * strength;
            y = ((event.clientY - rect.top) / rect.height - 0.5) * 2 * strength;
            schedule();
        };

        const onLeave = () => {
            x = 0;
            y = 0;
            schedule();
        };

        node.addEventListener('pointermove', onMove);
        node.addEventListener('pointerleave', onLeave);

        return () => {
            node.removeEventListener('pointermove', onMove);
            node.removeEventListener('pointerleave', onLeave);
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, [strength]);

    return ref;
}
