import { test, expect } from '@playwright/test';

/** REQ-DEMO-013 — SeoKit demo happy path (localized metadata, hreflang switch, sitemap/robots, admin). */
test.describe('SeoKit demo', () => {
  test('home renders title, description, canonical and hreflang alternates', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    await expect(page).toHaveTitle('Home · SEO Kit Demo');
    await expect(page.locator('meta[name="description"]')).toHaveAttribute('content', /SEO Kit FrankenPHP demo/);
    await expect(page.locator('link[rel="canonical"]')).toHaveCount(1);
    await expect(page.locator('link[rel="alternate"][hreflang="es"]')).toHaveAttribute('href', /\/es$/);
    await expect(page.locator('link[rel="alternate"][hreflang="fr"]')).toHaveAttribute('href', /\/fr$/);
  });

  test('language switch follows the localized path and metadata', async ({ page }) => {
    await page.goto('/');
    await page.getByRole('navigation', { name: 'Language' }).getByRole('link', { name: 'ES' }).click();
    await expect(page).toHaveURL(/\/es$/);
    await expect(page).toHaveTitle('Inicio · SEO Kit Demo');
    await expect(page.locator('html')).toHaveAttribute('lang', 'es');
  });

  test('configured slug gets its own localized title and URL', async ({ page }) => {
    await page.goto('/en/blog/hello-world');
    await expect(page).toHaveTitle(/Hello World post/);
    await expect(page.locator('link[rel="alternate"][hreflang="es"]')).toHaveAttribute('href', /\/es\/blog\/hola-mundo$/);
  });

  test('sitemap and robots are generated', async ({ request }) => {
    const sitemap = await request.get('/sitemap.xml');
    expect(sitemap.ok()).toBeTruthy();
    const xml = await sitemap.text();
    expect(xml).toContain('<urlset');
    expect(xml).toContain('/es/blog/hola-mundo');
    const robots = await request.get('/robots.txt');
    expect(robots.ok()).toBeTruthy();
    expect(await robots.text()).toMatch(/Sitemap:/i);
  });

  test('SEO admin lists pages with their localized URLs', async ({ page }) => {
    await page.goto('/admin/seo');
    await expect(page.locator('table').first().getByText('app_home', { exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: /\/es\/about$/ }).first()).toBeVisible();
    await page.locator('tr', { hasText: 'app_about' }).first().getByRole('link', { name: 'Edit' }).click();
    await expect(page.getByText('Resolved SEO URLs')).toBeVisible();
  });
});
