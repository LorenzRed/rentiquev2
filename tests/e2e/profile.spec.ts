import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';

const pixel = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a9e8AAAAASUVORK5CYII=',
  'base64',
);

async function accountServices(page: Page) {
  const headers = {
    'access-control-allow-origin': '*',
    'access-control-allow-headers': '*',
    'access-control-allow-methods': 'GET,POST,PATCH,OPTIONS',
  };
  const email = 'profile-test@example.test';
  const uid = 'rentique-profile-test';
  const now = Math.floor(Date.now() / 1000);
  const encode = (value: object) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const token = `${encode({ alg: 'RS256', typ: 'JWT' })}.${encode({ iss: 'https://securetoken.google.com/rentique-test', aud: 'rentique-test', sub: uid, user_id: uid, auth_time: now, iat: now, exp: now + 3600, email, email_verified: false, firebase: { identities: { email: [email] }, sign_in_provider: 'password' } })}.test-signature`;
  const state = {
    signups: 0,
    registration: {} as Record<string, unknown>,
    profileUpdate: {} as Record<string, unknown>,
    photoUploaded: false,
    user: {
      id: 123,
      full_name: '',
      email,
      phone: null as string | null,
      role: 'customer',
      account_status: 'active',
      registration_completed_at: null as string | null,
      email_verified_at: null,
      profile_image_url: null as string | null,
      created_at: '2026-11-13T00:00:00.000000Z',
    },
  };
  // Test-only service responses: no accounts or emails are created in the live Firebase project.
  await page.route('https://identitytoolkit.googleapis.com/**', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers });
      return;
    }
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('accounts:signUp') || path.endsWith('accounts:signInWithPassword')) {
      state.signups++;
      await route.fulfill({
        headers,
        json: {
          localId: uid,
          email,
          idToken: token,
          refreshToken: 'test-refresh-token',
          expiresIn: '3600',
          registered: true,
        },
      });
    } else if (path.endsWith('accounts:lookup')) {
      await route.fulfill({
        headers,
        json: {
          users: [
            {
              localId: uid,
              email,
              emailVerified: false,
              createdAt: String(Date.now()),
              providerUserInfo: [{ providerId: 'password', email, federatedId: email }],
            },
          ],
        },
      });
    } else if (path.endsWith('accounts:sendOobCode')) {
      await route.fulfill({ headers, json: { email } });
    } else await route.abort();
  });
  await page.route('https://securetoken.googleapis.com/**', (route) => route.abort());
  await page.route('**/api/v1/**', async (route) => {
    if (route.request().method() === 'OPTIONS') {
      await route.fulfill({ status: 204, headers });
      return;
    }
    const path = new URL(route.request().url()).pathname;
    if (path.endsWith('/auth/sync') || path.endsWith('/auth/me')) await route.fulfill({ headers, json: state.user });
    else if (path.endsWith('/auth/complete-registration')) {
      state.registration = route.request().postDataJSON();
      state.user = {
        ...state.user,
        full_name: String(state.registration.full_name),
        role: String(state.registration.account_type),
        registration_completed_at: new Date().toISOString(),
      };
      await route.fulfill({ headers, json: state.user });
    } else if (path.endsWith('/profile')) {
      state.profileUpdate = route.request().postDataJSON();
      state.user = {
        ...state.user,
        full_name: String(state.profileUpdate.full_name),
        phone: String(state.profileUpdate.phone),
      };
      await route.fulfill({ headers, json: state.user });
    } else if (path.endsWith('/profile/photo')) {
      state.photoUploaded = true;
      const origin = new URL(route.request().url()).origin;
      state.user.profile_image_url = `${origin}/api/v1/profiles/123/photo?expires=9999999999&signature=test`;
      await route.fulfill({ headers, json: state.user });
    } else if (path.endsWith('/profiles/123/photo'))
      await route.fulfill({ headers, contentType: 'image/png', body: pixel });
    else await route.abort();
  });
  return state;
}

test('owner registration leads to an editable personal profile', async ({ page }) => {
  const state = await accountServices(page);
  await page.goto('/profile', { waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('radio')).toHaveCount(0);
  await page.getByText('New here? Create an account', { exact: true }).click();
  await page.getByLabel('Full name', { exact: true }).fill('Jordan Cruz');
  await page.getByRole('radio', { name: /Shop Owner/ }).check();
  await page.getByLabel('Email address', { exact: true }).fill('profile-test@example.test');
  await page.getByLabel('Password', { exact: true }).fill('Rentique-test-123!');
  await page.getByLabel('Confirm password', { exact: true }).fill('Rentique-test-123!');
  await page.getByRole('button', { name: 'Create account', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Jordan Cruz', exact: true })).toBeVisible();
  expect(state.registration).toEqual({ full_name: 'Jordan Cruz', account_type: 'owner' });
  await expect(page.getByRole('radio')).toHaveCount(0);
  await expect(page.getByText('Shop Owner', { exact: true }).first()).toBeVisible();
  await page.getByRole('button', { name: 'Edit profile', exact: true }).click();
  await page.getByLabel('Full name', { exact: true }).fill('Jordan Santos');
  await page.getByLabel('Phone number', { exact: true }).fill('+63 917 123 4567');
  await page.getByRole('button', { name: 'Save changes', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Jordan Santos', exact: true })).toBeVisible();
  expect(state.profileUpdate).toEqual({ full_name: 'Jordan Santos', phone: '+63 917 123 4567' });
  await page.screenshot({ path: 'test-results/rentique-profile-owner.png', fullPage: true });
  await page
    .getByLabel('Choose profile photo', { exact: true })
    .setInputFiles({ name: 'avatar.png', mimeType: 'image/png', buffer: pixel });
  await expect(page.getByAltText('Your profile photo', { exact: true })).toBeVisible();
  expect(state.photoUploaded).toBe(true);
  await page.reload({ waitUntil: 'domcontentloaded' });
  await expect(page.getByRole('heading', { name: 'Jordan Santos', exact: true })).toBeVisible();
});

test('customer registration checks passwords before creating an account', async ({ page }) => {
  const state = await accountServices(page);
  await page.goto('/profile', { waitUntil: 'domcontentloaded' });
  await page.getByText('New here? Create an account', { exact: true }).click();
  await page.getByLabel('Full name', { exact: true }).fill('Jamie Cruz');
  await page.getByRole('radio', { name: /Renter \/ Customer/ }).check();
  await page.getByLabel('Email address', { exact: true }).fill('profile-test@example.test');
  await page.getByLabel('Password', { exact: true }).fill('Rentique-test-123!');
  await page.getByLabel('Confirm password', { exact: true }).fill('Different-password!');
  await page.getByRole('button', { name: 'Create account', exact: true }).click();
  await expect(page.getByText('Your passwords do not match.', { exact: true })).toBeVisible();
  await expect(page.getByText('Your passwords do not match.', { exact: true })).toBeInViewport();
  expect(state.signups).toBe(0);
  await page.screenshot({ path: 'test-results/rentique-registration.png', fullPage: true });
  await page.getByLabel('Confirm password', { exact: true }).fill('Rentique-test-123!');
  await page.getByRole('button', { name: 'Create account', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Jamie Cruz', exact: true })).toBeVisible();
  expect(state.registration.account_type).toBe('customer');
  await expect(page.getByText('View my bookings', { exact: true })).toBeVisible();
  await expect(page.getByText('Open shop workspace', { exact: true })).toHaveCount(0);
});
