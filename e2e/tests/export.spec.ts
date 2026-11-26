import { readFile } from 'node:fs/promises';
import { test, expect } from './fixtures';

test('CSV export downloads the filtered owners', async ({ page }) => {
  await page.goto('/index.php?url=owners&search=01256748');

  const downloadPromise = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Export do CSV' }).click();
  const download = await downloadPromise;

  expect(download.suggestedFilename()).toMatch(/^vlastnici_\d{8}_\d{4}\.csv$/);

  const lines = (await readFile(await download.path(), 'utf8')).split('\n');
  expect(lines[0]).toMatch(/^﻿"Export vlastníků";/);
  expect(lines[1]).toBe('ID;Typ;Titul;Jméno;"Příjmení / název";"Datum narození";IČO;Ulice;PSČ;Město;"Obec (KN)";Telefon;E-mail;"Termín oslovení";Založeno');
  expect(lines[2]).toMatch(/^12;"Právnická osoba";;;"Statek Lhota spol. s r.o.";;01256748;/);
});
