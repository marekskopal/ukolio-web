// Up to two uppercase letters for an initials avatar: first + last word ("Jane Q. Doe" → "JD"),
// or the first two letters of a single word. Email-ish separators split too, so an email works as a fallback name.
export function initials(name: string): string {
    const parts = name
        .trim()
        .split(/[\s@.]+/)
        .filter((p) => p.length > 0);
    if (parts.length === 0) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}
