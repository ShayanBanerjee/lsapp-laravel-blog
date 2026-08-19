import { useEffect, type RefObject } from 'react';

/**
 * Attaches behaviour to storytelling blocks inside a rendered body.
 *
 * The markup comes from the server (App\Support\StoryBlocks) and is already
 * complete and readable without any of this — the CSS does the pinning, and
 * the comparison slider is a real range input. What is left is two things
 * JavaScript genuinely has to do:
 *
 * 1. Reveal sequence steps as they arrive, via IntersectionObserver rather
 *    than a scroll handler, so nothing runs on the main thread between
 *    intersections.
 * 2. Mirror the range input's value onto a CSS custom property, since CSS
 *    cannot read a form value.
 *
 * Both are re-run whenever `repaintKey` changes. The reading view replaces the
 * body's innerHTML every time marks are repainted, so the key has to cover the
 * marks as well as the body — otherwise a reader marking a passage silently
 * loses every observer and the slider's position.
 */
export function useStoryBlocks(container: RefObject<HTMLElement | null>, repaintKey: string): void {
    useEffect(() => {
        const root = container.current;

        if (!root) return;

        const cleanups: (() => void)[] = [];

        // --- Comparison sliders -----------------------------------------
        root.querySelectorAll<HTMLInputElement>('.u-story-compare-range').forEach((range) => {
            const frame = range.closest<HTMLElement>('.u-story-compare-frame');

            if (!frame) return;

            const sync = () => frame.style.setProperty('--u-compare', `${range.value}%`);

            sync();
            range.addEventListener('input', sync);
            cleanups.push(() => range.removeEventListener('input', sync));
        });

        // --- Sequence reveal --------------------------------------------
        const steps = root.querySelectorAll<HTMLElement>('.u-story-sequence .u-story-step');

        if (steps.length > 0) {
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            if (reduced || typeof IntersectionObserver === 'undefined') {
                // Degrade to the finished state, never to hidden content.
                steps.forEach((step) => step.setAttribute('data-revealed', 'true'));
            } else {
                const observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (!entry.isIntersecting) return;

                            entry.target.setAttribute('data-revealed', 'true');
                            // One-shot: a step that has been read should not
                            // fade out again when it leaves the viewport.
                            observer.unobserve(entry.target);
                        });
                    },
                    { rootMargin: '0px 0px -12% 0px', threshold: 0.15 },
                );

                steps.forEach((step) => observer.observe(step));
                cleanups.push(() => observer.disconnect());
            }
        }

        return () => cleanups.forEach((cleanup) => cleanup());
    }, [container, repaintKey]);
}
