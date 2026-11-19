import { describe, expect, it } from 'vitest';
import { nameMatches } from './search.js';

describe('nameMatches', () => {
    it('ignores case and accents both ways', () => {
        expect(nameMatches('Crème fraîche', 'creme')).toBe(true);
        expect(nameMatches('Pâté', 'PATE')).toBe(true);
        expect(nameMatches('Citron', 'cîtron')).toBe(true);
        expect(nameMatches('Façon', 'c')).toBe(true);
    });

    it('spells out ligatures', () => {
        expect(nameMatches('Œufs', 'oeuf')).toBe(true);
    });

    it('trims the query and rejects other names', () => {
        expect(nameMatches('Lait', '  la ')).toBe(true);
        expect(nameMatches('Lait', 'beurre')).toBe(false);
    });
});
