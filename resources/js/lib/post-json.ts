/**
 * POST to a JSON endpoint of the app with Laravel's CSRF protection: the
 * XSRF-TOKEN cookie is sent back in the X-XSRF-TOKEN header.
 */
export async function postJson<T>(url: string, body: Record<string, unknown> = {}): Promise<{ ok: boolean; status: number; data: T }> {
    const token = decodeURIComponent(
        document.cookie
            .split('; ')
            .find((part) => part.startsWith('XSRF-TOKEN='))
            ?.split('=')[1] ?? '',
    );
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': token },
        body: JSON.stringify(body),
    });
    let data = {} as T;
    try {
        data = (await response.json()) as T;
    } catch {
        // An empty or non-JSON body leaves data empty.
    }
    return { ok: response.ok, status: response.status, data };
}
