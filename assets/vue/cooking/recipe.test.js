import { describe, expect, it } from 'vitest';
import { formFromView, isComplete, lacking, lineAmount, lineFromView, newLine, payloadOf } from './recipe.js';

const ingredient = { id: 'a', name: 'Pasta', unit: 'pack' };

const view = {
    id: 'r',
    name: 'Seitan',
    makes: 'prep',
    lines: [
        { ingredientId: 'a', name: 'Gluten', unit: 'bag', manual: false, amount: 15, stock: 24, covered: true },
        { ingredientId: 'b', name: 'Spices', unit: 'jar', manual: true, amount: null, stock: 6, covered: true },
    ],
    output: { ingredientId: 'c', name: 'Seitan', unit: 'bag', yield: 18, stock: 0 },
    missing: ['b'],
    makeable: false,
};

describe('newLine', () => {
    it('starts at one whole unit', () => {
        expect(newLine(ingredient)).toEqual({ ingredientId: 'a', name: 'Pasta', unit: 'pack', some: false, whole: 12, fraction: 0 });
    });
});

describe('lineFromView', () => {
    it('splits a stored amount into whole units and a fraction', () => {
        expect(lineFromView(view.lines[0])).toMatchObject({ some: false, whole: 12, fraction: 3 });
    });

    it('turns an amount-less line into a some line', () => {
        expect(lineFromView(view.lines[1])).toMatchObject({ some: true, whole: 12, fraction: 0 });
    });
});

describe('lineAmount', () => {
    it('adds the whole units and the fraction', () => {
        expect(lineAmount({ some: false, whole: 24, fraction: 9 })).toBe(33);
    });

    it('has no amount for a some line, whatever the stepper holds', () => {
        expect(lineAmount({ some: true, whole: 24, fraction: 9 })).toBeNull();
    });
});

describe('formFromView and payloadOf', () => {
    it('round-trips a prep recipe', () => {
        expect(payloadOf(formFromView(view))).toEqual({
            name: 'Seitan',
            lines: [{ ingredientId: 'a', amount: 15 }, { ingredientId: 'b', amount: null }],
            output: { ingredientId: 'c', yield: 18 },
        });
    });

    it('sends no output for a meal, even when one was picked before switching', () => {
        const form = { ...formFromView(view), kind: 'meal' };
        expect(payloadOf(form).output).toBeNull();
    });

    it('trims the name', () => {
        expect(payloadOf({ ...formFromView(view), name: '  Seitan ' }).name).toBe('Seitan');
    });
});

describe('isComplete', () => {
    const meal = { name: 'Soup', kind: 'meal', lines: [newLine(ingredient)], output: null, whole: 12, fraction: 0 };

    it('accepts a named recipe with a line', () => {
        expect(isComplete(meal)).toBe(true);
    });

    it('needs a name', () => {
        expect(isComplete({ ...meal, name: '   ' })).toBe(false);
    });

    it('needs a line', () => {
        expect(isComplete({ ...meal, lines: [] })).toBe(false);
    });

    it('refuses an amount of zero but accepts some', () => {
        expect(isComplete({ ...meal, lines: [{ ...newLine(ingredient), whole: 0, fraction: 0 }] })).toBe(false);
        expect(isComplete({ ...meal, lines: [{ ...newLine(ingredient), some: true, whole: 0, fraction: 0 }] })).toBe(true);
    });

    it('needs an output and a yield for a prep', () => {
        const prep = { ...meal, kind: 'prep' };
        expect(isComplete(prep)).toBe(false);
        expect(isComplete({ ...prep, output: { id: 'c', name: 'Seitan', unit: 'bag' } })).toBe(true);
        expect(isComplete({ ...prep, output: { id: 'c', name: 'Seitan', unit: 'bag' }, whole: 0 })).toBe(false);
    });
});

describe('lacking', () => {
    it('keeps the lines whose ingredient is missing', () => {
        expect(lacking(view).map((line) => line.name)).toEqual(['Spices']);
    });
});
