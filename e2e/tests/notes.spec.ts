import { test, expect } from './fixtures';

test('cancelling the delete dialog keeps the note', async ({ page }) => {
  await page.goto('/');
  const row = page.getByRole('row').filter({ has: page.getByRole('link') }).first();
  const noteText = await row.getByRole('cell').nth(2).innerText();
  await row.getByRole('link').click();
  await page.waitForLoadState('load');

  const note = page.locator('.note').filter({ hasText: noteText }).first();
  await expect(note).toBeVisible();
  const url = page.url();

  // Safety net: no POST (delete) may reach the app, even if the confirm handler is broken.
  let deleteRequested = false;
  await page.route('**/*', (route) => {
    if (route.request().method() !== 'POST') return route.continue();
    deleteRequested = true;
    return route.abort();
  });

  let message = '';
  page.once('dialog', async (dialog) => {
    message = dialog.message();
    await dialog.dismiss();
  });
  await note.getByRole('button', { name: 'smazat' }).click();

  expect(message).toBe('Opravdu smazat poznámku?');
  expect(deleteRequested).toBe(false);
  expect(page.url()).toBe(url);
  await expect(note).toBeVisible();
  expect(deleteRequested).toBe(false);
});
