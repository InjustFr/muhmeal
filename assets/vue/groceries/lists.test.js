import { describe, expect, it } from 'vitest';
import { boughtLine, boughtMax, boughtStep, groupByLocation, prepareRows, stockAfterBuying } from './lists.js';

const ingredient = (name, stock, extra = {}) => ({ id: name.toLowerCase(), name, stock, location: 'pantry', unit: 'piece', suggested: false, ...extra });

describe('prepareRows', () => {
    it('keeps the suggested ingredients and the added ones', () => {
        const rows = prepareRows([ingredient('Milk', 3, { suggested: true }), ingredient('Rice', 24), ingredient('Salt', 24)], ['salt']);

        expect(rows.map((row) => row.name)).toEqual(['Milk', 'Salt']);
    });

    it('puts what is out first, then sorts by name', () => {
        const rows = prepareRows([
            ingredient('Cheddar', 3, { suggested: true }),
            ingredient('Yoghurt', 0, { suggested: true }),
            ingredient('Apples', 6, { suggested: true }),
            ingredient('Zucchini', 0, { suggested: true }),
        ], []);

        expect(rows.map((row) => row.name)).toEqual(['Yoghurt', 'Zucchini', 'Apples', 'Cheddar']);
    });

    it('does not change the list it is given', () => {
        const all = [ingredient('B', 0, { suggested: true }), ingredient('A', 0, { suggested: true })];

        prepareRows(all, []);

        expect(all.map((row) => row.name)).toEqual(['B', 'A']);
    });
});

describe('groupByLocation', () => {
    it('groups by place in the usual order and drops empty places', () => {
        const groups = groupByLocation([
            { name: 'Peas', location: 'freezer', ticked: false },
            { name: 'Milk', location: 'fridge', ticked: false },
        ]);

        expect(groups.map((group) => group.location)).toEqual(['fridge', 'freezer']);
    });

    it('sinks the ticked rows to the bottom of their group', () => {
        const [group] = groupByLocation([
            { name: 'Apples', location: 'fridge', ticked: true },
            { name: 'Milk', location: 'fridge', ticked: false },
            { name: 'Butter', location: 'fridge', ticked: false },
            { name: 'Eggs', location: 'fridge', ticked: true },
        ]);

        expect(group.rows.map((row) => row.name)).toEqual(['Butter', 'Milk', 'Apples', 'Eggs']);
    });

    it('sorts rows that have no ticked flag by name', () => {
        const [group] = groupByLocation([{ name: 'Rice', location: 'pantry' }, { name: 'Flour', location: 'pantry' }]);

        expect(group.rows.map((row) => row.name)).toEqual(['Flour', 'Rice']);
    });
});

describe('boughtLine', () => {
    it('starts at one unit', () => {
        expect(boughtLine({ ingredientId: 'a', name: 'Milk', unit: 'carton', location: 'fridge', stock: 3 })).toEqual({ ingredientId: 'a', name: 'Milk', unit: 'carton', location: 'fridge', stock: 3, amount: 12 });
    });

    it('never starts above 999 units in stock', () => {
        expect(boughtLine({ id: 'c', name: 'Salt', unit: 'box', location: 'pantry', stock: 11988 }).amount).toBe(0);
    });

    it('takes the id of an ingredient as well as the id of a list item', () => {
        expect(boughtLine({ id: 'b', name: 'Chives', unit: 'piece', location: 'fridge', stock: 0 }).ingredientId).toBe('b');
    });
});

describe('stockAfterBuying', () => {
    it('adds what was bought to the stock', () => {
        expect(stockAfterBuying({ stock: 3, amount: 12 })).toBe(15);
    });

    it('stops at 999 units', () => {
        expect(stockAfterBuying({ stock: 11988, amount: 12 })).toBe(11988);
    });
});

describe('boughtStep and boughtMax', () => {
    it('steps by one whole unit, containers included', () => {
        expect([boughtStep({ unit: 'piece' }), boughtStep({ unit: 'jar' })]).toEqual([12, 12]);
    });

    it('leaves room up to 999 units', () => {
        expect(boughtMax({ stock: 11976 })).toBe(12);
    });
});
