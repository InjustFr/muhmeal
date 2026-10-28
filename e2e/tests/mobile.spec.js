import { expect, test } from './support/test.js';
import { signIn } from './support/session.js';

test('the tab bar sits at the bottom of the phone and navigates', async ({ page }) => {
    await signIn(page);
    const tabs = page.getByRole('navigation', { name: 'Main' });
    await expect(tabs).toBeVisible();
    const { y, height } = await tabs.boundingBox();
    expect(y + height).toBeCloseTo(page.viewportSize().height, 0);

    await tabs.getByRole('link', { name: 'Pantry' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Pantry' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
});

test('the account menu fits a phone', async ({ page }) => {
    await signIn(page);
    await page.getByRole('button', { name: 'Account menu' }).click();
    const menu = page.getByRole('menu');
    await expect(menu).toBeVisible();
    const { x, width } = await menu.boundingBox();
    expect(x).toBeGreaterThanOrEqual(0);
    expect(x + width).toBeLessThanOrEqual(page.viewportSize().width);
});

test('the pantry rows and the ingredient sheet fit a phone', async ({ page }) => {
    await signIn(page);
    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Pantry' }).click();
    await expect(page.getByRole('button', { name: 'One more Milk' })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    for (const name of ['One more Milk', 'One less Milk', 'Milk (edit)']) {
        const { width, height } = await page.getByRole('button', { name, exact: true }).boundingBox();
        expect(height, name).toBeGreaterThanOrEqual(44);
        expect(width, name).toBeGreaterThanOrEqual(44);
    }

    await page.getByRole('button', { name: 'Milk (edit)', exact: true }).click();
    const sheet = page.getByRole('dialog', { name: 'Edit ingredient' });
    await expect(sheet).toBeVisible();
    const viewport = page.viewportSize();
    await expect.poll(async () => {
        const { y, height } = await sheet.boundingBox();
        return Math.round(y + height);
    }).toBe(viewport.height);
    const { x, width } = await sheet.boundingBox();
    expect(x).toBe(0);
    expect(width).toBe(viewport.width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();
});

test('the Cook page and the meal sheet fit a phone', async ({ page }) => {
    await signIn(page);
    const card = page.getByTestId('recipe-card').filter({ has: page.getByRole('link', { name: 'Pasta al pomodoro', exact: true }) });
    await expect(card).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    for (const name of ['Cook it: Pasta al pomodoro', 'Log a meal']) {
        const { width, height } = await page.getByRole('button', { name }).boundingBox();
        expect(height, name).toBeGreaterThanOrEqual(44);
        expect(width, name).toBeGreaterThanOrEqual(44);
    }

    await page.getByRole('button', { name: 'Cook it: Pasta al pomodoro' }).click();
    const sheet = page.getByRole('dialog', { name: 'Cook Pasta al pomodoro' });
    await expect(sheet.getByTestId('used-line')).toHaveCount(5);
    const viewport = page.viewportSize();
    await expect.poll(async () => {
        const { y, height } = await sheet.boundingBox();
        return Math.round(y + height);
    }).toBe(viewport.height);
    const { x, width } = await sheet.boundingBox();
    expect(x).toBe(0);
    expect(width).toBe(viewport.width);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    for (const name of ['More Pasta', 'Less Pasta']) {
        const { width: buttonWidth, height: buttonHeight } = await sheet.getByRole('button', { name, exact: true }).boundingBox();
        expect(buttonHeight, name).toBeGreaterThanOrEqual(44);
        expect(buttonWidth, name).toBeGreaterThanOrEqual(44);
    }
    await sheet.getByTestId('submit-meal').scrollIntoViewIfNeeded();
    await expect(sheet.getByTestId('submit-meal')).toBeInViewport();
});

test('the shop screen fits a phone with big tap targets', async ({ page }) => {
    await signIn(page);
    const send = (method, url, body) => page.evaluate(async (request) => {
        const response = await fetch(request.url, { method: request.method, headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: request.body ? JSON.stringify(request.body) : undefined });
        return response.status === 204 ? null : response.json();
    }, { method, url, body });
    if ((await send('GET', '/api/grocery-list')) !== null) await send('POST', '/api/grocery-list/put-away', { bought: [] });
    const ingredients = await send('GET', '/api/ingredients');
    const ids = ['Yoghurt', 'Cheddar', 'Rice', 'Peas'].map((name) => ingredients.find((ingredient) => ingredient.name === name).id);
    await send('POST', '/api/grocery-list', { ingredientIds: ids });

    await page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: 'Groceries' }).click();
    const rows = page.getByTestId('tick-row');
    await expect(rows).toHaveCount(4);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBeTruthy();

    const viewport = page.viewportSize();
    for (const row of await rows.all()) {
        const { x, width, height } = await row.boundingBox();
        expect(height).toBeGreaterThanOrEqual(44);
        expect(x).toBeGreaterThanOrEqual(0);
        expect(x + width).toBeLessThanOrEqual(viewport.width);
    }

    await rows.filter({ hasText: 'Yoghurt' }).click();
    await expect(rows.filter({ hasText: 'Yoghurt' })).toHaveAttribute('aria-checked', 'true');
    await expect(page.locator('section').filter({ has: page.getByRole('heading', { level: 2, name: /^Fridge/ }) }).getByTestId('tick-row').last()).toContainText('Yoghurt');

    const home = page.getByTestId('back-home');
    await home.scrollIntoViewIfNeeded();
    const { height, width } = await home.boundingBox();
    expect(height).toBeGreaterThanOrEqual(44);
    expect(width).toBeGreaterThanOrEqual(44);
    await expect(home).toBeInViewport();

    await send('POST', '/api/grocery-list/put-away', { bought: [] });
});
