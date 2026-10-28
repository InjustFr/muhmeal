import { describe, expect, it } from 'vitest';
import { defaultLowAt, describeQuantity, formatQuantity, FRACTIONS, MAX_TWELFTHS, splitQuantity, unitForm } from './quantity.js';

describe('formatQuantity', () => {
    it.each([
        [0, '0'],
        [12, '1'],
        [36, '3'],
        [11988, '999'],
    ])('shows %i twelfths as the whole number %s', (twelfths, text) => {
        expect(formatQuantity(twelfths)).toBe(text);
    });

    it.each([
        [2, '⅙'],
        [3, '¼'],
        [4, '⅓'],
        [6, '½'],
        [8, '⅔'],
        [9, '¾'],
        [10, '⅚'],
    ])('shows %i twelfths as the glyph %s', (twelfths, glyph) => {
        expect(formatQuantity(twelfths)).toBe(glyph);
    });

    it('puts the glyph right after the whole number', () => {
        expect(formatQuantity(28)).toBe('2⅓');
        expect(formatQuantity(15)).toBe('1¼');
        expect(formatQuantity(118)).toBe('9⅚');
    });

    it.each([
        [1, '≈0.1'],
        [5, '≈0.4'],
        [7, '≈0.6'],
        [11, '≈0.9'],
        [29, '≈2.4'],
    ])('falls back on an approximation for %i twelfths', (twelfths, text) => {
        expect(formatQuantity(twelfths)).toBe(text);
    });

    it('uses the decimal separator of the language', () => {
        expect(formatQuantity(5, 'fr')).toBe('≈0,4');
        expect(formatQuantity(5, 'en')).toBe('≈0.4');
    });

    it('has a glyph or a whole number for every chip', () => {
        expect(FRACTIONS.map((twelfths) => formatQuantity(twelfths))).toEqual(['0', '¼', '⅓', '½', '⅔', '¾']);
    });

    it('covers the whole range', () => {
        expect(formatQuantity(MAX_TWELFTHS)).toBe('999');
    });
});

describe('unitForm', () => {
    it.each([
        [0, 'other'],
        [3, 'one'],
        [12, 'one'],
        [13, 'other'],
        [24, 'other'],
    ])('takes the form of %i twelfths in English: %s', (twelfths, form) => {
        expect(unitForm(twelfths, 'en')).toBe(form);
    });

    it.each([
        [0, 'one'],
        [12, 'one'],
        [18, 'one'],
        [23, 'one'],
        [24, 'other'],
        [36, 'other'],
    ])('takes the form of %i twelfths in French: %s', (twelfths, form) => {
        expect(unitForm(twelfths, 'fr')).toBe(form);
    });
});

describe('describeQuantity', () => {
    const translate = (key) => key;

    it('shows pieces without a unit label', () => {
        expect(describeQuantity(36, 'piece', 'en', translate)).toBe('3');
        expect(describeQuantity(9, 'piece', 'fr', translate)).toBe('¾');
    });

    it('adds the unit label of the right form to containers', () => {
        expect(describeQuantity(9, 'carton', 'en', translate)).toBe('¾ units.carton.one');
        expect(describeQuantity(28, 'bag', 'en', translate)).toBe('2⅓ units.bag.other');
        expect(describeQuantity(28, 'bag', 'fr', translate)).toBe('2⅓ units.bag.other');
        expect(describeQuantity(18, 'bag', 'fr', translate)).toBe('1½ units.bag.one');
    });
});

describe('defaultLowAt', () => {
    it('is one piece or a quarter of a container', () => {
        expect(defaultLowAt('piece')).toBe(12);
        expect(defaultLowAt('bottle')).toBe(3);
    });
});

describe('splitQuantity', () => {
    it('splits whole units from the fraction', () => {
        expect(splitQuantity(33)).toEqual({ whole: 24, fraction: 9 });
        expect(splitQuantity(24)).toEqual({ whole: 24, fraction: 0 });
        expect(splitQuantity(0)).toEqual({ whole: 0, fraction: 0 });
    });
});
