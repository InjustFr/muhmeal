import { describe, expect, it } from 'vitest';
import { MEAL_TYPES, mealTypeAt } from './mealType.js';

const at = (hour, minute = 0) => new Date(2026, 9, 6, hour, minute);

describe('mealTypeAt', () => {
    it.each([
        [5, 0, 'breakfast'],
        [8, 30, 'breakfast'],
        [10, 59, 'breakfast'],
        [11, 0, 'lunch'],
        [12, 45, 'lunch'],
        [14, 59, 'lunch'],
        [15, 0, 'snack'],
        [16, 30, 'snack'],
        [17, 59, 'snack'],
        [18, 0, 'dinner'],
        [20, 15, 'dinner'],
        [23, 59, 'dinner'],
        [0, 0, 'dinner'],
        [4, 59, 'dinner'],
    ])('presets %i:%i to %s', (hour, minute, type) => {
        expect(mealTypeAt(at(hour, minute))).toBe(type);
    });

    it('only ever answers one of the four meal types', () => {
        for (let hour = 0; hour < 24; hour += 1) {
            expect(MEAL_TYPES).toContain(mealTypeAt(at(hour)));
        }
    });

    it('defaults to the current time', () => {
        expect(MEAL_TYPES).toContain(mealTypeAt());
    });
});
