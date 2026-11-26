import { test, expect } from './fixtures';

const pages = [
  { menu: 'Přehled', heading: 'Přehled' },
  { menu: 'Vlastníci', heading: 'Vlastníci pozemků' },
  { menu: 'Parcely', heading: 'Parcely' },
  { menu: 'Obce', heading: 'Obce' },
];

for (const { menu, heading } of pages) {
  test(`menu ${menu} opens its page`, async ({ page }) => {
    await page.goto('/');
    await page.locator('#menu').getByRole('link', { name: menu }).click();

    await expect(page.getByRole('heading', { level: 1 })).toHaveText(heading);
    await expect(page).toHaveTitle(`${menu} | Evidence vlastníků`);
    await expect(page.locator('#menu li.active')).toHaveText(menu);
  });
}

test('unknown module shows 404 page', async ({ page }) => {
  const response = await page.goto('/index.php?url=nope');

  expect(response?.status()).toBe(404);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Stránka nenalezena');

  await page.getByRole('link', { name: 'Zpět na přehled' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Přehled');
});
