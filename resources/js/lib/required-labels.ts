/**
 * A red asterisk on the label of every required field, in every form of the
 * app (auth, settings, contact, practice…), without each form adding it by
 * hand. A field counts as required when it has the `required` attribute; its
 * label is the <label for="id"> or the <label> that wraps it. The mark goes
 * right after the label's own text. New forms (dialogs, page visits) are
 * picked up by a MutationObserver.
 */
const MARK = 'mw-required-mark';
const FIELDS = 'input[required]:not([type=hidden]), textarea[required], select[required]';

function labelFor(field: HTMLElement): HTMLLabelElement | null {
    if (field.id) {
        const byFor = document.querySelector<HTMLLabelElement>(`label[for="${CSS.escape(field.id)}"]`);
        if (byFor) return byFor;
    }

    return field.closest('label');
}

export function markRequiredLabels(root: ParentNode = document): void {
    root.querySelectorAll<HTMLElement>(FIELDS).forEach((field) => {
        // Radio groups and checkboxes keep their own wording.
        if (field instanceof HTMLInputElement && ['radio', 'checkbox'].includes(field.type)) return;
        const label = labelFor(field);
        if (!label || label.querySelector(`.${MARK}`)) return;

        const star = document.createElement('span');
        star.className = MARK;
        star.textContent = '*';
        star.setAttribute('aria-hidden', 'true');

        // After the label's own words: its first non-empty text node, or before the control it wraps.
        const text = Array.from(label.childNodes).find((node) => node.nodeType === Node.TEXT_NODE && node.textContent?.trim());
        if (text && label.contains(field)) {
            // A label that wraps its field is often a grid or flex box: keep the words and the star in one box,
            // so the star sits next to the words instead of on its own row.
            const words = document.createElement('span');
            words.className = 'mw-required-label';
            text.parentNode?.insertBefore(words, text);
            words.append(text, star);
        } else if (text) {
            text.parentNode?.insertBefore(star, text.nextSibling);
        } else {
            const control = label.querySelector('input, textarea, select');
            if (control && control.parentNode === label) {
                label.insertBefore(star, control);
            } else {
                label.appendChild(star);
            }
        }
    });
}

export function watchRequiredLabels(): void {
    markRequiredLabels();
    let queued = false;
    new MutationObserver(() => {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            markRequiredLabels();
        });
    }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['required'] });
}
