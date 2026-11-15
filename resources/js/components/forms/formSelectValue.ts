import type { AcceptableValue } from 'reka-ui';

/**
 * The kit's Select reserves '' and cannot hold null or undefined, yet our
 * filters use undefined for "All" and forms use null for "None". FormSelect
 * stores those three values under sentinels and restores them on the way out.
 */
const NULL = '__null__';
const EMPTY = '__empty__';
const UNDEFINED = '__undefined__';

export type FormSelectValue = AcceptableValue | undefined;

export function toInner(
    value: FormSelectValue,
): Exclude<AcceptableValue, null> {
    if (value === undefined) {
        return UNDEFINED;
    }

    if (value === null) {
        return NULL;
    }

    return value === '' ? EMPTY : value;
}

export function toOuter(value: AcceptableValue): FormSelectValue {
    switch (value) {
        case UNDEFINED:
            return undefined;
        case NULL:
            return null;
        case EMPTY:
            return '';
        default:
            return value;
    }
}
