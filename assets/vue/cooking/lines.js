import { TWELFTHS_PER_UNIT } from '../pantry/quantity.js';

export const stepFor = (unit) => (unit === 'piece' ? TWELFTHS_PER_UNIT : 3);

export function lineFromIngredient(ingredient, amount = TWELFTHS_PER_UNIT) {
    return {
        ingredientId: ingredient.id,
        name: ingredient.name,
        unit: ingredient.unit,
        manual: ingredient.manual,
        stock: ingredient.stock,
        amount,
    };
}

export function linesFromRecipe(recipe) {
    return recipe.lines.map((line) => lineFromIngredient(
        { id: line.ingredientId, name: line.name, unit: line.unit, manual: line.manual, stock: line.stock },
        line.manual || line.amount === null ? 0 : line.amount,
    ));
}

export function withIngredient(lines, ingredient) {
    if (lines.some((line) => line.ingredientId === ingredient.id)) return lines;

    return [...lines, lineFromIngredient(ingredient)];
}

export const usedOf = (lines) => lines.filter((line) => line.amount > 0).map((line) => ({ ingredientId: line.ingredientId, amount: line.amount }));

export const stockAfter = (line) => Math.max(0, line.stock - line.amount);

export const isShort = (line) => line.amount > line.stock;

export const ticks = (from, to, count) => Array.from({ length: count + 1 }, (_, index) => Math.round(from + ((to - from) * index) / count));
