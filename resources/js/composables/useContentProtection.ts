import { onBeforeUnmount, onMounted, type Ref, watch } from 'vue';

/**
 * Discourages copying of the question bank in the student app: copying,
 * cutting, the context menu, dragging, text selection and printing are
 * blocked, as are the copy, select-all, save, print and view-source
 * shortcuts. Typing and pasting into form fields (notes, comments, search)
 * keep working.
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

function blockShortcuts(event: KeyboardEvent) {
    const key = event.key.toLowerCase();
    if ((event.ctrlKey || event.metaKey) && BLOCKED_KEYS.has(key) && !(isEditable(event.target) && key !== 'p' && key !== 's' && key !== 'u')) {
        event.preventDefault();
    }
    // Ctrl+Shift+I / J / C and F12 open the developer tools.
    if (event.key === 'F12' || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['i', 'j', 'c'].includes(key))) {
        event.preventDefault();
    }
}

const EVENTS = ['copy', 'cut', 'contextmenu', 'dragstart', 'selectstart'] as const;

export function useContentProtection(enabled: Ref<boolean>) {
    function attach() {
        EVENTS.forEach((name) => document.addEventListener(name, block, true));
        document.addEventListener('keydown', blockShortcuts, true);
        document.documentElement.classList.add('content-protected');
    }
    function detach() {
        EVENTS.forEach((name) => document.removeEventListener(name, block, true));
        document.removeEventListener('keydown', blockShortcuts, true);
        document.documentElement.classList.remove('content-protected');
    }
    onMounted(() => {
        if (enabled.value) attach();
    });
    watch(enabled, (on) => (on ? attach() : detach()));
    onBeforeUnmount(detach);
}
