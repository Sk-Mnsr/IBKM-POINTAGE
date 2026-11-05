export function xsrfToken(): string {
    const cookie = document.cookie.split('; ').find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

/**
 * JSON POST that carries the session cookie and CSRF token, like Inertia requests do.
 */
export function postJson(url: string, body: Record<string, unknown>): Promise<Response> {
    return fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: JSON.stringify(body),
    });
}

/**
 * First message of a Laravel 422 response.
 */
export async function firstValidationError(response: Response, fallback: string): Promise<{ field: string | null; message: string }> {
    try {
        const data: { message?: string; errors?: Record<string, string[]> } = await response.json();
        const [field, messages] = Object.entries(data.errors ?? {})[0] ?? [null, []];

        return { field, message: messages[0] ?? data.message ?? fallback };
    } catch {
        return { field: null, message: fallback };
    }
}
