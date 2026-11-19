/**
 * Translations shared with PHP (LLD 10 §1): lang/{locale}.json for UI strings
 * plus lang/php_{locale}.json exported from lang/{locale}/*.php by
 * `php artisan i18n:export`. Loaded eagerly so pages never render untranslated.
 */
const files = import.meta.glob<{ default: Record<string, string> }>(
    '../../../lang/*.json',
    { eager: true },
);

export function resolveTranslations(lang: string): Record<string, string> {
    const json = files[`../../../lang/${lang}.json`]?.default ?? {};
    const php = files[`../../../lang/php_${lang}.json`]?.default ?? {};

    return { ...php, ...json };
}

export function currentLocale(): 'en' | 'ar' {
    return document.documentElement.lang === 'ar' ? 'ar' : 'en';
}

/**
 * Dates use the locale's month and day names but always Western digits (D-11).
 */
export function formatDate(
    value: string | Date | null | undefined,
    options: Intl.DateTimeFormatOptions = {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    },
): string {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(currentLocale(), {
        ...options,
        numberingSystem: 'latn',
    }).format(typeof value === 'string' ? new Date(value) : value);
}

/**
 * Numbers are always shown with Western digits (D-11).
 */
export function formatNumber(
    value: number,
    options: Intl.NumberFormatOptions = {},
): string {
    return new Intl.NumberFormat(currentLocale(), {
        ...options,
        numberingSystem: 'latn',
    }).format(value);
}

/**
 * Wraps text in a left-to-right isolate (U+2066 … U+2069). Use it for number
 * ranges, ratios and codes placed inside Arabic sentences: without it "1–10"
 * or "2 / 5" are reordered by the bidi algorithm and read backwards.
 */
export function isolateLtr(text: string | number): string {
    return `\u2066${text}\u2069`;
}
