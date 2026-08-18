import { Node, mergeAttributes } from '@tiptap/core';

/**
 * Storytelling blocks.
 *
 * The Deep Field proved these mechanics on one hardcoded page; these make them
 * available to any writer. Four kinds, chosen because each does something a
 * paragraph cannot and video does badly — the reader keeps control of the pace
 * in all of them.
 *
 * The stored shape is deliberately plain semantic HTML with a `data-story`
 * label: `<figure data-story="pinned">…</figure>`. Nothing about the behaviour
 * lives in the markup. That means a block is fully readable with no JavaScript
 * at all — server-rendered, in an RSS reader, or under reduced motion — and the
 * scroll behaviour is layered on afterwards as an enhancement. It also keeps
 * the sanitiser's job small: one attribute with four legal values.
 */

declare module '@tiptap/core' {
    interface Commands<ReturnType> {
        storyBlock: {
            setStoryBlock: (kind: StoryKind) => ReturnType;
        };
    }
}

export type StoryKind = 'pinned' | 'steps' | 'before-after' | 'callout';

export const STORY_KINDS: { kind: StoryKind; label: string; hint: string }[] = [
    { kind: 'pinned', label: 'Pinned image', hint: 'An image that holds still while the text moves past it.' },
    { kind: 'steps', label: 'Step sequence', hint: 'Numbered steps that arrive one at a time as the reader descends.' },
    { kind: 'before-after', label: 'Before / after', hint: 'Two states the reader can wipe between at their own speed.' },
    { kind: 'callout', label: 'Data callout', hint: 'One number, large, with the sentence that makes it mean something.' },
];

export const StoryBlock = Node.create({
    name: 'storyBlock',
    group: 'block',
    // Ordinary blocks inside, so the whole editing surface keeps working
    // within a story block — including marking it once published.
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            kind: {
                default: 'callout' as StoryKind,
                parseHTML: (element) => element.getAttribute('data-story'),
                renderHTML: (attributes) => ({ 'data-story': attributes.kind }),
            },
            label: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-story-label'),
                renderHTML: (attributes) => (attributes.label ? { 'data-story-label': attributes.label } : {}),
            },
            value: {
                default: null,
                parseHTML: (element) => element.getAttribute('data-story-value'),
                renderHTML: (attributes) => (attributes.value ? { 'data-story-value': attributes.value } : {}),
            },
        };
    },

    parseHTML() {
        return [{ tag: 'figure[data-story]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['figure', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            setStoryBlock:
                (kind: StoryKind) =>
                ({ commands }) =>
                    commands.insertContent({
                        type: this.name,
                        attrs: { kind },
                        content: [{ type: 'paragraph', content: [{ type: 'text', text: seedText(kind) }] }],
                    }),
        };
    },
});

function seedText(kind: StoryKind): string {
    return {
        pinned: 'Describe what the reader is looking at while it stays on screen.',
        steps: 'One step per paragraph. Each arrives as the reader reaches it.',
        'before-after': 'What changed between the two states, and why it matters.',
        callout: 'The sentence that makes the number mean something.',
    }[kind];
}
