import { Node, mergeAttributes } from '@tiptap/core';

/**
 * TipTap nodes for the storytelling blocks.
 *
 * Each one serialises to exactly the compact form App\Support\StoryBlocks
 * validates and expands — one `<figure data-story="…">` carrying scalar
 * attributes and no children. Keeping the editor's output identical to the
 * stored form is what lets a block round-trip through save and reload without
 * the sanitizer quietly rewriting it.
 *
 * The nodes are atoms: the editor treats a block as one indivisible thing you
 * select and delete, rather than a container you can accidentally type inside
 * and corrupt.
 */

export const STEP_SEPARATOR = '||';

interface StorySpec {
    name: string;
    story: string;
    attributes: string[];
}

const SPECS: StorySpec[] = [
    { name: 'pinnedImage', story: 'pinned', attributes: ['src', 'alt', 'caption', 'steps'] },
    { name: 'stepSequence', story: 'sequence', attributes: ['title', 'steps'] },
    { name: 'beforeAfter', story: 'compare', attributes: ['before', 'after', 'before-label', 'after-label', 'caption'] },
    { name: 'dataCallout', story: 'callout', attributes: ['value', 'label', 'note'] },
];

function build(spec: StorySpec) {
    return Node.create({
        name: spec.name,
        group: 'block',
        atom: true,
        draggable: true,

        addAttributes() {
            return Object.fromEntries(
                spec.attributes.map((attribute) => [
                    attribute,
                    {
                        default: '',
                        parseHTML: (element: HTMLElement) => element.getAttribute(`data-${attribute}`) ?? '',
                        renderHTML: (attributes: Record<string, string>) =>
                            attributes[attribute] ? { [`data-${attribute}`]: attributes[attribute] } : {},
                    },
                ]),
            );
        },

        parseHTML() {
            return [{ tag: `figure[data-story="${spec.story}"]` }];
        },

        renderHTML({ HTMLAttributes }) {
            return ['figure', mergeAttributes(HTMLAttributes, { 'data-story': spec.story }), 0];
        },
    });
}

export const PinnedImage = build(SPECS[0]);
export const StepSequence = build(SPECS[1]);
export const BeforeAfter = build(SPECS[2]);
export const DataCallout = build(SPECS[3]);

export const STORY_NODES = [PinnedImage, StepSequence, BeforeAfter, DataCallout];

/** What each block is for, shown in the insert menu. */
export const STORY_KINDS = [
    {
        node: 'pinnedImage',
        label: 'Pinned image',
        hint: 'An image that holds still while your steps scroll past it.',
        fields: [
            { key: 'src', label: 'Image URL', placeholder: '/images/covers/…' },
            { key: 'alt', label: 'Alt text', placeholder: 'What the image shows' },
            { key: 'steps', label: 'Steps', placeholder: 'One per line', multiline: true },
            { key: 'caption', label: 'Caption', placeholder: 'Optional' },
        ],
    },
    {
        node: 'stepSequence',
        label: 'Step-through',
        hint: 'Numbered steps that arrive one at a time as the reader scrolls.',
        fields: [
            { key: 'title', label: 'Title', placeholder: 'Optional' },
            { key: 'steps', label: 'Steps', placeholder: 'One per line', multiline: true },
        ],
    },
    {
        node: 'beforeAfter',
        label: 'Before / after',
        hint: 'Two images with a slider between them.',
        fields: [
            { key: 'before', label: 'Before image URL', placeholder: '/images/…' },
            { key: 'after', label: 'After image URL', placeholder: '/images/…' },
            { key: 'before-label', label: 'Before label', placeholder: 'Before' },
            { key: 'after-label', label: 'After label', placeholder: 'After' },
            { key: 'caption', label: 'Caption', placeholder: 'Optional' },
        ],
    },
    {
        node: 'dataCallout',
        label: 'Data callout',
        hint: 'One number, said loudly.',
        fields: [
            { key: 'value', label: 'The number', placeholder: '87%' },
            { key: 'label', label: 'What it means', placeholder: 'of drafts are never published' },
            { key: 'note', label: 'Source or note', placeholder: 'Optional' },
        ],
    },
] as const;

/** Steps are typed one per line and stored joined — see StoryBlocks. */
export function linesToSteps(value: string): string {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .join(STEP_SEPARATOR);
}

export function stepsToLines(value: string): string {
    return value.split(STEP_SEPARATOR).join('\n');
}
