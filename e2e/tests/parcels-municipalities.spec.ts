import { test, expect } from './fixtures';

test('parcels filter by land type and link to owner', async ({ page }) => {
  await page.goto('/index.php?url=parcels');
  await page.getByLabel('Druh').selectOption({ label: 'Orná půda' });
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  const types = await page.locator('table.list tbody tr td:nth-child(5)').allInnerTexts();
  expect(types.length).toBeGreaterThan(0);
  expect(new Set(types)).toEqual(new Set(['Orná půda']));

  const owner = page.locator('table.list tbody tr td:nth-child(7) a').first();
  const name = await owner.innerText();
  await owner.click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(name);
});

test('municipalities filter by region and link to owners', async ({ page }) => {
  await page.goto('/index.php?url=municipalities');
  await page.getByLabel('Kraj').selectOption('Západní kraj');
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  const regions = await page.locator('table.list tbody tr td:nth-child(4)').allInnerTexts();
  expect(regions.length).toBeGreaterThan(0);
  expect(new Set(regions)).toEqual(new Set(['Západní kraj']));

  const firstRow = page.locator('table.list tbody tr').first();
  const municipality = await firstRow.locator('td:nth-child(2)').innerText();
  await firstRow.getByRole('link', { name: 'vlastníci' }).click();

  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Vlastníci pozemků');
  await expect(page.getByLabel('Obec')).toHaveValue(municipality);
  await expect(page.getByRole('columnheader', { name: 'V obci' })).toBeVisible();
});
