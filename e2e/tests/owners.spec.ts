import type { Page } from '@playwright/test';
import { test, expect } from './fixtures';

const heading = (page: Page) => page.getByRole('heading', { level: 1 });
const info = (page: Page) => page.locator('.section_info');

test('list has table and pagination', async ({ page }) => {
  await page.goto('/index.php?url=owners');

  await expect(heading(page)).toHaveText('Vlastníci pozemků');
  await expect(page.getByRole('columnheader', { name: 'Vlastník' })).toBeVisible();
  await expect(page.locator('table.list tbody tr')).toHaveCount(50);
  await expect(page.getByRole('link', { name: 'další ›' })).toBeVisible();
});

test('filter by type shows only companies', async ({ page }) => {
  await page.goto('/index.php?url=owners');
  await page.getByLabel('Typ').selectOption('P');
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  await expect(page).toHaveURL(/f_type=P/);
  await expect(page.getByLabel('Typ')).toHaveValue('P');
  const types = await page.locator('table.list tbody tr td:nth-child(2)').allInnerTexts();
  expect(types.length).toBeGreaterThan(0);
  expect(new Set(types)).toEqual(new Set(['Právnická osoba']));
});

test('filter by municipality adds column "V obci"', async ({ page }) => {
  await page.goto('/index.php?url=owners&search=01256748');
  await page.getByLabel('Hledat').fill('');
  await page.getByLabel('Obec').fill('Lhota');
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  await expect(page).toHaveURL(/f_municipality=Lhota/);
  await expect(page.getByRole('columnheader', { name: 'V obci' })).toBeVisible();
});

test('one-letter municipality is ignored with a note', async ({ page }) => {
  await page.goto('/index.php?url=owners');
  await page.getByLabel('Obec').fill('x');
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  await expect(page).toHaveURL(/f_municipality=x/);
  await expect(page.getByLabel('Obec')).toHaveValue('x');
  await expect(page.locator('.filter_note')).toHaveText('zadejte aspoň 2 znaky');
  await expect(info(page)).toContainText('Nalezeno 500 000 vlastníků');
  await expect(page.getByRole('columnheader', { name: 'V obci' })).toHaveCount(0);
});

test('search by IČO finds the company', async ({ page }) => {
  await page.goto('/index.php?url=owners');
  await page.getByLabel('Hledat').fill('01256748');
  await page.getByRole('button', { name: 'Filtrovat' }).click();

  await expect(info(page)).toHaveText('Nalezeno 1 vlastníků');
  await expect(page.getByRole('link', { name: 'Statek Lhota spol. s r.o.' })).toBeVisible();
  await expect(page.getByRole('cell', { name: 'IČO 01256748' })).toBeVisible();
});

test('sorting by "Založeno" toggles the arrow', async ({ page }) => {
  await page.goto('/index.php?url=owners');

  await page.getByRole('link', { name: 'Založeno' }).click();
  await expect(page.getByRole('link', { name: 'Založeno ▲' })).toBeVisible();

  await page.getByRole('link', { name: 'Založeno ▲' }).click();
  await expect(page.getByRole('link', { name: 'Založeno ▼' })).toBeVisible();
});

test('"další ›" opens page 2', async ({ page }) => {
  await page.goto('/index.php?url=owners');
  await page.getByRole('link', { name: 'další ›' }).click();

  await expect(page).toHaveURL(/page=2/);
  await expect(info(page)).toContainText(', strana 2 z ');
  await expect(page.locator('.pagination b')).toHaveText('2');
});

test('owner detail and edit form', async ({ page }) => {
  await page.goto('/index.php?url=owners&search=01256748');
  await page.getByRole('link', { name: 'Statek Lhota spol. s r.o.' }).click();

  await expect(heading(page)).toHaveText('Statek Lhota spol. s r.o.');
  await expect(page).toHaveTitle('Statek Lhota spol. s r.o. | Evidence vlastníků');
  await expect(page.getByRole('row', { name: 'IČO 01256748' })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: /^Parcely \(\d+\)$/ })).toBeVisible();
  await expect(page.getByRole('heading', { level: 2, name: /^Poznámky \(\d+\)$/ })).toBeVisible();

  await page.getByRole('link', { name: 'Upravit údaje' }).click();

  await expect(heading(page)).toHaveText('Úprava vlastníka');
  await expect(page.locator('input[name="last_name"]')).toHaveValue('Statek Lhota spol. s r.o.');
  await expect(page.getByRole('button', { name: 'Uložit' })).toBeVisible();
});

test('person detail and edit form', async ({ page }) => {
  await page.goto('/index.php?url=owners/detail&id=1');

  await expect(heading(page)).toHaveText('Jana Svobodová');
  await expect(page.getByText('Vlastník je fyzická osoba')).toBeVisible();

  await page.getByRole('link', { name: 'Upravit údaje' }).click();
  await expect(page.locator('input[name="first_name"]')).toHaveValue('Jana');
  await expect(page.locator('input[name="last_name"]')).toHaveValue('Svobodová');
});
