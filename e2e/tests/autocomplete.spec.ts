import { test, expect } from './fixtures';

test('municipality autocomplete fills the filter', async ({ page }) => {
  await page.goto('/index.php?url=parcels');

  const input = page.getByLabel('Obec');
  await input.pressSequentially('Lh');

  const item = page.getByRole('listitem').filter({ hasText: 'Lhota (okres Habřiny)' });
  await expect(item).toBeVisible();
  await expect(page.locator('ul.autocomplete li')).toHaveCount(15);

  await item.click();
  await expect(input).toHaveValue('Lhota');
});

test('autocomplete shows labels as text, not HTML', async ({ page }) => {
  await page.route(/ajax_municipalities\.php/, (route) =>
    route.fulfill({ json: [{ id: '1', code: '1', value: 'X', label: '<img src=x onerror="window.xss=1">Lhota' }] }),
  );
  await page.goto('/index.php?url=parcels');

  await page.getByLabel('Obec').pressSequentially('Lh');

  const item = page.locator('ul.autocomplete li');
  await expect(item).toHaveText('<img src=x onerror="window.xss=1">Lhota');
  await expect(item.locator('img')).toHaveCount(0);
  expect(await page.evaluate(() => (window as unknown as { xss?: number }).xss)).toBeUndefined();
});
