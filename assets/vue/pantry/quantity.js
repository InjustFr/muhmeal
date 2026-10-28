export const TWELFTHS_PER_UNIT = 12;
export const MAX_TWELFTHS = 999 * TWELFTHS_PER_UNIT;
export const FRACTIONS = [0, 3, 4, 6, 8, 9];

const GLYPHS = { 2: '⅙', 3: '¼', 4: '⅓', 6: '½', 8: '⅔', 9: '¾', 10: '⅚' };

export function formatQuantity(twelfths, language = 'en') {
    const whole = Math.floor(twelfths / TWELFTHS_PER_UNIT);
    const rest = twelfths % TWELFTHS_PER_UNIT;
    if (rest === 0) return String(whole);
    if (GLYPHS[rest]) return `${whole > 0 ? whole : ''}${GLYPHS[rest]}`;
    return `≈${new Intl.NumberFormat(language, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(twelfths / TWELFTHS_PER_UNIT)}`;
}

export function unitForm(twelfths, language = 'en') {
    const singular = language === 'fr' ? twelfths < 2 * TWELFTHS_PER_UNIT : twelfths > 0 && twelfths <= TWELFTHS_PER_UNIT;
    return singular ? 'one' : 'other';
}

export function describeQuantity(twelfths, unit, language, translate) {
    const amount = formatQuantity(twelfths, language);

    return unit === 'piece' ? amount : `${amount} ${translate(`units.${unit}.${unitForm(twelfths, language)}`)}`;
}

export function defaultLowAt(unit) {
    return unit === 'piece' ? TWELFTHS_PER_UNIT : 3;
}

export function splitQuantity(twelfths) {
    return { whole: twelfths - (twelfths % TWELFTHS_PER_UNIT), fraction: twelfths % TWELFTHS_PER_UNIT };
}
