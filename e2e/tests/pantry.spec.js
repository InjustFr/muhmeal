import { expect, test } from './support/test.js';
import { signIn, unique } from './support/session.js';

const row = (page, name) => page.getByTestId('ingredient-row').filter({ has: page.getByRole('button', { name: `${name} (edit)` }) });
const quantity = (page, name) => row(page, name).getByRole('button', { name: /^Set the stock of/ });
const search = (page) => page.getByRole('searchbox', { name: 'Search or add an ingredient' });
const place = (page, name) => page.getByRole('group', { name: 'Place' }).getByRole('button', { name });

async function addIngredient(page, name, { location = 'Fridge', unit = null } = {}) {
    await search(page).fill(name);
    await page.getByRole('button', { name: `Add “${name}”` }).click();
    const sheet = page.getByRole('dialog', { name: 'New ingredient' });
    await expect(sheet.getByLabel('Name')).toHaveValue(name);
    await sheet.getByRole('button', { name: location }).click();
    if (unit) {
        await sheet.getByRole('combobox', { name: 'Counted in' }).click();
        await page.getByRole('option', { name: unit }).click();
    }
    await sheet.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(sheet).toBeHidden();
    await expect(row(page, name)).toBeVisible();
    await search(page).fill('');
    await expect(row(page, name)).toBeVisible();
}

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Pantry' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Pantry' })).toBeVisible();
});

test('the demo kitchen is listed by place with the empty and low ones flagged', async ({ page }) => {
    await expect(row(page, 'Olive oil')).toBeVisible();
    await expect(row(page, 'Yoghurt')).toContainText('Out');
    await expect(row(page, 'Cheddar')).toContainText('Low');
    await expect(row(page, 'Milk')).not.toContainText('Low');
    await expect(quantity(page, 'Milk')).toContainText('1¾ cartons');
    await expect(quantity(page, 'Eggs')).toHaveText(/8$/);

    await place(page, 'Freezer').click();
    await expect(row(page, 'Peas')).toBeVisible();
    await expect(row(page, 'Milk')).toHaveCount(0);
});

test('rows that are out sit dimmed at the bottom of their place', async ({ page }) => {
    await place(page, 'Fridge').click();
    const outs = await page.getByTestId('ingredient-row').evaluateAll((rows) => rows.map((element) => element.classList.contains('row--out')));
    expect(outs).toContain(true);
    expect(outs).toEqual([...outs].sort((a, b) => Number(a) - Number(b)));
});

test('searching offers to add a new name, and the ingredient is created', async ({ page }) => {
    const name = unique('Leeks');
    await search(page).fill(name);
    await expect(page.getByTestId('ingredient-row')).toHaveCount(0);
    await page.getByRole('button', { name: `Add “${name}”` }).click();
    const sheet = page.getByRole('dialog', { name: 'New ingredient' });
    await expect(sheet.getByLabel('Name')).toHaveValue(name);
    await sheet.getByRole('button', { name: 'Fridge' }).click();
    await sheet.getByRole('button', { name: 'Save', exact: true }).click();

    await expect(row(page, name)).toBeVisible();
    await expect(page.getByRole('button', { name: `Add “${name}”` })).toHaveCount(0);
    await expect(row(page, name)).toContainText('Out');
});

test('two taps on plus are sent as one change and survive a reload', async ({ page }) => {
    const name = unique('Carrots');
    await addIngredient(page, name);
    const adjustments = [];
    page.on('request', (request) => request.url().endsWith('/adjust') && adjustments.push(request.postDataJSON()));

    const plus = row(page, name).getByRole('button', { name: `One more ${name}` });
    await plus.click();
    await plus.click();
    await expect(quantity(page, name)).toHaveText(/2$/);
    await expect(row(page, name)).not.toContainText('Out');
    await expect.poll(() => adjustments.length).toBe(1);
    expect(adjustments).toEqual([{ by: 24 }]);

    await row(page, name).getByRole('button', { name: `One less ${name}` }).click();
    await expect.poll(() => adjustments.length).toBe(2);
    expect(adjustments[1]).toEqual({ by: -12 });

    await page.reload();
    await search(page).fill(name);
    await expect(quantity(page, name)).toHaveText(/1$/);
});

test('the minus never goes below zero', async ({ page }) => {
    const name = unique('Lemons');
    await addIngredient(page, name);

    await expect(row(page, name).getByRole('button', { name: `One less ${name}` })).toBeDisabled();
    await row(page, name).getByRole('button', { name: `One more ${name}` }).click();
    await row(page, name).getByRole('button', { name: `One less ${name}` }).click();

    await expect(quantity(page, name)).toHaveText(/0$/);
    await expect(row(page, name)).toContainText('Out');
});

test('a fraction chip sets the stock to three quarters of a container', async ({ page }) => {
    const name = unique('Cream');
    await addIngredient(page, name, { unit: 'Carton' });

    await quantity(page, name).click();
    const sheet = page.getByRole('dialog', { name: `How much ${name}?` });
    await sheet.getByRole('button', { name: '¾' }).click();
    await expect(sheet.getByTestId('stock-summary')).toHaveText('¾ carton');
    await sheet.getByRole('button', { name: 'Set stock' }).click();
    await expect(sheet).toBeHidden();

    await expect(quantity(page, name)).toContainText('¾ carton');

    await quantity(page, name).click();
    await sheet.getByRole('button', { name: 'More' }).click();
    await expect(sheet.getByTestId('stock-summary')).toHaveText('1¾ cartons');
    await sheet.getByRole('button', { name: 'Set stock' }).click();
    await expect(sheet).toBeHidden();
    await page.reload();
    await search(page).fill(name);
    await expect(quantity(page, name)).toContainText('1¾ cartons');
});

test('an ingredient is renamed and moved to the freezer', async ({ page }) => {
    const name = unique('Berries');
    const renamed = unique('Frozen berries');
    await addIngredient(page, name);

    await row(page, name).getByRole('button', { name: `${name} (edit)` }).click();
    const sheet = page.getByRole('dialog', { name: 'Edit ingredient' });
    await expect(sheet.getByLabel('Name')).toHaveValue(name);
    await sheet.getByLabel('Name').fill(renamed);
    await sheet.getByRole('button', { name: 'Freezer' }).click();
    await sheet.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(sheet).toBeHidden();

    await place(page, 'Fridge').click();
    await expect(row(page, renamed)).toHaveCount(0);
    await place(page, 'Freezer').click();
    await expect(row(page, renamed)).toBeVisible();
    await expect(row(page, name)).toHaveCount(0);
});

test('a name already taken is refused, ignoring case', async ({ page }) => {
    const name = unique('Basil');
    await addIngredient(page, name);

    await search(page).fill(name.toUpperCase());
    await expect(row(page, name)).toBeVisible();
    await expect(page.getByRole('button', { name: /^Add “/ })).toHaveCount(0);

    await row(page, name).getByRole('button', { name: `${name} (edit)` }).click();
    const sheet = page.getByRole('dialog', { name: 'Edit ingredient' });
    await sheet.getByLabel('Name').fill('MILK');
    await sheet.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(sheet.getByRole('alert')).toHaveText('There is already an ingredient called “MILK”.');
    await expect(sheet).toBeVisible();
});

test('save and add another keeps the sheet open for the next ingredient', async ({ page }) => {
    const first = unique('Thyme');
    const second = unique('Sage');
    await search(page).fill(first);
    await page.getByRole('button', { name: `Add “${first}”` }).click();
    const sheet = page.getByRole('dialog', { name: 'New ingredient' });
    await sheet.getByRole('button', { name: 'Save & add another' }).click();
    await expect(sheet.getByLabel('Name')).toHaveValue('');
    await expect(sheet.getByLabel('Name')).toBeFocused();
    await sheet.getByLabel('Name').fill(second);
    await sheet.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(sheet).toBeHidden();

    await search(page).fill('');
    await expect(row(page, first)).toBeVisible();
    await expect(row(page, second)).toBeVisible();
});

test('an ingredient is deleted after a confirmation', async ({ page }) => {
    const name = unique('Parsley');
    await addIngredient(page, name);

    await row(page, name).getByRole('button', { name: `${name} (edit)` }).click();
    await page.getByRole('dialog', { name: 'Edit ingredient' }).getByRole('button', { name: 'Delete' }).click();
    const confirmation = page.getByRole('alertdialog');
    await expect(confirmation).toContainText(`Delete ${name}?`);
    await confirmation.getByRole('button', { name: 'Delete' }).click();

    await expect(row(page, name)).toHaveCount(0);
    await page.reload();
    await search(page).fill(name);
    await expect(row(page, name)).toHaveCount(0);
});
