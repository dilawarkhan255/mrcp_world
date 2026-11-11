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
    // Ctrl+Shift+I / J / C and F12 open the developer tools; Ctrl+Shift+S is the Firefox screenshot tool.
    if (event.key === 'F12' || ((event.ctrlKey || event.metaKey) && event.shiftKey && ['i', 'j', 'c', 's'].includes(key))) {
        event.preventDefault();
    }
    if (event.key === 'PrintScreen') {
        event.preventDefault();
    }
}

/*
 * Screenshot shield: the page is hidden behind a notice while a screenshot is
 * likely — on Print Screen, when the Windows / Command key is pressed (Win+
 * Shift+S, Cmd+Shift+4…), and while the window is not in front (a snipping
 * or screen-recording tool has the focus). Print Screen also replaces the
 * clipboard, so the captured picture is not kept there.
 */
let shieldTimer: number | undefined;

function shieldOn(ms?: number) {
    document.documentElement.classList.add('mw-shield');
    window.clearTimeout(shieldTimer);
    if (ms) {
        shieldTimer = window.setTimeout(() => {
            if (document.hasFocus() && document.visibilityState === 'visible') document.documentElement.classList.remove('mw-shield');
        }, ms);
    }
}

function shieldOff() {
    window.clearTimeout(shieldTimer);
    shieldTimer = window.setTimeout(() => document.documentElement.classList.remove('mw-shield'), 300);
}

function screenshotKeyDown(event: KeyboardEvent) {
    if (event.key === 'PrintScreen' || event.key === 'Meta' || event.key === 'OS' || (event.metaKey && event.shiftKey)) shieldOn(1500);
}

function screenshotKeyUp(event: KeyboardEvent) {
    if (event.key === 'PrintScreen') {
        shieldOn(2500);
        navigator.clipboard?.writeText('Screenshots are not allowed on this site.').catch(() => undefined);
    }
}

function onBlur() {
    shieldOn();
}

function onVisibility() {
    if (document.visibilityState === 'hidden') shieldOn();
    else if (document.hasFocus()) shieldOff();
}

const EVENTS = ['contextmenu', 'dragstart', 'selectstart'] as const;
const CLIPBOARD_EVENTS = ['copy', 'cut'] as const;

export function useContentProtection(enabled: Ref<boolean>) {
    function attach() {
        EVENTS.forEach((name) => document.addEventListener(name, block, true));
        CLIPBOARD_EVENTS.forEach((name) => document.addEventListener(name, blockClipboard, true));
        document.addEventListener('keydown', blockShortcuts, true);
        document.addEventListener('keydown', screenshotKeyDown, true);
        document.addEventListener('keyup', screenshotKeyUp, true);
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('blur', onBlur);
        window.addEventListener('focus', shieldOff);
        document.documentElement.classList.add('content-protected');
    }
    function detach() {
        EVENTS.forEach((name) => document.removeEventListener(name, block, true));
        CLIPBOARD_EVENTS.forEach((name) => document.removeEventListener(name, blockClipboard, true));
        document.removeEventListener('keydown', blockShortcuts, true);
        document.removeEventListener('keydown', screenshotKeyDown, true);
        document.removeEventListener('keyup', screenshotKeyUp, true);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('blur', onBlur);
        window.removeEventListener('focus', shieldOff);
        document.documentElement.classList.remove('content-protected', 'mw-shield');
    }
    onMounted(() => {
        if (enabled.value) attach();
    });
    watch(enabled, (on) => (on ? attach() : detach()));
    onBeforeUnmount(detach);
}
