import { splitQuantity, TWELFTHS_PER_UNIT } from '../pantry/quantity.js';

export const KINDS = ['meal', 'prep'];

export function newLine(ingredient) {
    return { ingredientId: ingredient.id, name: ingredient.name, unit: ingredient.unit, some: false, whole: TWELFTHS_PER_UNIT, fraction: 0 };
}

export function lineFromView(line) {
    const { whole, fraction } = splitQuantity(line.amount ?? TWELFTHS_PER_UNIT);

    return { ingredientId: line.ingredientId, name: line.name, unit: line.unit, some: line.amount === null, whole, fraction };
}

export const lineAmount = (line) => (line.some ? null : line.whole + line.fraction);

export function formFromView(recipe) {
    const { whole, fraction } = splitQuantity(recipe.output?.yield ?? TWELFTHS_PER_UNIT);

    return {
        name: recipe.name,
        kind: recipe.makes,
        lines: recipe.lines.map(lineFromView),
        output: recipe.output ? { id: recipe.output.ingredientId, name: recipe.output.name, unit: recipe.output.unit } : null,
        whole,
        fraction,
    };
}

export function payloadOf(form) {
    return {
        name: form.name.trim(),
        lines: form.lines.map((line) => ({ ingredientId: line.ingredientId, amount: lineAmount(line) })),
        output: form.kind === 'prep' && form.output ? { ingredientId: form.output.id, yield: form.whole + form.fraction } : null,
    };
}

export function isComplete(form) {
    const amountsAreValid = form.lines.every((line) => line.some || lineAmount(line) > 0);
    const outputIsValid = form.kind === 'meal' || (form.output !== null && form.whole + form.fraction > 0);

    return form.name.trim() !== '' && form.lines.length > 0 && amountsAreValid && outputIsValid;
}

export const lacking = (recipe) => recipe.lines.filter((line) => recipe.missing.includes(line.ingredientId));
