import { useCallback, useEffect, useRef, useState } from 'react';
import { prefersReducedMotion } from './use-motion';

/**
 * The Deep Field's zoom, driven without React.
 *
 * ### Why this exists
 *
 * The obvious implementation — track scroll into React state, render layers
 * from it — reconciles the entire stage on every animation frame. At six
 * layers plus a caption and a rail that is sixty full reconciliations a second
 * competing with the compositor for the main thread, and it is the reason the
 * zoom stuttered. React is not slow; asking it to run an animation loop is
 * simply the wrong job for it.
 *
 * So the loop here writes `transform` and `opacity` straight onto the layer
 * elements. Both are compositor-only properties: no layout, no paint, no style
 * recalculation of anything else on the page. React renders the stage once and
 * is then only told when the *integer* layer changes — about six times for the
 * whole descent, for chrome that genuinely needs re-rendering.
 *
 * ### Three other things that were causing visible hitches
 *
 * 1. **Layers were unmounted when they left the visible band.** Re-entering
 *    meant re-creating the element and decoding the photograph again, at
 *    exactly the moment of transition. Everything now stays mounted and is
 *    hidden with `visibility`, which costs nothing and keeps images decoded.
 *
 * 2. **The scroll value was quantised to a thousand steps.** That was there to
 *    stop redundant React updates — a problem that no longer exists — and it
 *    put a floor on how finely the zoom could move. The loop now uses the full
 *    float.
 *
 * 3. **Momentum scrolling delivers position in visible jumps.** iOS in
 *    particular reports coarse steps during a fling. A light critically-damped
 *    follow absorbs that without adding the floaty lag a heavier smoothing
 *    would.
 */
export function useDeepZoom(layerCount: number) {
    const scrollRef = useRef<HTMLDivElement>(null);
    const layersRef = useRef<(HTMLElement | null)[]>([]);
    const captionRef = useRef<HTMLElement | null>(null);
    const hintRef = useRef<HTMLElement | null>(null);
    const depthRef = useRef(0);

    // Chrome only. Changes ~6 times across the whole descent.
    const [activeIndex, setActiveIndex] = useState(0);

    const registerLayer = useCallback(
        (index: number) => (node: HTMLElement | null) => {
            layersRef.current[index] = node;
        },
        [],
    );

    useEffect(() => {
        const scroller = scrollRef.current;

        if (!scroller || layerCount === 0) return;
        if (prefersReducedMotion()) return;

        let frame = 0;
        let running = false;
        let smoothed = -1;
        let lastActive = -1;

        const measure = () => {
            const total = scroller.scrollHeight - window.innerHeight;

            if (total <= 0) return 0;

            const raw = -scroller.getBoundingClientRect().top / total;

            return Math.min(1, Math.max(0, raw)) * layerCount;
        };

        const paint = (depth: number) => {
            depthRef.current = depth;

            for (let index = 0; index < layerCount; index++) {
                const node = layersRef.current[index];

                if (!node) continue;

                const local = depth - index;

                // Outside the visible band: hidden, but still mounted and still
                // decoded. `visibility` is not composited away the way
                // `display: none` tears down layout.
                if (local < -1.15 || local > 1.15) {
                    if (node.style.visibility !== 'hidden') {
                        node.style.visibility = 'hidden';
                        node.style.opacity = '0';
                    }
                    continue;
                }

                // Exponential growth is what sells a continuous zoom; a linear
                // scale reads as six separate slides.
                const scale = Math.pow(2, local);
                const opacity = local < 0 ? Math.max(0, 1 + local / 1.1) : Math.max(0, 1 - local / 0.95);

                node.style.visibility = 'visible';
                // translateZ(0) keeps each layer on its own compositor layer,
                // so a scale never forces a repaint of the ones behind it.
                node.style.transform = `translateZ(0) scale(${scale.toFixed(4)})`;
                node.style.opacity = opacity.toFixed(3);
            }

            const index = Math.min(layerCount - 1, Math.max(0, Math.round(depth - 0.5)));

            if (captionRef.current) {
                const local = depth - index;
                const captionOpacity = local < 0 ? 1 : Math.max(0, 1 - Math.pow(Math.max(0, local - 0.55) / 0.45, 2));

                captionRef.current.style.opacity = captionOpacity.toFixed(3);
            }

            if (hintRef.current) {
                hintRef.current.style.opacity = depth < 0.35 ? '1' : '0';
            }

            if (index !== lastActive) {
                lastActive = index;
                setActiveIndex(index);
            }
        };

        const tick = () => {
            const target = measure();

            // First frame lands exactly, so a reload mid-descent does not
            // animate in from the top.
            if (smoothed < 0) {
                smoothed = target;
            } else {
                const delta = target - smoothed;

                // Snap when close enough that further easing is invisible;
                // otherwise the loop never quite settles and never idles.
                smoothed = Math.abs(delta) < 0.0005 ? target : smoothed + delta * 0.28;
            }

            paint(smoothed);

            if (running) frame = window.requestAnimationFrame(tick);
        };

        const start = () => {
            if (running) return;
            running = true;
            frame = window.requestAnimationFrame(tick);
        };

        const stop = () => {
            running = false;
            if (frame) window.cancelAnimationFrame(frame);
            frame = 0;
        };

        /*
         * The loop runs only while the stage is on screen.
         *
         * A rAF loop that keeps running after the reader has scrolled past
         * burns battery on a page nobody is looking at — and this one is
         * measuring a bounding rect every frame.
         */
        const observer =
            typeof IntersectionObserver === 'undefined'
                ? null
                : new IntersectionObserver(
                      (entries) => {
                          entries.forEach((entry) => (entry.isIntersecting ? start() : stop()));
                      },
                      { threshold: 0 },
                  );

        if (observer) {
            observer.observe(scroller);
        } else {
            start();
        }

        // Paint once immediately so the first frame is correct before any
        // scrolling happens.
        paint(measure());

        const onResize = () => paint(measure());
        window.addEventListener('resize', onResize, { passive: true });

        return () => {
            stop();
            observer?.disconnect();
            window.removeEventListener('resize', onResize);
        };
    }, [layerCount]);

    /** Scroll to the middle of a given layer. */
    const jumpTo = useCallback(
        (index: number) => {
            const node = scrollRef.current;

            if (!node) return;

            const total = node.scrollHeight - window.innerHeight;
            const target = node.offsetTop + (total * (index + 0.5)) / layerCount;

            window.scrollTo({ top: target, behavior: 'smooth' });
        },
        [layerCount],
    );

    return { scrollRef, registerLayer, captionRef, hintRef, activeIndex, jumpTo, depthRef };
}
