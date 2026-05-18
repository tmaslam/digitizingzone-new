@php
    $editorId = $id ?? ('editor-'.uniqid());
    $editorName = $name ?? 'body';
    $editorLabel = $label ?? 'Message';
    $editorValue = old($editorName, $value ?? '');
    $editorPlaceholder = $placeholder ?? '';
    $editorHelp = $help ?? null;
@endphp

<div class="field rich-text-field" style="{{ $style ?? '' }}">
    <label for="{{ $editorId }}_textarea">{{ $editorLabel }}</label>
    <div class="rich-text-editor" data-rich-text-editor>
        <div class="rich-text-toolbar" role="toolbar" aria-label="{{ $editorLabel }} formatting tools">
            <button type="button" class="badge" data-editor-command="bold">Bold</button>
            <button type="button" class="badge" data-editor-command="italic">Italic</button>
            <button type="button" class="badge" data-editor-command="underline">Underline</button>
            <button type="button" class="badge" data-editor-command="insertUnorderedList">Bullets</button>
            <button type="button" class="badge" data-editor-command="insertOrderedList">Numbering</button>
            <button type="button" class="badge" data-editor-command="createLink">Link</button>
            <button type="button" class="badge" data-editor-command="removeFormat">Clear Style</button>
        </div>
        <div
            class="rich-text-surface"
            contenteditable="true"
            data-editor-surface
            data-placeholder="{{ $editorPlaceholder }}"
            aria-label="{{ $editorLabel }}"
        >{!! $editorValue !!}</div>
        <textarea
            id="{{ $editorId }}_textarea"
            name="{{ $editorName }}"
            rows="{{ $rows ?? 10 }}"
            data-editor-textarea
            hidden
        >{{ $editorValue }}</textarea>
    </div>
    @if ($editorHelp)
        <span class="muted rich-text-help">{{ $editorHelp }}</span>
    @endif
</div>

@once
    <style>
        .rich-text-editor {
            border: 1px solid rgba(24, 34, 45, 0.16);
            border-radius: 18px;
            background: rgba(255,255,255,0.86);
            overflow: hidden;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.72);
        }
        .rich-text-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 12px;
            border-bottom: 1px solid rgba(24, 34, 45, 0.1);
            background: linear-gradient(180deg, rgba(247,250,252,0.95), rgba(239,244,248,0.86));
        }
        .rich-text-toolbar .badge {
            cursor: pointer;
            background: rgba(255,255,255,0.82);
        }
        .rich-text-surface {
            min-height: 240px;
            padding: 16px 18px;
            outline: none;
            line-height: 1.7;
            color: var(--ink);
            white-space: normal;
        }
        .rich-text-surface:empty::before {
            content: attr(data-placeholder);
            color: var(--muted);
        }
        .rich-text-surface a {
            color: var(--accent);
            text-decoration: underline;
        }
        .rich-text-surface p {
            margin: 0 0 12px;
        }
        .rich-text-surface ul,
        .rich-text-surface ol {
            padding-left: 22px;
        }
        .rich-text-help {
            display: block;
            margin-top: 8px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-rich-text-editor]').forEach(function (editor) {
                if (editor.dataset.editorInitialized === 'true') {
                    return;
                }

                const surface = editor.querySelector('[data-editor-surface]');
                const textarea = editor.querySelector('[data-editor-textarea]');

                if (!surface || !textarea) {
                    return;
                }

                const sync = function () {
                    textarea.value = surface.innerHTML.trim();
                };

                editor.querySelectorAll('[data-editor-command]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const command = button.dataset.editorCommand;
                        surface.focus();

                        if (command === 'createLink') {
                            const url = window.prompt('Enter the full link URL');
                            if (!url) {
                                return;
                            }
                            document.execCommand('createLink', false, url);
                        } else {
                            document.execCommand(command, false, null);
                        }

                        sync();
                    });
                });

                surface.addEventListener('input', sync);
                surface.closest('form')?.addEventListener('submit', sync);
                sync();
                editor.dataset.editorInitialized = 'true';
            });
        });
    </script>
@endonce
