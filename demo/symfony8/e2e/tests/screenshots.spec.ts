import { test, expect } from '@playwright/test';
import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { waitForWebProfiler } from './helpers';

/** REQ-DEMO-013 — README screenshot (captured as PNG, converted to WebP by scripts/to-webp.mjs). */
const outDir = process.env.SCREENSHOT_DIR
  ? resolve(process.env.SCREENSHOT_DIR)
  : resolve(__dirname, '../../../../docs/images/demo');

test.beforeAll(() => {
  mkdirSync(outDir, { recursive: true });
});

test.describe('SeoKit screenshots', () => {
  test.use({ viewport: { width: 1280, height: 1000 } });

  test('seo-admin — pages, slug routes and slugs with localized URLs', async ({ page }) => {
    await page.goto('/admin/seo');
    await expect(page.locator('table').first().getByText('app_home', { exact: true })).toBeVisible();
    await waitForWebProfiler(page);
    await page.screenshot({ path: resolve(outDir, 'seo-admin.png') });
  });
});
