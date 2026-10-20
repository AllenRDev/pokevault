/**
 * The CSRF token rendered into the root layout.
 *
 * Native `fetch` does not read the XSRF cookie the way axios does, so every
 * hand-rolled request that mutates state has to send the token explicitly.
 */
export function csrfToken(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}
