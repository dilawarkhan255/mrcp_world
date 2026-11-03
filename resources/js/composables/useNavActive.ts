import type { NavNode } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The current path without the query string or trailing slash. */
function normalise(url: string): string {
    const path = url.split('?')[0].replace(/\/+$/, '');

    return path === '' ? '/' : path;
}

/**
 * Active state for sidebar nodes. A node is active on its own href (exact match),
 * or on any of its `match` paths (the path equals it or lies below it).
 */
export function useNavActive() {
    const page = usePage();
    const path = computed(() => normalise(page.url));

    function isActive(node: NavNode): boolean {
        if (node.href && path.value === normalise(node.href)) {
            return true;
        }

        return (node.match ?? []).some((match) => {
            const base = normalise(match);

            return path.value === base || path.value.startsWith(base === '/' ? '/' : `${base}/`);
        });
    }

    /** True when the node or any of its descendants is active. */
    function containsActive(node: NavNode): boolean {
        return isActive(node) || (node.children ?? []).some(containsActive);
    }

    return { path, isActive, containsActive };
}
