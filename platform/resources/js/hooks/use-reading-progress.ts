import { useEffect, useRef } from 'react';

/**
 * How far through the article you are, as a bar at the top of the page.
 *
 * Written straight to the DOM in a rAF loop rather than through state, for the
 * same reason the Deep Field's zoom is — a scroll-linked value in React state
 * reconciles the tree on every frame, and the one thing a reading view must
 * never do is stutter while someone is reading.
 *
 * Progress is measured against the *article*, not the document, so the footer
 * and the response thread do not count as unread reading. Finishing the prose
 * should read as 100%.
 */
export function useReadingProgress() {
    const barRef = useRef<HTMLElement | null>(null);
    const articleRef = useRef<HTMLElement | null>(null);

    useEffect(() => {
        const bar = barRef.current;
        const article = articleRef.current;

        if (!bar || !article) return;

        let frame = 0;
        let running = true;
        let last = -1;
        let lastVisible: boolean | null = null;

        const tick = () => {
            const rect = article.getBoundingClientRect();

            // Distance from the article's top passing the viewport top, over the
            // distance available before its bottom reaches the viewport bottom.
            const total = rect.height - window.innerHeight;

            /*
             * A short lesson that fits entirely on screen has no progress to
             * report. Showing a full bar there would claim the reader had
             * finished something they had not started, and showing an empty one
             * would claim the opposite — so the bar hides itself instead.
             */
            const measurable = total > 0;

            if (measurable !== lastVisible) {
                lastVisible = measurable;
                bar.style.opacity = measurable ? '1' : '0';
            }

            if (measurable) {
                const value = Math.min(1, Math.max(0, -rect.top / total));

                if (Math.abs(value - last) > 0.001) {
                    last = value;
                    bar.style.transform = `scaleX(${value.toFixed(4)})`;
                }
            }

            if (running) frame = window.requestAnimationFrame(tick);
        };

        frame = window.requestAnimationFrame(tick);

        return () => {
            running = false;
            if (frame) window.cancelAnimationFrame(frame);
        };
    }, []);

    return { barRef, articleRef };
}
