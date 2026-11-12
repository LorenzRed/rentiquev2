import { expect, test } from '@playwright/test';

test('visits the app root url', async ({ page }) => {
  await page.goto('/', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Great moments. Unforgettable style.' })).toBeVisible();
  await page.screenshot({ path: 'test-results/rentique-home-mobile.png', fullPage: true });
  await page.getByText('Find your look', { exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Beautiful finds. Local shops.' })).toBeVisible();
});

test('location denial leaves map browsing available', async ({ page }) => {
  await page.addInitScript(() => {
    Object.defineProperty(navigator, 'geolocation', {
      value: {
        getCurrentPosition: (_success: unknown, failure: (error: { code: number }) => void) => failure({ code: 1 }),
      },
    });
  });
  await page.goto('/map', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.leaflet-container')).toBeVisible();
  await page.getByText('Find nearest shop', { exact: true }).click();
  await expect(
    page.getByText('Unable to get your location. Check permissions or browse the map manually.'),
  ).toBeVisible();
  await expect(page.locator('.leaflet-container')).toBeVisible();
});

test('home adapts to desktop', async ({ page }) => {
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto('/home', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'What are you dressing for?' })).toBeVisible();
  await page.screenshot({ path: 'test-results/rentique-home-desktop.png', fullPage: true });
});
