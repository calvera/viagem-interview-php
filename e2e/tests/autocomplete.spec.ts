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
