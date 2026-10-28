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
