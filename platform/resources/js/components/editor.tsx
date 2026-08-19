import { STORY_KINDS, STORY_NODES, linesToSteps, stepsToLines } from '@/lib/story-nodes';
import Link from '@tiptap/extension-link';
import Placeholder from '@tiptap/extension-placeholder';
import { EditorContent, useEditor, type Editor as TiptapEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { Bold, Clapperboard, Code, Heading2, Heading3, Italic, Link2, List, ListOrdered, Quote, Redo2, Strikethrough, Undo2, X } from 'lucide-react';
import { useState, type ComponentType } from 'react';

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
            // Storytelling blocks. These serialise to the same compact
            // <figure data-story="…"> the server validates and expands.
            ...STORY_NODES,
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
 * Inserting a storytelling block.
 *
 * A small form rather than an in-canvas editor: these blocks are a handful of
 * scalar fields, and asking for them plainly is clearer than a WYSIWYG surface
 * that has to explain that you cannot type inside the image.
 */
function StoryMenu({ editor }: { editor: TiptapEditor }) {
    const [open, setOpen] = useState(false);
    const [kind, setKind] = useState<(typeof STORY_KINDS)[number] | null>(null);
    const [values, setValues] = useState<Record<string, string>>({});

    const close = () => {
        setOpen(false);
        setKind(null);
        setValues({});
    };

    const insert = () => {
        if (!kind) return;

        const attributes: Record<string, string> = {};

        kind.fields.forEach((field) => {
            const raw = values[field.key] ?? '';
            attributes[field.key] = field.key === 'steps' ? linesToSteps(raw) : raw.trim();
        });

        editor.chain().focus().insertContent({ type: kind.node, attrs: attributes }).run();
        close();
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                title="Insert a storytelling block"
                aria-label="Insert a storytelling block"
                className="flex items-center gap-1.5 rounded-[7px] px-2.5 py-2 text-xs transition-colors"
                style={{ color: 'var(--u-text-muted)' }}
            >
                <Clapperboard className="size-4" />
                Story block
            </button>

            {open && (
                <div className="fixed inset-0 z-[80] flex items-start justify-center overflow-y-auto p-6 sm:p-12">
                    <button
                        type="button"
                        aria-label="Close"
                        onClick={close}
                        className="absolute inset-0"
                        style={{ backgroundColor: 'color-mix(in srgb, var(--u-bg-deep) 82%, transparent)' }}
                    />

                    <div
                        className="relative w-full max-w-lg rounded-[14px] border p-6"
                        style={{ backgroundColor: 'var(--u-surface-1)', borderColor: 'var(--u-border)' }}
                    >
                        <div className="mb-5 flex items-start justify-between gap-4">
                            <div>
                                <h2 className="font-display text-xl">Add a story block</h2>
                                <p className="mt-1 text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                    These behave the same for every reader, and degrade to plain text for anyone who has asked for less motion.
                                </p>
                            </div>
                            <button type="button" onClick={close} aria-label="Close" className="u-btn u-btn-ghost px-2 py-1">
                                <X className="size-4" />
                            </button>
                        </div>

                        {kind === null ? (
                            <div className="flex flex-col gap-2">
                                {STORY_KINDS.map((option) => (
                                    <button
                                        key={option.node}
                                        type="button"
                                        onClick={() => setKind(option)}
                                        className="rounded-[9px] border p-3 text-left transition-colors"
                                        style={{ borderColor: 'var(--u-border)' }}
                                    >
                                        <span className="block text-sm font-medium">{option.label}</span>
                                        <span className="mt-0.5 block text-xs" style={{ color: 'var(--u-text-muted)' }}>
                                            {option.hint}
                                        </span>
                                    </button>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col gap-4">
                                {kind.fields.map((field) => (
                                    <label key={field.key} className="flex flex-col gap-1.5">
                                        <span
                                            className="text-[11px] font-semibold tracking-[0.16em] uppercase"
                                            style={{ color: 'var(--u-text-muted)' }}
                                        >
                                            {field.label}
                                        </span>
                                        {'multiline' in field && field.multiline ? (
                                            <textarea
                                                rows={4}
                                                value={stepsToLines(values[field.key] ?? '')}
                                                onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                                                placeholder={field.placeholder}
                                                className="u-field"
                                            />
                                        ) : (
                                            <input
                                                value={values[field.key] ?? ''}
                                                onChange={(event) => setValues((current) => ({ ...current, [field.key]: event.target.value }))}
                                                placeholder={field.placeholder}
                                                className="u-field"
                                            />
                                        )}
                                    </label>
                                ))}

                                <div className="mt-2 flex gap-2">
                                    <button type="button" onClick={insert} className="u-btn u-btn-primary">
                                        Insert {kind.label.toLowerCase()}
                                    </button>
                                    <button type="button" onClick={() => setKind(null)} className="u-btn u-btn-ghost">
                                        Back
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
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
