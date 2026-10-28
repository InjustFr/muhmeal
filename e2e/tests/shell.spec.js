import { expect, test } from './support/test.js';
import { openAccountMenu, SAM, signIn, signInWithAccount, unique } from './support/session.js';

const TABS = [
    { name: 'Cook', path: '/' },
    { name: 'Pantry', path: '/pantry' },
    { name: 'Recipes', path: '/recipes' },
    { name: 'History', path: '/history' },
];

test('signed-out visitors sign in with their mossyleaf account and land on Cook', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveURL(/\/authorize\?/);
    await signInWithAccount(page, 'demo');
    await page.waitForURL('/');
    await expect(page.getByRole('heading', { level: 1, name: 'Cook' })).toBeVisible();
    for (const tab of TABS) {
        await expect(page.getByRole('navigation', { name: 'Main' }).getByRole('link', { name: tab.name })).toBeVisible();
    }
});

test('every tab opens its page', async ({ page }) => {
    await signIn(page);
    const tabs = page.getByRole('navigation', { name: 'Main' });
    for (const tab of TABS) {
        await tabs.getByRole('link', { name: tab.name }).click();
        await expect(page).toHaveURL(tab.path);
        await expect(page.getByRole('heading', { level: 1, name: tab.name })).toBeVisible();
        await expect(tabs.getByRole('link', { name: tab.name })).toHaveClass(/shell__tab--active/);
    }
});

test('the tabs form a rail on a desktop', async ({ page }) => {
    await signIn(page);
    const tabs = page.getByRole('navigation', { name: 'Main' });
    const cook = await tabs.getByRole('link', { name: 'Cook' }).boundingBox();
    const pantry = await tabs.getByRole('link', { name: 'Pantry' }).boundingBox();
    expect(pantry.y).toBeGreaterThan(cook.y);
    expect(pantry.x).toBe(cook.x);
});

test('a new mossyleaf account joins the kitchen and shows in the session', async ({ page }) => {
    const account = unique('fern').replace(' ', '-');
    await page.goto('/login');
    await signInWithAccount(page, account, { email: `${account}@mossyleaf.test`, name: 'Fern' });
    await page.waitForURL('/');
    const menu = await openAccountMenu(page);
    await expect(menu).toContainText('Fern');
    await expect(menu).toContainText(`${account}@mossyleaf.test`);
    const session = JSON.parse(await page.locator('#app-session').textContent());
    expect(session.members.map((member) => member.name)).toEqual(expect.arrayContaining(['Demo', 'Sam', 'Fern']));
    expect(session.members.find((member) => member.name === 'Fern').id).toBe(session.userId);
});

test('the second account of the kitchen signs in too', async ({ page }) => {
    await signIn(page, SAM);
    const menu = await openAccountMenu(page);
    await expect(menu).toContainText('Sam');
});

test('the credits list the fonts, icons and libraries', async ({ page }) => {
    await signIn(page);
    const menu = await openAccountMenu(page);
    await menu.getByRole('menuitem', { name: 'Credits' }).click();
    await expect(page).toHaveURL('/credits');
    await expect(page.getByRole('heading', { level: 1, name: 'Credits' })).toBeVisible();
    for (const name of ['Fredoka', 'Nunito', 'Phosphor Icons', 'Reka UI', 'Vue', 'Vue Router', 'Vue I18n']) {
        await expect(page.getByRole('link', { name, exact: true })).toBeVisible();
    }
});

test('signing out also signs out of the mossyleaf account', async ({ page }) => {
    await signIn(page);
    const menu = await openAccountMenu(page);
    await menu.getByRole('menuitem', { name: 'Sign out' }).click();
    await expect(page.locator('input[name="username"]')).toBeVisible();
    await expect(page).toHaveURL(/\/authorize\?/);
});

test('crawlers are asked to stay away', async ({ page, request }) => {
    const robots = await request.get('/robots.txt');
    expect(robots.ok()).toBeTruthy();
    expect(await robots.text()).toBe('User-agent: *\nDisallow: /\n');

    await signIn(page);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute('content', 'noindex, nofollow');
    const shell = await page.request.get('/');
    expect(shell.headers()['x-robots-tag']).toBe('noindex, nofollow, noarchive');
    expect(shell.headers()['content-security-policy']).toContain("script-src 'self'");
});
