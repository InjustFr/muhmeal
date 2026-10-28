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
