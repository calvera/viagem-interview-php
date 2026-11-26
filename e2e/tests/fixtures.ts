import { test as base, expect } from '@playwright/test';

const PHP_ERRORS = ['Fatal error:', 'Parse error:', 'Warning:', 'Deprecated:', 'Notice:', 'Uncaught '];

/**
 * Test with an automatic check after each test: no error in the browser console
 * and no PHP error message in the page body.
 */
export const test = base.extend<{ errorGuard: void }>({
  errorGuard: [
    async ({ page }, use) => {
      const errors: string[] = [];
      page.on('pageerror', (error) => errors.push(error.message));
      page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        // The 404 page itself is expected; its own load error is not a bug.
        if (msg.text().includes('status of 404') && msg.location().url === page.url()) return;
        errors.push(msg.text());
      });

      await use();

      const body = await page.locator('body').innerText();
      for (const error of PHP_ERRORS) expect(body, 'PHP error in page').not.toContain(error);
      expect(errors, 'browser console errors').toEqual([]);
    },
    { auto: true },
  ],
});

export { expect };
