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
  const link = firstRow.getByRole('link', { name: 'vlastníci' });
  const id = (await link.getAttribute('href'))?.match(/f_municipality_id=(\d+)$/)?.[1];
  expect(id).toBeTruthy();
  await link.click();

  // The link filters by municipality id: label instead of the text field, "V obci" for every row.
  await page.waitForURL((url) => url.searchParams.get('f_municipality_id') === id);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Vlastníci pozemků');
  const label = page.locator('form.filters .hidden_filter');
  await expect(label).toHaveText(`Obec: ${municipality} ×`);
  await expect(page.getByLabel('Obec')).toHaveValue('');
  await expect(page.getByRole('columnheader', { name: 'V obci' })).toBeVisible();
  const municipalities = await page.locator('table.list tbody tr td:nth-child(4)').allInnerTexts();
  expect(new Set(municipalities)).toEqual(new Set([municipality]));
  const inMunicipality = await page.locator('table.list tbody tr td:nth-child(6)').allInnerTexts();
  expect(inMunicipality.length).toBeGreaterThan(0);
  for (const cell of inMunicipality) expect(cell).toMatch(/^(\d+ \(.+ (m²|ha)\)|–)$/);

  // Another filter keeps the municipality (hidden input).
  await page.getByLabel('Typ').selectOption({ label: 'Fyzická osoba' });
  await page.getByRole('button', { name: 'Filtrovat' }).click();
  await page.waitForURL((url) => url.searchParams.get('f_type') === 'F' && url.searchParams.get('f_municipality_id') === id);
  await expect(label).toHaveText(`Obec: ${municipality} ×`);

  // × removes the municipality filter (and the page), the other filters stay.
  await label.getByRole('link', { name: '×' }).click();
  await page.waitForURL((url) => !url.searchParams.has('f_municipality_id') && url.searchParams.get('f_type') === 'F');
  await expect(label).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: 'V obci' })).toHaveCount(0);
});
