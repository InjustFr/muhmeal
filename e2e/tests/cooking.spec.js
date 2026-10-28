import { expect, test } from './support/test.js';
import { signIn, unique } from './support/session.js';

async function api(page, method, url, body) {
    return page.evaluate(async (request) => {
        const response = await fetch(request.url, {
            method: request.method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
            body: request.body ? JSON.stringify(request.body) : undefined,
        });
        if (!response.ok) throw new Error(`${request.method} ${request.url} answered ${response.status}`);
        return response.status === 204 ? null : response.json();
    }, { method, url, body });
}

const ingredient = (page, name, { stock = 12, unit = 'piece', manual = false } = {}) => api(page, 'POST', '/api/ingredients', { name, location: 'pantry', unit, manual, stock });
const recipe = (page, name, lines, output = null) => api(page, 'POST', '/api/recipes', { name, lines: lines.map(([item, amount = null]) => ({ ingredientId: item.id, amount })), output: output && { ingredientId: output[0].id, yield: output[1] } });
const goTo = (page, tab) => page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: tab }).click();
const stockOf = async (page, name) => (await api(page, 'GET', '/api/ingredients')).find((item) => item.name === name).stock;
const card = (page, name) => page.getByTestId('recipe-card').filter({ has: page.getByRole('link', { name, exact: true }) });

async function pickIngredient(page, name) {
    await page.getByTestId('picker-open').click();
    await page.getByRole('searchbox', { name: 'Search ingredients' }).fill(name);
    await page.getByTestId('picker-option').filter({ hasText: name }).click();
}

test.beforeEach(async ({ page }) => {
    await signIn(page);
});

test('the demo recipes that can be cooked are on the Cook page, the others are not', async ({ page }) => {
    await expect(page.getByRole('heading', { level: 1, name: 'Cook' })).toBeVisible();
    await expect(card(page, 'Pasta al pomodoro')).toBeVisible();
    await expect(card(page, 'Cheese omelette')).toBeVisible();
    await expect(card(page, 'Seitan')).toBeVisible();
    await expect(card(page, 'Seitan stew')).toHaveCount(0);
    await expect(card(page, 'Berry yoghurt bowl')).toHaveCount(0);
    await expect(page.getByTestId('running-low')).toContainText('running low');

    await goTo(page, 'Recipes');
    await expect(card(page, 'Seitan stew')).toContainText('Missing 1');
    await expect(card(page, 'Pasta al pomodoro')).toContainText('Can cook');
    await expect(card(page, 'Berry yoghurt bowl')).toContainText('Missing 1');
});

test('the running-low chip leads to the groceries', async ({ page }) => {
    await page.getByTestId('running-low').click();

    await expect(page).toHaveURL(/\/groceries$/);
});

test('a recipe is written, cooked with an edited amount, logged for both eaters and deleted with the ingredients put back', async ({ page }) => {
    const pasta = unique('Spaghetti');
    const sauce = unique('Sauce');
    const oil = unique('Rapeseed oil');
    const dish = unique('Spaghetti night');
    await ingredient(page, pasta, { unit: 'pack', stock: 36 });
    await ingredient(page, sauce, { unit: 'jar', stock: 24 });
    await ingredient(page, oil, { unit: 'bottle', stock: 10, manual: true });

    await goTo(page, 'Recipes');
    await page.getByRole('link', { name: 'New recipe' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'New recipe' })).toBeVisible();
    await page.getByLabel('Name', { exact: true }).fill(dish);
    for (const name of [pasta, sauce, oil]) await pickIngredient(page, name);
    await expect(page.getByTestId('recipe-line')).toHaveCount(3);
    await page.getByTestId('recipe-line').filter({ hasText: sauce }).getByRole('button', { name: 'Some' }).click();
    await page.getByTestId('save-recipe').click();

    await expect(page.getByRole('heading', { level: 1, name: dish })).toBeVisible();
    await expect(page.getByTestId('recipe-line').filter({ hasText: pasta })).toContainText('You have: 3 / Needs: 1 pack');
    await expect(page.getByTestId('recipe-line').filter({ hasText: sauce })).toContainText('Needs: Some');
    await expect(page.getByTestId('short-note')).toHaveCount(0);

    await page.getByTestId('cook-it').click();
    const sheet = page.getByRole('dialog', { name: `Cook ${dish}` });
    await expect(sheet.getByLabel('Title')).toHaveValue(dish);
    await expect(sheet.getByRole('button', { name: 'Demo' })).toHaveAttribute('data-state', 'on');
    await expect(sheet.getByRole('button', { name: 'Sam' })).toHaveAttribute('data-state', 'on');
    const line = (name) => sheet.getByTestId('used-line').filter({ hasText: name });
    await expect(line(pasta).getByTestId('used-preview')).toHaveText('3 → 2 packs');
    await expect(line(sauce).getByTestId('used-note')).toHaveText('Not deducted');
    await expect(line(oil).getByTestId('used-note')).toHaveText('Not deducted');
    await line(pasta).getByRole('button', { name: `More ${pasta}` }).click();
    await expect(line(pasta).getByTestId('used-preview')).toHaveText('3 → 1¾ packs');

    await sheet.getByTestId('submit-meal').click();
    await expect(sheet.getByTestId('cook-success')).toBeVisible();
    await expect(sheet.getByTestId('stock-tick')).toHaveCount(1);
    await expect(sheet.getByTestId('stock-tick').filter({ hasText: pasta })).toContainText('1¾ packs');
    await expect(page.getByTestId('toast').filter({ hasText: 'Meal logged' })).toBeVisible();
    await sheet.getByRole('button', { name: 'Done' }).click();
    await expect(sheet).toBeHidden();

    expect(await stockOf(page, pasta)).toBe(21);
    expect(await stockOf(page, sauce)).toBe(24);
    expect(await stockOf(page, oil)).toBe(10);

    await goTo(page, 'History');
    const meal = page.getByTestId('meal').filter({ hasText: dish });
    await expect(meal).toHaveCount(1);
    await expect(meal.getByTestId('eater')).toHaveText(['Demo', 'Sam']);
    await meal.getByTestId('toggle-lines').click();
    await expect(meal.getByTestId('meal-line')).toHaveText([`${pasta}1¼ packs`]);

    await meal.getByRole('button', { name: `Delete ${dish}` }).click();
    const confirmation = page.getByRole('alertdialog', { name: 'Delete this meal?' });
    await expect(confirmation.getByRole('switch', { name: 'Put ingredients back' })).toBeChecked();
    await confirmation.getByTestId('confirm-delete-meal').click();
    await expect(meal).toHaveCount(0);

    expect(await stockOf(page, pasta)).toBe(36);
    expect(await stockOf(page, oil)).toBe(10);
});

test('deleting a meal can leave the stock alone', async ({ page }) => {
    const rice = unique('Basmati');
    const created = await ingredient(page, rice, { stock: 36 });
    const title = unique('Rice bowl');
    const members = await page.evaluate(() => JSON.parse(document.getElementById('app-session').textContent).members);
    await api(page, 'POST', '/api/meals', { title, type: 'lunch', eatenOn: '2026-10-05', eaterIds: [members[0].id], used: [{ ingredientId: created.id, amount: 12 }] });
    expect(await stockOf(page, rice)).toBe(24);

    await goTo(page, 'History');
    await page.getByTestId('meal').filter({ hasText: title }).getByRole('button', { name: `Delete ${title}` }).click();
    const confirmation = page.getByRole('alertdialog', { name: 'Delete this meal?' });
    await confirmation.getByRole('switch', { name: 'Put ingredients back' }).click();
    await confirmation.getByTestId('confirm-delete-meal').click();
    await expect(page.getByTestId('meal').filter({ hasText: title })).toHaveCount(0);

    expect(await stockOf(page, rice)).toBe(24);
});

test('a prep is made, the output stock goes up and the dish that needs it becomes makeable', async ({ page }) => {
    const gluten = unique('Vital gluten');
    const seitan = unique('Homemade seitan');
    const prep = unique('Seitan batch');
    const dish = unique('Seitan skewers');
    const glutenItem = await ingredient(page, gluten, { unit: 'bag', stock: 24 });
    const seitanItem = await ingredient(page, seitan, { unit: 'bag', stock: 0 });
    await recipe(page, prep, [[glutenItem, 12]], [seitanItem, 12]);
    await recipe(page, dish, [[seitanItem, 12]]);

    await page.goto('/');
    await expect(card(page, prep)).toBeVisible();
    await expect(card(page, dish)).toHaveCount(0);

    await page.getByRole('button', { name: `Make it: ${prep}` }).click();
    const sheet = page.getByRole('dialog', { name: `Make ${prep}` });
    await expect(sheet.getByTestId('prep-preview')).toHaveText('0 → 1 bag');
    await expect(sheet.getByTestId('used-line').filter({ hasText: gluten }).getByTestId('used-preview')).toHaveText('2 → 1 bag');
    await sheet.getByTestId('submit-prep').click();
    await expect(sheet.getByTestId('cook-success')).toBeVisible();
    await expect(sheet.getByTestId('stock-tick').filter({ hasText: seitan })).toContainText('1 bag');
    await expect(sheet.getByTestId('stock-tick').filter({ hasText: gluten })).toContainText('1 bag');
    await sheet.getByRole('button', { name: 'Done' }).click();

    await expect(card(page, dish)).toBeVisible();
    expect(await stockOf(page, seitan)).toBe(12);
    expect(await stockOf(page, gluten)).toBe(12);
    const history = await api(page, 'GET', '/api/meals');
    expect(history.meals.filter((meal) => meal.title === prep)).toHaveLength(0);
});

test('a free meal is logged from the Cook page with a new ingredient created on the spot', async ({ page }) => {
    const eggs = unique('Duck eggs');
    const title = unique('Late brunch');
    const created = unique('Chives');
    await ingredient(page, eggs, { stock: 24 });

    await page.getByTestId('log-meal').click();
    const sheet = page.getByRole('dialog', { name: 'Log a meal' });
    await expect(sheet.getByTestId('submit-meal')).toBeDisabled();
    await expect(sheet.getByRole('button', { name: /^(Breakfast|Lunch|Dinner|Snack)$/ }).and(sheet.locator('[data-state="on"]'))).toHaveCount(1);
    await sheet.getByRole('button', { name: 'Breakfast' }).click();
    await sheet.getByRole('button', { name: 'Yesterday' }).click();
    await sheet.getByLabel('Title').fill(title);
    await expect(sheet.getByTestId('submit-meal')).toBeEnabled();

    await pickIngredient(page, eggs);
    await expect(sheet.getByTestId('used-line').filter({ hasText: eggs }).getByTestId('used-preview')).toHaveText('2 → 1');

    await sheet.getByTestId('picker-open').click();
    await sheet.getByRole('searchbox', { name: 'Search ingredients' }).fill(created);
    await sheet.getByTestId('picker-create').click();
    const ingredientSheet = page.getByRole('dialog', { name: 'New ingredient' });
    await expect(ingredientSheet.getByLabel('Name')).toHaveValue(created);
    await expect(ingredientSheet.getByRole('button', { name: 'Save & add another' })).toHaveCount(0);
    await ingredientSheet.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(sheet.getByTestId('used-line').filter({ hasText: created }).getByTestId('used-preview')).toHaveText('1 → 0');

    await sheet.getByRole('button', { name: 'Sam' }).click();
    await expect(sheet.getByRole('button', { name: 'Sam' })).toHaveAttribute('data-state', 'off');
    await sheet.getByRole('button', { name: 'Demo' }).click();
    await expect(sheet.getByRole('button', { name: 'Demo' })).toHaveAttribute('data-state', 'on');

    await sheet.getByTestId('submit-meal').click();
    await expect(sheet.getByTestId('cook-success')).toBeVisible();
    await sheet.getByRole('button', { name: 'Done' }).click();
    expect(await stockOf(page, eggs)).toBe(12);
    expect(await stockOf(page, created)).toBe(0);

    await goTo(page, 'History');
    const meal = page.getByTestId('meal').filter({ hasText: title });
    await expect(meal.getByTestId('eater')).toHaveText(['Demo']);
    await expect(meal.locator('xpath=ancestor::section').getByRole('heading', { level: 2 })).toContainText('Yesterday');
    await expect(meal).toContainText('Breakfast');
});

test('a meal needs a title or an ingredient and an eater', async ({ page }) => {
    await page.getByTestId('log-meal').click();
    const sheet = page.getByRole('dialog', { name: 'Log a meal' });

    await expect(sheet.getByTestId('submit-meal')).toBeDisabled();
    await sheet.getByLabel('Title').fill('Soup');
    await expect(sheet.getByTestId('submit-meal')).toBeEnabled();
    await sheet.getByRole('button', { name: 'Sam' }).click();
    await sheet.getByRole('button', { name: 'Demo' }).click();
    await expect(sheet.getByRole('button', { name: 'Demo' })).toHaveAttribute('data-state', 'on');
    await sheet.getByLabel('Title').fill('');
    await expect(sheet.getByTestId('submit-meal')).toBeDisabled();
});

test('a short recipe can still be cooked and the preview flags the shortfall', async ({ page }) => {
    const flour = unique('Spelt flour');
    const bread = unique('Spelt bread');
    const item = await ingredient(page, flour, { unit: 'bag', stock: 3 });
    const created = await recipe(page, bread, [[item, 12]]);

    await goTo(page, 'Recipes');
    await expect(card(page, bread)).toContainText('Missing 1');
    await card(page, bread).getByRole('link', { name: bread }).click();
    await expect(page).toHaveURL(new RegExp(`/recipes/${created.id}$`));
    await expect(page.getByTestId('short-note')).toContainText(`Short on ${flour}`);
    await expect(page.getByTestId('recipe-line')).toContainText('You have: ¼ / Needs: 1 bag');

    await page.getByTestId('cook-it').click();
    const sheet = page.getByRole('dialog', { name: `Cook ${bread}` });
    await expect(sheet.getByTestId('used-preview')).toHaveText('¼ → 0 bags');
    await expect(sheet.getByTestId('used-preview')).toHaveClass(/used__preview--short/);
    await sheet.getByTestId('submit-meal').click();
    await expect(sheet.getByTestId('cook-success')).toBeVisible();
    await sheet.getByRole('button', { name: 'Done' }).click();

    expect(await stockOf(page, flour)).toBe(0);
});

test('a recipe is edited and then deleted', async ({ page }) => {
    const first = unique('Lentils');
    const second = unique('Carrots');
    const third = unique('Cumin');
    const name = unique('Dal');
    const renamed = unique('Spiced dal');
    const lentils = await ingredient(page, first, { stock: 24 });
    const carrots = await ingredient(page, second, { stock: 24 });
    await ingredient(page, third, { stock: 24, manual: true });
    const created = await recipe(page, name, [[lentils, 24], [carrots, 12]]);

    await page.goto(`/recipes/${created.id}/edit`);
    await expect(page.getByLabel('Name', { exact: true })).toHaveValue(name);
    await expect(page.getByTestId('recipe-line')).toHaveCount(2);
    await page.getByLabel('Name', { exact: true }).fill(renamed);
    await page.getByRole('button', { name: `Remove ${second}` }).click();
    await page.getByRole('button', { name: `More ${first}` }).click();
    await page.getByRole('button', { name: '½', exact: true }).click();
    await pickIngredient(page, third);
    await page.getByTestId('recipe-line').filter({ hasText: third }).getByRole('button', { name: 'Some' }).click();
    await page.getByTestId('save-recipe').click();

    await expect(page.getByRole('heading', { level: 1, name: renamed })).toBeVisible();
    await expect(page.getByTestId('recipe-line')).toHaveCount(2);
    await expect(page.getByTestId('recipe-line').filter({ hasText: first })).toContainText('Needs: 3½');
    await expect(page.getByTestId('recipe-line').filter({ hasText: third })).toContainText('Needs: Some');

    await page.getByRole('link', { name: 'Edit' }).click();
    await page.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();

    await expect(page).toHaveURL(/\/recipes$/);
    await expect(card(page, renamed)).toHaveCount(0);
    expect((await api(page, 'GET', '/api/ingredients')).map((item) => item.name)).toContain(first);
});

test('a recipe name already taken is refused, ignoring case', async ({ page }) => {
    const item = await ingredient(page, unique('Beans'));
    const name = unique('Chilli');
    await recipe(page, name, [[item, 12]]);

    await goTo(page, 'Recipes');
    await page.getByRole('link', { name: 'New recipe' }).click();
    await page.getByLabel('Name', { exact: true }).fill(name.toUpperCase());
    await pickIngredient(page, item.name);
    await page.getByTestId('save-recipe').click();

    await expect(page.getByRole('alert')).toHaveText(`There is already a recipe called “${name.toUpperCase()}”.`);
    await expect(page).toHaveURL(/\/recipes\/new$/);
});

test('a recipe draft survives a trip to another tab and is cleared once saved', async ({ page }) => {
    const item = await ingredient(page, unique('Lentils'));
    const name = unique('Dal');

    await goTo(page, 'Recipes');
    await page.getByRole('link', { name: 'New recipe' }).click();
    await page.getByLabel('Name', { exact: true }).fill(name);
    await pickIngredient(page, item.name);

    await goTo(page, 'Pantry');
    await expect(page.getByRole('heading', { level: 1, name: 'Pantry' })).toBeVisible();
    await goTo(page, 'Recipes');

    await expect(page).toHaveURL(/\/recipes\/new$/);
    await expect(page.getByLabel('Name', { exact: true })).toHaveValue(name);
    await expect(page.getByTestId('recipe-line')).toHaveCount(1);
    await page.getByTestId('save-recipe').click();
    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();

    await page.getByRole('link', { name: 'All recipes' }).click();
    await page.getByRole('link', { name: 'New recipe' }).click();
    await expect(page.getByLabel('Name', { exact: true })).toHaveValue('');
    await expect(page.getByTestId('recipe-line')).toHaveCount(0);
});

test('a prep recipe is written with its output and yield', async ({ page }) => {
    const gluten = unique('Gluten flour');
    const output = unique('Tempeh');
    const name = unique('Tempeh batch');
    await ingredient(page, gluten, { unit: 'bag', stock: 12 });
    await ingredient(page, output, { unit: 'pack', stock: 0 });

    await goTo(page, 'Recipes');
    await page.getByRole('link', { name: 'New recipe' }).click();
    await page.getByLabel('Name', { exact: true }).fill(name);
    await pickIngredient(page, gluten);
    await page.getByRole('button', { name: 'A prep' }).click();
    await expect(page.getByTestId('save-recipe')).toBeDisabled();

    await page.getByRole('button', { name: 'Pick the ingredient it makes' }).click();
    await page.getByRole('searchbox', { name: 'Search ingredients' }).fill(output);
    await page.getByTestId('picker-option').filter({ hasText: output }).click();
    await expect(page.getByTestId('prep-output')).toContainText(output);
    await page.getByRole('button', { name: 'Make more' }).click();
    await page.getByTestId('save-recipe').click();

    await expect(page.getByRole('heading', { level: 1, name })).toBeVisible();
    await expect(page.getByText(`Makes 2 packs ${output}`)).toBeVisible();
    await expect(page.getByTestId('make-it')).toBeVisible();
});

test('an ingredient that a recipe uses cannot be deleted, and the message names the recipe', async ({ page }) => {
    const thyme = unique('Fresh thyme');
    const name = unique('Roast');
    const item = await ingredient(page, thyme);
    await recipe(page, name, [[item, 12]]);

    await goTo(page, 'Pantry');
    await page.getByRole('searchbox', { name: 'Search or add an ingredient' }).fill(thyme);
    await page.getByRole('button', { name: `${thyme} (edit)` }).click();
    const sheet = page.getByRole('dialog', { name: 'Edit ingredient' });
    await sheet.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('alertdialog').getByRole('button', { name: 'Delete' }).click();

    await expect(page.getByTestId('toast').filter({ hasText: `“${thyme}” cannot be deleted: it is used by ${name}.` })).toBeVisible();
    await expect(sheet).toBeVisible();
    expect((await api(page, 'GET', '/api/ingredients')).map((entry) => entry.name)).toContain(thyme);
});

test('older meals are loaded by thirty days', async ({ page }) => {
    const members = await page.evaluate(() => JSON.parse(document.getElementById('app-session').textContent).members);
    const prefix = unique('Old');
    for (let day = 1; day <= 31; day += 1) {
        await api(page, 'POST', '/api/meals', { title: `${prefix} ${day}`, type: 'dinner', eatenOn: `2019-01-${String(day).padStart(2, '0')}`, eaterIds: [members[0].id], used: [] });
    }

    await goTo(page, 'History');
    await expect(page.getByText(`${prefix} 31`, { exact: true })).toBeVisible();
    await expect(page.getByText(`${prefix} 1`, { exact: true })).toHaveCount(0);
    await page.getByTestId('load-older').click();
    await expect(page.getByText(`${prefix} 1`, { exact: true })).toBeVisible();
    await expect(page.getByTestId('load-older')).toHaveCount(0);
});

test('the cooked moment is still under reduced motion: the stock shows its new value at once and nothing moves', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    const stock = unique('Millet');
    const item = await ingredient(page, stock, { stock: 24 });
    const name = unique('Millet porridge');
    await recipe(page, name, [[item, 12]]);

    await page.goto('/');
    await page.getByRole('button', { name: `Cook it: ${name}` }).click();
    const sheet = page.getByRole('dialog', { name: `Cook ${name}` });
    await sheet.getByTestId('submit-meal').click();

    await expect(sheet.getByTestId('stock-tick')).toContainText('1');
    await expect(sheet.locator('.success__puff').first()).toBeHidden();
    expect(await sheet.locator('.success__pot').evaluate((pot) => getComputedStyle(pot).animationName)).toBe('none');
});
