import { onBeforeUnmount, onMounted, type Ref, watch } from 'vue';

/**
 * Discourages copying from every page of the web app (landing, login, the
 * student app, legal pages): copying, cutting, the context menu (so no
 * "Copy link address"), dragging text, links or images, text selection and
 * printing are blocked, as are the copy, cut, select-all, save, print and
 * view-source shortcuts. A copy that still gets through leaves the clipboard
 * empty. Typing, and copying and pasting inside form fields (notes,
 * comments, search, passwords), keep working.
 *
 * This is a deterrent, not a guarantee: a browser cannot stop screenshots,
 * a phone camera or the developer tools. The watermark makes a leaked
 * screenshot traceable to the account.
 */
const BLOCKED_KEYS = new Set(['c', 'x', 'a', 's', 'p', 'u']);

function isEditable(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) return false;
    return target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);
}

function block(event: Event) {
    if (!isEditable(event.target)) event.preventDefault();
}

/** Copy and cut outside a form field: block it and leave nothing on the clipboard. */
function blockClipboard(event: ClipboardEvent) {
    if (isEditable(event.target)) return;
    event.preventDefault();
    event.clipboardData?.setData('text/plain', '');
    event.clipboardData?.setData('text/html', '');
}

function blockShortcuts(event: KeyboardEvent) {
    const key = event.key.toLowerCase();
    if ((event.ctrlKey || event.metaKey) && BLOCKED_KEYS.has(key) && !(isEditable(event.target) && key !== 'p' && key !== 's' && key !== 'u')) {
        event.preventDefault();
    }
    // Ctrl+Insert copies and Shift+Delete cuts, like Ctrl+C and Ctrl+X.
    if (!isEditable(event.target) && (((event.ctrlKey || event.metaKey) && event.key === 'Insert') || (event.shiftKey && event.key === 'Delete'))) {
        event.preventDefault();
    }
    // Ctrl+Shift+I / J / C and F12 open the developer tools.
    if (event.key === 'F12' || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['i', 'j', 'c'].includes(key))) {
        event.preventDefault();
    }
}

const EVENTS = ['contextmenu', 'dragstart', 'selectstart'] as const;
const CLIPBOARD_EVENTS = ['copy', 'cut'] as const;

export function useContentProtection(enabled: Ref<boolean>) {
    function attach() {
        EVENTS.forEach((name) => document.addEventListener(name, block, true));
        CLIPBOARD_EVENTS.forEach((name) => document.addEventListener(name, blockClipboard, true));
        document.addEventListener('keydown', blockShortcuts, true);
        document.documentElement.classList.add('content-protected');
    }
    function detach() {
        EVENTS.forEach((name) => document.removeEventListener(name, block, true));
        CLIPBOARD_EVENTS.forEach((name) => document.removeEventListener(name, blockClipboard, true));
        document.removeEventListener('keydown', blockShortcuts, true);
        document.documentElement.classList.remove('content-protected');
    }
    onMounted(() => {
        if (enabled.value) attach();
    });
    watch(enabled, (on) => (on ? attach() : detach()));
    onBeforeUnmount(detach);
}
