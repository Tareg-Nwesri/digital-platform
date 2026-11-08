<script setup lang="ts">
import StarterKit from '@tiptap/starter-kit';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import {
    Bold,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    List,
    ListOrdered,
    Quote,
    Redo2,
    Undo2,
} from '@lucide/vue';
import { trans } from 'laravel-vue-i18n';
import { onBeforeUnmount, watch } from 'vue';

/**
 * Rich text with a short toolbar (LLD 10 §4). It emits HTML; the server
 * sanitises it again. `dir` sets the writing direction of this field, so an
 * Arabic body is right to left even on an English page and vice versa.
 */
const props = defineProps<{
    id?: string;
    dir?: 'rtl' | 'ltr';
    disabled?: boolean;
    label?: string;
}>();

const model = defineModel<string>({ default: '' });

const editor = useEditor({
    content: model.value,
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            heading: { levels: [2, 3] },
            link: {
                openOnClick: false,
                protocols: ['http', 'https', 'mailto'],
            },
        }),
    ],
    editorProps: {
        attributes: {
            class: 'prose-editor min-h-48 px-3 py-2 text-sm leading-7 outline-none',
            dir: props.dir ?? 'rtl',
            ...(props.id ? { id: props.id } : {}),
            ...(props.label ? { 'aria-label': props.label } : {}),
        },
    },
    onUpdate: ({ editor: instance }) => {
        model.value = instance.isEmpty ? '' : instance.getHTML();
    },
});

watch(model, (value) => {
    if (
        editor.value &&
        value !== editor.value.getHTML() &&
        !(value === '' && editor.value.isEmpty)
    ) {
        editor.value.commands.setContent(value, { emitUpdate: false });
    }
});

watch(
    () => props.disabled,
    (disabled) => editor.value?.setEditable(!disabled),
);

onBeforeUnmount(() => editor.value?.destroy());

function setLink(): void {
    const previous = editor.value?.getAttributes('link').href as
        | string
        | undefined;
    const url = window.prompt(trans('Link address'), previous ?? 'https://');

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        editor.value?.chain().focus().unsetLink().run();

        return;
    }

    editor.value
        ?.chain()
        .focus()
        .extendMarkRange('link')
        .setLink({ href: url.trim() })
        .run();
}

type Tool = {
    label: string;
    icon: unknown;
    run: () => void;
    active?: () => boolean;
};

const tools: Tool[] = [
    {
        label: 'Heading',
        icon: Heading2,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 2 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 2 }),
    },
    {
        label: 'Subheading',
        icon: Heading3,
        run: () =>
            editor.value?.chain().focus().toggleHeading({ level: 3 }).run(),
        active: () => !!editor.value?.isActive('heading', { level: 3 }),
    },
    {
        label: 'Bold',
        icon: Bold,
        run: () => editor.value?.chain().focus().toggleBold().run(),
        active: () => !!editor.value?.isActive('bold'),
    },
    {
        label: 'Italic',
        icon: Italic,
        run: () => editor.value?.chain().focus().toggleItalic().run(),
        active: () => !!editor.value?.isActive('italic'),
    },
    {
        label: 'Bulleted list',
        icon: List,
        run: () => editor.value?.chain().focus().toggleBulletList().run(),
        active: () => !!editor.value?.isActive('bulletList'),
    },
    {
        label: 'Numbered list',
        icon: ListOrdered,
        run: () => editor.value?.chain().focus().toggleOrderedList().run(),
        active: () => !!editor.value?.isActive('orderedList'),
    },
    {
        label: 'Quote',
        icon: Quote,
        run: () => editor.value?.chain().focus().toggleBlockquote().run(),
        active: () => !!editor.value?.isActive('blockquote'),
    },
    {
        label: 'Link',
        icon: LinkIcon,
        run: setLink,
        active: () => !!editor.value?.isActive('link'),
    },
];
</script>

<template>
    <div
        class="rounded-md border border-input shadow-xs focus-within:ring-[3px] focus-within:ring-ring/50"
        :class="disabled && 'opacity-70'"
    >
        <div
            v-if="!disabled"
            class="flex flex-wrap items-center gap-0.5 border-b px-1 py-1"
            role="toolbar"
            :aria-label="$t('Formatting')"
        >
            <button
                v-for="tool in tools"
                :key="tool.label"
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                :class="tool.active?.() && 'bg-accent text-foreground'"
                :aria-label="$t(tool.label)"
                :aria-pressed="tool.active?.() ?? false"
                :title="$t(tool.label)"
                @click="tool.run"
            >
                <component :is="tool.icon" class="size-4" />
            </button>
            <span class="mx-1 h-5 w-px bg-border" aria-hidden="true" />
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-label="$t('Undo')"
                :title="$t('Undo')"
                @click="editor?.chain().focus().undo().run()"
            >
                <Undo2 class="size-4 rtl:-scale-x-100" />
            </button>
            <button
                type="button"
                class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                :aria-label="$t('Redo')"
                :title="$t('Redo')"
                @click="editor?.chain().focus().redo().run()"
            >
                <Redo2 class="size-4 rtl:-scale-x-100" />
            </button>
        </div>
        <EditorContent :editor="editor" />
    </div>
</template>

<style>
.prose-editor h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-block: 0.75rem 0.5rem;
}

.prose-editor h3 {
    font-size: 1.075rem;
    font-weight: 600;
    margin-block: 0.5rem 0.25rem;
}

.prose-editor p {
    margin-block: 0.25rem;
}

.prose-editor ul,
.prose-editor ol {
    padding-inline-start: 1.5rem;
    margin-block: 0.25rem;
}

.prose-editor ul {
    list-style: disc;
}

.prose-editor ol {
    list-style: decimal;
}

.prose-editor blockquote {
    border-inline-start: 3px solid var(--border);
    padding-inline-start: 0.75rem;
    color: var(--muted-foreground);
}

.prose-editor a {
    text-decoration: underline;
    color: var(--primary);
}
</style>
