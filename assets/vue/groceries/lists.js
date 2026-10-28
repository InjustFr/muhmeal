import { LOCATIONS } from '../pantry/locations.js';
import { MAX_TWELFTHS, TWELFTHS_PER_UNIT } from '../pantry/quantity.js';

const byName = (a, b) => a.name.localeCompare(b.name);
const tickedLast = (a, b) => Number(a.ticked === true) - Number(b.ticked === true);

export function prepareRows(ingredients, extraIds) {
    const extras = new Set(extraIds);

    return ingredients
        .filter((ingredient) => ingredient.suggested || extras.has(ingredient.id))
        .sort((a, b) => Number(b.stock === 0) - Number(a.stock === 0) || byName(a, b));
}

export function groupByLocation(rows) {
    return LOCATIONS
        .map((location) => ({ location, rows: rows.filter((row) => row.location === location).sort((a, b) => tickedLast(a, b) || byName(a, b)) }))
        .filter((group) => group.rows.length > 0);
}

export function boughtLine(source) {
    return {
        ingredientId: source.ingredientId ?? source.id,
        name: source.name,
        unit: source.unit,
        location: source.location,
        stock: source.stock,
        amount: Math.min(TWELFTHS_PER_UNIT, MAX_TWELFTHS - source.stock),
    };
}

export const boughtStep = () => TWELFTHS_PER_UNIT;

export const boughtMax = (line) => MAX_TWELFTHS - line.stock;

export const stockAfterBuying = (line) => Math.min(MAX_TWELFTHS, line.stock + line.amount);
