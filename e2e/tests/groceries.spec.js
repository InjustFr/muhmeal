import { expect, test } from './support/test.js';
import { SAM, signIn, unique } from './support/session.js';

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

const ingredient = (page, name, { stock = 12, unit = 'piece', location = 'pantry' } = {}) => api(page, 'POST', '/api/ingredients', { name, location, unit, manual: false, stock });
const goToGroceries = (page) => page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Groceries' }).click();
const stockOf = async (page, name) => (await api(page, 'GET', '/api/ingredients')).find((item) => item.name === name).stock;
const row = (page, name) => page.getByTestId('tick-row').filter({ hasText: name });
const group = (page, title) => page.locator('section').filter({ has: page.getByRole('heading', { level: 2, name: new RegExp(`^${title}`) }) });
const line = (page, name) => page.getByTestId('bought-line').filter({ hasText: name });

async function discardOpenList(page) {
    if ((await api(page, 'GET', '/api/grocery-list')) !== null) await api(page, 'POST', '/api/grocery-list/put-away', { bought: [] });
}

test.beforeEach(async ({ page }) => {
    await signIn(page);
    await discardOpenList(page);
    await page.reload();
});

test('what is running low is suggested, out of stock first, and a homemade ingredient never is', async ({ page }) => {
    await goToGroceries(page);
    await expect(page.getByTestId('start-shopping')).toBeVisible();

    const names = await page.getByTestId('tick-row').allTextContents();
    const position = (name) => names.findIndex((text) => text.startsWith(name));
    for (const name of ['Yoghurt', 'Cheddar', 'Leeks', 'Rice']) expect(position(name), name).toBeGreaterThanOrEqual(0);
    expect(position('Yoghurt')).toBeLessThan(position('Cheddar'));
    expect(position('Cheddar')).toBeLessThan(position('Leeks'));
    expect(position('Leeks')).toBeLessThan(position('Rice'));
    await expect(row(page, 'Yoghurt')).toContainText('Out');
    await expect(row(page, 'Cheddar')).toContainText('left');
    for (const text of await page.getByTestId('tick-row').all()) await expect(text).toHaveAttribute('aria-checked', 'true');

    expect(position('Seitan')).toBe(-1);
    const seitan = (await api(page, 'GET', '/api/ingredients')).find((item) => item.name === 'Seitan');
    expect([seitan.stock, seitan.homemade, seitan.suggested]).toEqual([0, true, false]);

});

test('the Cook page counts the suggested ingredients', async ({ page }) => {
    const suggested = (await api(page, 'GET', '/api/ingredients')).filter((item) => item.suggested).length;

    await expect(page.getByTestId('running-low')).toHaveText(`${suggested} running low`);
});

test('a shopping trip is prepared, shared between two phones and put away with an extra ingredient', async ({ page, browser }) => {
    const milk = unique('Oat drink');
    const oats = unique('Rolled oats');
    const chives = unique('Chives');
    await ingredient(page, milk, { stock: 3, unit: 'carton', location: 'fridge' });
    await ingredient(page, oats, { stock: 0, unit: 'bag' });
    await ingredient(page, chives, { stock: 24, location: 'fridge' });
    const demoStocks = (await api(page, 'GET', '/api/ingredients')).filter((item) => ['Yoghurt', 'Cheddar', 'Leeks', 'Rice'].includes(item.name)).map((item) => [item.name, item.stock]);

    await goToGroceries(page);
    await expect(row(page, milk)).toHaveAttribute('aria-checked', 'true');
    await expect(row(page, chives)).toHaveCount(0);
    await row(page, 'Leeks').click();
    await expect(row(page, 'Leeks')).toHaveAttribute('aria-checked', 'false');
    await page.getByTestId('picker-open').click();
    await page.getByRole('searchbox', { name: 'Search ingredients' }).fill(chives);
    await page.getByTestId('picker-option').filter({ hasText: chives }).click();
    await expect(row(page, chives)).toHaveAttribute('aria-checked', 'true');
    await row(page, chives).click();
    await expect(row(page, chives)).toHaveAttribute('aria-checked', 'false');

    await page.getByTestId('start-shopping').click();
    await expect(page.getByTestId('progress')).toBeVisible();
    await expect(page.getByTestId('toast').filter({ hasText: 'Shopping trip started' })).toBeVisible();
    await expect(row(page, milk)).toHaveAttribute('aria-checked', 'false');
    await expect(row(page, oats)).toHaveAttribute('aria-checked', 'false');
    await expect(row(page, 'Leeks')).toHaveCount(0);
    await expect(row(page, chives)).toHaveCount(0);
    await expect(page.getByRole('heading', { level: 1, name: 'Groceries' })).toBeVisible();
    await expect(page.getByText('Started by Demo')).toBeVisible();

    const samContext = await browser.newContext({ baseURL: test.info().project.use.baseURL, locale: 'en-GB', timezoneId: 'Europe/Paris' });
    const samPage = await samContext.newPage();
    await signIn(samPage, SAM);
    await samPage.goto('/groceries');
    await expect(row(samPage, milk)).toHaveAttribute('aria-checked', 'false');

    await row(page, milk).click();
    await expect(row(page, milk)).toHaveAttribute('aria-checked', 'true');
    const fridge = group(page, 'Fridge').getByTestId('tick-row');
    await expect(fridge.last()).toContainText(milk);
    await expect(page.getByTestId('progress')).toContainText('1 of');
    await expect.poll(async () => (await api(page, 'GET', '/api/grocery-list')).items.find((item) => item.name === milk).ticked).toBe(true);

    await samPage.bringToFront();
    await samPage.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect(row(samPage, milk)).toHaveAttribute('aria-checked', 'true');
    await expect(row(samPage, oats)).toHaveAttribute('aria-checked', 'false');
    await row(page, milk).click();
    await expect(row(page, milk)).toHaveAttribute('aria-checked', 'false');
    await expect.poll(async () => (await api(page, 'GET', '/api/grocery-list')).items.find((item) => item.name === milk).ticked).toBe(false);
    await row(page, milk).click();
    await row(page, oats).click();
    await expect.poll(async () => (await api(page, 'GET', '/api/grocery-list')).items.filter((item) => item.ticked).map((item) => item.name).sort()).toEqual([milk, oats].sort());

    await page.getByTestId('back-home').click();
    await expect(page).toHaveURL(/\/groceries\/put-away$/);
    await expect(page.getByRole('heading', { level: 1, name: 'Put away' })).toBeVisible();
    await expect(line(page, milk).getByTestId('bought-preview')).toHaveText('¼ → 1¼ cartons');
    await expect(line(page, oats).getByTestId('bought-preview')).toHaveText('0 → 1 bag');
    await line(page, milk).getByRole('button', { name: `Buy more ${milk}` }).click();
    await expect(line(page, milk).getByTestId('bought-preview')).toHaveText('¼ → 2¼ cartons');
    await expect(line(page, 'Yoghurt')).toHaveCount(0);

    await page.getByTestId('toggle-unbought').click();
    await expect(row(page, 'Yoghurt')).toHaveAttribute('aria-checked', 'false');

    await page.getByTestId('picker-open').click();
    await page.getByRole('searchbox', { name: 'Search ingredients' }).fill(chives);
    await page.getByTestId('picker-option').filter({ hasText: chives }).click();
    await expect(line(page, chives).getByTestId('bought-preview')).toHaveText('2 → 3');

    await page.getByTestId('put-away-done').click();
    await expect(page.getByTestId('toast').filter({ hasText: 'Groceries put away' })).toBeVisible();
    await expect(page).toHaveURL(/\/groceries$/);
    await expect(page.getByTestId('start-shopping')).toBeVisible();

    expect(await stockOf(page, milk)).toBe(18);
    expect(await stockOf(page, oats)).toBe(12);
    expect(await stockOf(page, chives)).toBe(36);
    expect((await api(page, 'GET', '/api/ingredients')).filter((item) => ['Yoghurt', 'Cheddar', 'Leeks', 'Rice'].includes(item.name)).map((item) => [item.name, item.stock])).toEqual(demoStocks);
    expect(await api(page, 'GET', '/api/grocery-list')).toBeNull();

    await samPage.evaluate(() => window.dispatchEvent(new Event('focus')));
    await expect(samPage.getByTestId('start-shopping')).toBeVisible();
    await samContext.close();
});

test('a put-away page of a list that is gone leads back to the groceries', async ({ page }) => {
    await page.goto('/groceries/put-away');

    await expect(page).toHaveURL(/\/groceries$/);
    await expect(page.getByTestId('start-shopping')).toBeVisible();
});

test('a list needs an item to start and only one can be open', async ({ page }) => {
    const name = unique('Single item');
    const created = await ingredient(page, name, { stock: 0 });

    await goToGroceries(page);
    await expect(page.getByTestId('start-shopping')).toBeVisible();
    const rows = await page.getByTestId('tick-row').all();
    for (const tick of rows) await tick.click();
    await expect(page.getByTestId('start-shopping')).toBeDisabled();

    await row(page, name).click();
    await page.getByTestId('start-shopping').click();
    await expect(page.getByTestId('progress')).toContainText('0 of 1');

    await expect(api(page, 'POST', '/api/grocery-list', { ingredientIds: [created.id] })).rejects.toThrow('answered 422');
    await page.getByTestId('back-home').click();
    await page.getByTestId('put-away-done').click();
    await expect(page.getByTestId('start-shopping')).toBeVisible();
    expect(await stockOf(page, name)).toBe(0);
});

test('an item ticked in at put-away and stepped down to nothing adds nothing to the stock', async ({ page }) => {
    const name = unique('Maybe bread');
    const created = await ingredient(page, name, { stock: 0, unit: 'bag' });
    await api(page, 'POST', '/api/grocery-list', { ingredientIds: [created.id] });

    await page.goto('/groceries/put-away');
    await page.getByTestId('toggle-unbought').click();
    await row(page, name).click();
    await expect(line(page, name).getByTestId('bought-preview')).toHaveText('0 → 1 bag');
    await line(page, name).getByRole('button', { name: `Buy less ${name}` }).click();
    await line(page, name).getByRole('button', { name: `Buy less ${name}` }).click();
    await line(page, name).getByRole('button', { name: `Buy less ${name}` }).click();
    await line(page, name).getByRole('button', { name: `Buy less ${name}` }).click();
    await expect(line(page, name).getByTestId('bought-preview')).toHaveText('Nothing added');

    await page.getByTestId('put-away-done').click();
    await expect(page).toHaveURL(/\/groceries$/);

    expect(await stockOf(page, name)).toBe(0);
    expect(await api(page, 'GET', '/api/grocery-list')).toBeNull();
});
