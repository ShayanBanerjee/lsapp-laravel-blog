import { useEffect } from 'react';
import { prefersReducedMotion } from './use-motion';

/**
 * Enhance storytelling blocks inside an already-rendered body.
 *
 * Progressive enhancement rather than React islands, for three reasons that all
 * matter here: the body is injected as HTML (so there is no component tree to
 * mount into), it must stay fully readable when server-rendered with no JS, and
 * `Readable` repaints innerHTML whenever marks change — anything React had
 * mounted inside would be destroyed on the next mark.
 *
 * Under reduced motion this returns immediately, leaving every block in its
 * finished state: steps all visible, images shown, the wipe at 50%. Never
 * missing content.
 */
export function useStoryBlocks(containerRef: React.RefObject<HTMLElement | null>, deps: unknown[] = []) {
    useEffect(() => {
        const root = containerRef.current;

        if (!root) return;

        const blocks = Array.from(root.querySelectorAll<HTMLElement>('figure[data-story]'));

        if (blocks.length === 0) return;

        // Mark as enhanced so CSS can hide step numbering etc. only when we are
        // actually driving it. Without JS the static styles stand alone.
        blocks.forEach((block) => block.setAttribute('data-story-ready', ''));

        if (prefersReducedMotion()) {
            blocks.forEach((block) => block.setAttribute('data-story-settled', ''));

            return () => blocks.forEach((block) => block.removeAttribute('data-story-ready'));
        }

        const cleanups: Array<() => void> = [];

        for (const block of blocks) {
            const kind = block.getAttribute('data-story');

            if (kind === 'steps') {
                cleanups.push(enhanceSteps(block));
            } else if (kind === 'before-after') {
                cleanups.push(enhanceBeforeAfter(block));
            } else {
                cleanups.push(revealOnce(block));
            }
        }

        return () => {
            cleanups.forEach((cleanup) => cleanup());
            blocks.forEach((block) => {
                block.removeAttribute('data-story-ready');
                block.removeAttribute('data-story-settled');
            });
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, deps);
}

/** Reveal the block the first time it enters view, then stop observing. */
function revealOnce(block: HTMLElement): () => void {
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    block.setAttribute('data-story-settled', '');
                    observer.disconnect();
                }
            }
        },
        { threshold: 0.2 },
    );

    observer.observe(block);

    // Same failsafe as the step sequence: never leave a block stranded hidden.
    const failsafe = window.setTimeout(() => {
        block.setAttribute('data-story-settled', '');
        observer.disconnect();
    }, 3000);

    return () => {
        window.clearTimeout(failsafe);
        observer.disconnect();
    };
}

/**
 * Steps arrive one at a time as the reader descends.
 *
 * Driven by IntersectionObserver per child rather than a scroll listener —
 * scroll events are delayed through iOS momentum scrolling and coalesced in
 * some webviews, which is exactly when a reader would notice a step failing to
 * appear.
 */
function enhanceSteps(block: HTMLElement): () => void {
    const steps = Array.from(block.children).filter((child): child is HTMLElement => child instanceof HTMLElement);

    steps.forEach((step, index) => {
        step.setAttribute('data-story-step', String(index + 1));
    });

    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.setAttribute('data-story-settled', '');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.35, rootMargin: '0px 0px -10% 0px' },
    );

    steps.forEach((step) => observer.observe(step));
    block.setAttribute('data-story-settled', '');

    /*
     * Backstop, in the same spirit as `.u-reveal` being forced visible in CSS.
     * These steps start at opacity 0, so anything that stops the observer from
     * firing — a tab that was never brought to the foreground, a webview that
     * does not implement IntersectionObserver faithfully — would otherwise
     * leave the prose permanently invisible. Missing content is a far worse
     * outcome than an un-animated reveal.
     */
    const failsafe = window.setTimeout(() => {
        steps.forEach((step) => step.setAttribute('data-story-settled', ''));
        observer.disconnect();
    }, 3000);

    return () => {
        window.clearTimeout(failsafe);
        observer.disconnect();
    };
}

/**
 * A wipe between two images the reader controls.
 *
 * Pointer-driven, not scroll-driven: the whole argument for text over video is
 * that the reader sets the pace, and a comparison that animates itself takes
 * that back. Keyboard-operable via a real range input.
 */
function enhanceBeforeAfter(block: HTMLElement): () => void {
    const images = block.querySelectorAll('img');

    block.setAttribute('data-story-settled', '');

    if (images.length < 2) return () => {};

    const setPosition = (percent: number) => block.style.setProperty('--story-wipe', `${percent}%`);

    setPosition(50);

    const slider = document.createElement('input');
    slider.type = 'range';
    slider.min = '0';
    slider.max = '100';
    slider.value = '50';
    slider.className = 'story-wipe-handle';
    slider.setAttribute('aria-label', 'Compare before and after');

    const onInput = () => setPosition(Number(slider.value));

    slider.addEventListener('input', onInput);
    block.appendChild(slider);

    return () => {
        slider.removeEventListener('input', onInput);
        slider.remove();
        block.style.removeProperty('--story-wipe');
    };
}
