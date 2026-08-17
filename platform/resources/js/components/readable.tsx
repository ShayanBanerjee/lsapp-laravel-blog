import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';

export interface Passage {
    block_index: number;
    start_offset: number;
    end_offset: number;
    quote: string;
    marks: number;
}

export interface OwnHighlight {
    id: number;
    block_index: number;
    start_offset: number;
    end_offset: number;
}

/** Index signature so this can be posted directly as an Inertia payload. */
export interface SelectionAnchor {
    block_index: number;
    start_offset: number;
    end_offset: number;
    quote: string;
    [key: string]: string | number;
}

/* ------------------------------------------------------------------ *
 * Anchoring
 *
 * A passage is addressed as (block index, start, end) where the offsets are
 * into the *plain text of that block*. Anchoring to whole-document offsets
 * would invalidate every mark in a piece the moment the author edits an
 * earlier paragraph; per-block offsets only break marks in the block that
 * actually changed.
 * ------------------------------------------------------------------ */

/** Direct block children of the article — the addressable units. */
function blocksOf(container: HTMLElement): HTMLElement[] {
    return Array.from(container.children) as HTMLElement[];
}

function textNodesOf(block: HTMLElement): Text[] {
    const walker = document.createTreeWalker(block, NodeFilter.SHOW_TEXT);
    const nodes: Text[] = [];
    let node = walker.nextNode();

    while (node) {
        nodes.push(node as Text);
        node = walker.nextNode();
    }

    return nodes;
}

/**
 * Wrap [start, end) of a block's text in <mark>.
 *
 * Works per text node rather than with Range.surroundContents, which throws
 * whenever a selection crosses an inline element boundary — i.e. any time a
 * reader marks a phrase containing a link or a bolded word.
 */
function paintRange(block: HTMLElement, start: number, end: number, className: string, dataset: Record<string, string>) {
    let cursor = 0;

    for (const node of textNodesOf(block)) {
        const length = node.data.length;
        const nodeStart = cursor;
        const nodeEnd = cursor + length;
        cursor = nodeEnd;

        const from = Math.max(start, nodeStart);
        const to = Math.min(end, nodeEnd);

        if (from >= to) continue;

        // Split so the overlapping slice is a node of its own, then wrap it.
        const target = from > nodeStart ? node.splitText(from - nodeStart) : node;
        if (to < nodeEnd) target.splitText(to - from);

        const mark = document.createElement('mark');
        mark.className = className;
        Object.entries(dataset).forEach(([key, value]) => mark.setAttribute(`data-${key}`, value));
        target.parentNode?.replaceChild(mark, target);
        mark.appendChild(target);
    }
}

/** Where in the document is the current selection? */
function anchorFromSelection(container: HTMLElement): SelectionAnchor | null {
    const selection = window.getSelection();

    if (!selection || selection.isCollapsed || selection.rangeCount === 0) return null;

    const range = selection.getRangeAt(0);
    if (!container.contains(range.commonAncestorContainer)) return null;

    const blocks = blocksOf(container);
    const blockIndex = blocks.findIndex((block) => block.contains(range.commonAncestorContainer));

    // A selection spanning multiple blocks has no single anchor; ask the reader
    // to mark one passage instead of silently truncating their selection.
    if (blockIndex === -1) return null;

    const block = blocks[blockIndex];

    // Offset of the range start within the block's plain text.
    const before = range.cloneRange();
    before.selectNodeContents(block);
    before.setEnd(range.startContainer, range.startOffset);
    const start = before.toString().length;

    const quote = range.toString();
    const end = start + quote.length;

    if (quote.trim().length < 2) return null;

    return { block_index: blockIndex, start_offset: start, end_offset: end, quote };
}

/* ------------------------------------------------------------------ *
 * The component
 * ------------------------------------------------------------------ */

export function Readable({
    html,
    passages,
    own,
    canMark,
    onMark,
    onUnmark,
}: {
    html: string;
    passages: Passage[];
    own: OwnHighlight[];
    canMark: boolean;
    onMark: (anchor: SelectionAnchor) => void;
    onUnmark: (id: number) => void;
}) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [anchor, setAnchor] = useState<SelectionAnchor | null>(null);
    const [toolbar, setToolbar] = useState<{ x: number; y: number } | null>(null);

    /**
     * Repaint marks from scratch whenever they change.
     *
     * Resetting innerHTML first is deliberate: incrementally adding <mark>
     * wrappers would nest them on every update. Rebuilding is O(body) and the
     * body is one article, so this is cheaper than the bookkeeping.
     */
    useLayoutEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        container.innerHTML = html;
        const blocks = blocksOf(container);
        const ownKeys = new Set(own.map((h) => `${h.block_index}:${h.start_offset}:${h.end_offset}`));
        const ownIds = new Map(own.map((h) => [`${h.block_index}:${h.start_offset}:${h.end_offset}`, h.id]));

        // Paint in reverse document order so earlier splits do not shift the
        // offsets of passages that have not been painted yet.
        [...passages]
            .sort((a, b) => b.block_index - a.block_index || b.start_offset - a.start_offset)
            .forEach((passage) => {
                const block = blocks[passage.block_index];
                if (!block) return;

                const key = `${passage.block_index}:${passage.start_offset}:${passage.end_offset}`;
                const mine = ownKeys.has(key);

                paintRange(block, passage.start_offset, passage.end_offset, mine ? 'u-mark is-mine' : 'u-mark', {
                    marks: String(passage.marks),
                    ...(mine ? { 'own-id': String(ownIds.get(key)) } : {}),
                });
            });
    }, [html, passages, own]);

    // Offer the mark control whenever there is a usable selection.
    const refreshSelection = useCallback(() => {
        const container = containerRef.current;
        if (!container) return;

        const next = anchorFromSelection(container);

        if (!next) {
            setAnchor(null);
            setToolbar(null);

            return;
        }

        const range = window.getSelection()?.getRangeAt(0);
        const rect = range?.getBoundingClientRect();

        setAnchor(next);
        setToolbar(rect ? { x: rect.left + rect.width / 2, y: rect.top } : null);
    }, []);

    useEffect(() => {
        if (!canMark) return;

        document.addEventListener('selectionchange', refreshSelection);

        return () => document.removeEventListener('selectionchange', refreshSelection);
    }, [canMark, refreshSelection]);

    // Clicking your own mark removes it.
    useEffect(() => {
        const container = containerRef.current;
        if (!container) return;

        const onClick = (event: MouseEvent) => {
            const mark = (event.target as HTMLElement).closest?.('mark[data-own-id]');
            if (!mark) return;

            event.preventDefault();
            onUnmark(Number(mark.getAttribute('data-own-id')));
        };

        container.addEventListener('click', onClick);

        return () => container.removeEventListener('click', onClick);
    }, [onUnmark]);

    return (
        <>
            {/*
              Body is sanitized server-side against a tag allowlist on write
              (App\Support\HtmlSanitizer), which is what makes this safe; the
              CSP is the second line of defence.
            */}
            <div ref={containerRef} className="prose-u mx-auto max-w-2xl" />

            {canMark && anchor && toolbar && (
                <div
                    className="fixed z-[70] -translate-x-1/2 -translate-y-[calc(100%+10px)]"
                    style={{ left: toolbar.x, top: toolbar.y }}
                    role="dialog"
                    aria-label="Mark this passage"
                >
                    <button
                        type="button"
                        className="u-btn u-btn-primary shadow-lg"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => {
                            onMark(anchor);
                            window.getSelection()?.removeAllRanges();
                            setAnchor(null);
                            setToolbar(null);
                        }}
                    >
                        Mark this passage
                    </button>
                </div>
            )}
        </>
    );
}
