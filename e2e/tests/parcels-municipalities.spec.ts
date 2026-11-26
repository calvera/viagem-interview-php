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

test('municipalities link to parcels by id', async ({ page }) => {
  await page.goto('/index.php?url=municipalities&search=Lhota');

  const row = page.locator('table.list tbody tr').first();
  const municipality = await row.locator('td:nth-child(2)').innerText();
  const parcels = await row.locator('td:nth-child(5)').innerText();
  const link = row.getByRole('link', { name: 'parcely' });
  const id = (await link.getAttribute('href'))?.match(/f_municipality_id=(\d+)$/)?.[1];
  expect(id).toBeTruthy();
  await link.click();

  // Label instead of the text field, the count equals the "Parcel" column, rows only from this municipality.
  await page.waitForURL((url) => url.searchParams.get('url') === 'parcels' && url.searchParams.get('f_municipality_id') === id);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Parcely');
  const label = page.locator('form.filters .hidden_filter');
  await expect(label).toHaveText(`Obec: ${municipality} ×`);
  await expect(page.getByLabel('Obec')).toHaveValue('');
  await expect(page.locator('.section_info b').first()).toHaveText(parcels);
  const rows = await page.locator('table.list tbody tr td:nth-child(1)').allInnerTexts();
  expect(rows.length).toBeGreaterThan(0);
  expect(new Set(rows)).toEqual(new Set([municipality]));

  // Another filter keeps the municipality (hidden input).
  await page.getByLabel('Druh').selectOption({ label: 'Orná půda' });
  await page.getByRole('button', { name: 'Filtrovat' }).click();
  await page.waitForURL((url) => url.searchParams.get('f_land_type') === 'orná půda' && url.searchParams.get('f_municipality_id') === id);
  await expect(label).toHaveText(`Obec: ${municipality} ×`);
  const filtered = await page.locator('table.list tbody tr').evaluateAll((trs) => trs.map((tr) => [tr.children[0].textContent, tr.children[4].textContent]));
  expect(filtered.length).toBeGreaterThan(0);
  for (const [name, type] of filtered) expect([name, type]).toEqual([municipality, 'Orná půda']);

  // × removes the municipality filter (and the page), the land type stays.
  await label.getByRole('link', { name: '×' }).click();
  await page.waitForURL((url) => !url.searchParams.has('f_municipality_id') && url.searchParams.get('f_land_type') === 'orná půda');
  await expect(label).toHaveCount(0);
  await expect(page.locator('.section_info b').first()).not.toHaveText(parcels);
});
