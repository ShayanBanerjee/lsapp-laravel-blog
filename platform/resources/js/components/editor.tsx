import { STORY_KINDS, StoryBlock, type StoryKind } from '@/components/story-blocks';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor, type Editor as TiptapEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Clapperboard, Code, Heading2, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Strikethrough, Undo2 } from 'lucide-react';
import type { ComponentType } from 'react';

/**
 * Replaces the legacy CKEditor integration.
 *
 * Output is HTML and is re-sanitized server-side against a tag allowlist before
 * storage — nothing here is trusted as a security boundary.
 */
export function Editor({ value, onChange }: { value: string; onChange: (html: string) => void }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] } }),
            Placeholder.configure({ placeholder: 'Begin where the world begins…' }),
            Link.configure({ openOnClick: false, autolink: true }),
            StoryBlock,
        ],
        content: value,
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
        editorProps: { attributes: { class: 'prose-u tiptap max-w-none' } },
    });

    if (!editor) return null;

    return (
        <div className="metal metal-edge grain">
            <Toolbar editor={editor} />
            <div className="px-5 py-6 sm:px-8 sm:py-8">
                <EditorContent editor={editor} />
            </div>
        </div>
    );
}

function Toolbar({ editor }: { editor: TiptapEditor }) {
    return (
        <div
            className="flex flex-wrap items-center gap-0.5 border-b px-3 py-2"
            style={{ borderColor: 'var(--u-border)', backgroundColor: 'color-mix(in srgb, var(--u-surface-2) 60%, transparent)' }}
        >
            <Tool icon={Bold} label="Bold" active={editor.isActive('bold')} onClick={() => editor.chain().focus().toggleBold().run()} />
            <Tool icon={Italic} label="Italic" active={editor.isActive('italic')} onClick={() => editor.chain().focus().toggleItalic().run()} />
            <Tool
                icon={Strikethrough}
                label="Strikethrough"
                active={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            />

            <Divider />

            <Tool
                icon={Heading2}
                label="Heading 2"
                active={editor.isActive('heading', { level: 2 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
            />
            <Tool
                icon={Heading3}
                label="Heading 3"
                active={editor.isActive('heading', { level: 3 })}
                onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
            />

            <Divider />

            <Tool
                icon={List}
                label="Bullet list"
                active={editor.isActive('bulletList')}
                onClick={() => editor.chain().focus().toggleBulletList().run()}
            />
            <Tool
                icon={ListOrdered}
                label="Numbered list"
                active={editor.isActive('orderedList')}
                onClick={() => editor.chain().focus().toggleOrderedList().run()}
            />
            <Tool icon={Quote} label="Quote" active={editor.isActive('blockquote')} onClick={() => editor.chain().focus().toggleBlockquote().run()} />
            <Tool
                icon={Code}
                label="Code block"
                active={editor.isActive('codeBlock')}
                onClick={() => editor.chain().focus().toggleCodeBlock().run()}
            />

            <Divider />

            <Tool
                icon={Link2}
                label="Add link"
                active={editor.isActive('link')}
                onClick={() => {
                    const previous = editor.getAttributes('link').href as string | undefined;
                    const url = window.prompt('Link URL', previous ?? 'https://');

                    if (url === null) return;

                    if (url === '') {
                        editor.chain().focus().extendMarkRange('link').unsetLink().run();

                        return;
                    }

                    editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
                }}
            />

            <Divider />

            <StoryMenu editor={editor} />

            <div className="ml-auto flex gap-0.5">
                <Tool icon={Undo2} label="Undo" onClick={() => editor.chain().focus().undo().run()} disabled={!editor.can().undo()} />
                <Tool icon={Redo2} label="Redo" onClick={() => editor.chain().focus().redo().run()} disabled={!editor.can().redo()} />
            </div>
        </div>
    );
}

/**
 * Storytelling blocks. A plain <details> rather than a popover component —
 * it needs no state, closes on its own, and is keyboard-operable for free.
 */
function StoryMenu({ editor }: { editor: TiptapEditor }) {
    return (
        <details className="relative">
            <summary
                className="flex cursor-pointer list-none items-center gap-1.5 rounded-[7px] p-2 text-xs transition-colors"
                style={{ color: 'var(--u-text-muted)' }}
                title="Insert a storytelling block"
            >
                <Clapperboard className="size-4" />
                Story block
            </summary>

            <div
                className="absolute top-full left-0 z-20 mt-1 w-64 rounded-[10px] border p-1.5 shadow-lg"
                style={{ borderColor: 'var(--u-border)', background: 'var(--u-surface-1)' }}
            >
                {STORY_KINDS.map(({ kind, label, hint }) => (
                    <button
                        key={kind}
                        type="button"
                        onClick={() => insert(editor, kind)}
                        className="block w-full rounded-[7px] px-2.5 py-2 text-left transition-colors hover:bg-[var(--u-surface-2)]"
                    >
                        <span className="block text-sm">{label}</span>
                        <span className="block text-xs" style={{ color: 'var(--u-text-muted)' }}>
                            {hint}
                        </span>
                    </button>
                ))}
            </div>
        </details>
    );
}

function insert(editor: TiptapEditor, kind: StoryKind) {
    if (kind === 'callout') {
        const value = window.prompt('The number or figure to show large', '68%');

        if (value === null) return;

        const label = window.prompt('A short label beneath it (optional)', '') ?? '';

        editor.chain().focus().setStoryBlock(kind).updateAttributes('storyBlock', { value, label }).run();

        return;
    }

    editor.chain().focus().setStoryBlock(kind).run();
}

function Divider() {
    return <span className="mx-1.5 h-5 w-px" style={{ backgroundColor: 'var(--u-border)' }} aria-hidden />;
}

function Tool({
    icon: Icon,
    label,
    active,
    disabled,
    onClick,
}: {
    icon: ComponentType<{ className?: string }>;
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            title={label}
            aria-label={label}
            aria-pressed={active}
            className="rounded-[7px] p-2 transition-colors disabled:opacity-30"
            style={{
                color: active ? 'var(--u-accent)' : 'var(--u-text-muted)',
                backgroundColor: active ? 'var(--u-accent-soft)' : 'transparent',
            }}
        >
            <Icon className="size-4" />
        </button>
    );
}
