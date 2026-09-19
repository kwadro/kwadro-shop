(() => {
    const EDITOR_SELECTOR = 'form textarea:not([readonly]):not([disabled]):not([data-no-html-editor])';
    const editorInstances = new Map();

    const editorConfig = {
        height: 320,
        theme: 'default',
        toolbarAdaptive: false,
        showXPathInStatusbar: false,
        askBeforePasteHTML: false,
        defaultActionOnPaste: 'insert_as_html',
        buttons: [
            'source',
            '|',
            'bold',
            'italic',
            'underline',
            'strikethrough',
            '|',
            'ul',
            'ol',
            '|',
            'font',
            'fontsize',
            'brush',
            'paragraph',
            '|',
            'link',
            'table',
            'hr',
            '|',
            'align',
            'undo',
            'redo',
        ],
    };

    const ensureTextareaId = (textarea) => {
        if (textarea.id) {
            return textarea.id;
        }

        const generatedId = `ea_html_editor_${Math.random().toString(36).slice(2, 11)}`;
        textarea.id = generatedId;

        return generatedId;
    };

    const destroyEditor = (textareaId) => {
        const instance = editorInstances.get(textareaId);
        if (instance && typeof instance.destruct === 'function') {
            instance.destruct();
        }

        editorInstances.delete(textareaId);
    };

    const waitForJodit = (callback, attemptsLeft = 50) => {
        if (typeof Jodit !== 'undefined' && typeof Jodit.make === 'function') {
            callback();
            return;
        }

        if (attemptsLeft <= 0) {
            console.warn('Jodit editor failed to load.');
            return;
        }

        window.setTimeout(() => waitForJodit(callback, attemptsLeft - 1), 100);
    };

    const initHtmlEditors = () => {
        document.querySelectorAll(EDITOR_SELECTOR).forEach((textarea) => {
            const textareaId = ensureTextareaId(textarea);

            if (editorInstances.has(textareaId)) {
                return;
            }

            if (textarea.closest('.jodit-container')) {
                return;
            }

            const instance = Jodit.make(textarea, editorConfig);
            editorInstances.set(textareaId, instance);
        });
    };

    const scheduleInit = () => {
        window.clearTimeout(window.__eaHtmlEditorTimer);
        window.__eaHtmlEditorTimer = window.setTimeout(() => waitForJodit(initHtmlEditors), 50);
    };

    document.addEventListener('DOMContentLoaded', scheduleInit);
    document.addEventListener('turbo:load', scheduleInit);
    document.addEventListener('turbo:render', scheduleInit);
    document.addEventListener('turbo:frame-load', scheduleInit);

    if (document.body) {
        const observer = new MutationObserver(scheduleInit);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    window.addEventListener('beforeunload', () => {
        editorInstances.forEach((_, textareaId) => destroyEditor(textareaId));
    });
})();
