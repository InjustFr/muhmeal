import { describe, expect, it } from 'vitest';
import { isShort, lineFromIngredient, linesFromRecipe, stepFor, stockAfter, ticks, usedOf, withIngredient } from './lines.js';

const ingredient = { id: 'a', name: 'Pasta', unit: 'pack', manual: false, stock: 24 };

describe('stepFor', () => {
    it('steps by a whole piece and by a quarter of any container', () => {
        expect(stepFor('piece')).toBe(12);
        expect(stepFor('pack')).toBe(3);
        expect(stepFor('bottle')).toBe(3);
    });
});

describe('lineFromIngredient', () => {
    it('starts at one whole unit and keeps what the preview needs', () => {
        expect(lineFromIngredient(ingredient)).toEqual({ ingredientId: 'a', name: 'Pasta', unit: 'pack', manual: false, stock: 24, amount: 12 });
    });

    it('takes an explicit amount', () => {
        expect(lineFromIngredient(ingredient, 3).amount).toBe(3);
    });
});

describe('linesFromRecipe', () => {
    const recipe = {
        lines: [
            { ingredientId: 'a', name: 'Pasta', unit: 'pack', manual: false, amount: 12, stock: 24, covered: true },
            { ingredientId: 'b', name: 'Olive oil', unit: 'bottle', manual: true, amount: 3, stock: 10, covered: true },
            { ingredientId: 'c', name: 'Salt', unit: 'box', manual: false, amount: null, stock: 12, covered: true },
            { ingredientId: 'd', name: 'Salt substitute', unit: 'box', manual: true, amount: null, stock: 12, covered: true },
        ],
    };

    it('prefills the recipe amount of the automatic lines that have one', () => {
        expect(linesFromRecipe(recipe)[0]).toEqual({ ingredientId: 'a', name: 'Pasta', unit: 'pack', manual: false, stock: 24, amount: 12 });
    });

    it('starts the hand-tracked and the amount-less lines at zero', () => {
        expect(linesFromRecipe(recipe).map((line) => line.amount)).toEqual([12, 0, 0, 0]);
    });
});

describe('withIngredient', () => {
    it('appends a new ingredient', () => {
        const lines = withIngredient([], ingredient);
        expect(lines.map((line) => line.ingredientId)).toEqual(['a']);
    });

    it('lists an ingredient once', () => {
        const lines = withIngredient([], ingredient);
        expect(withIngredient(lines, ingredient)).toBe(lines);
    });
});

describe('usedOf', () => {
    it('sends only what is above zero', () => {
        const lines = [{ ingredientId: 'a', amount: 12 }, { ingredientId: 'b', amount: 0 }, { ingredientId: 'c', amount: 3 }];
        expect(usedOf(lines)).toEqual([{ ingredientId: 'a', amount: 12 }, { ingredientId: 'c', amount: 3 }]);
    });
});

describe('stockAfter and isShort', () => {
    it('subtracts the amount from the stock', () => {
        expect(stockAfter({ stock: 24, amount: 12 })).toBe(12);
        expect(isShort({ stock: 24, amount: 12 })).toBe(false);
    });

    it('stops at zero and flags a shortfall', () => {
        expect(stockAfter({ stock: 6, amount: 30 })).toBe(0);
        expect(isShort({ stock: 6, amount: 30 })).toBe(true);
    });

    it('is not short when the stock exactly matches', () => {
        expect(isShort({ stock: 12, amount: 12 })).toBe(false);
    });
});

describe('ticks', () => {
    it('counts down in even steps from the old stock to the new one', () => {
        expect(ticks(24, 12, 4)).toEqual([24, 21, 18, 15, 12]);
    });

    it('counts up too, and rounds to whole twelfths', () => {
        expect(ticks(0, 12, 5)).toEqual([0, 2, 5, 7, 10, 12]);
    });

    it('stays put when nothing changes', () => {
        expect(ticks(6, 6, 3)).toEqual([6, 6, 6, 6]);
    });
});
