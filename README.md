# Rentique

Ionic Vue formalwear rental application with a Laravel API, Firebase identity, local Laravel file storage, MySQL schema, and Leaflet maps.

## Local setup

Requirements: Node.js compatible with Vite 8, PHP 8.3+, Composer, and MySQL. Android builds additionally require the JDK and Android SDK supported by the installed Capacitor version.

```powershell
npm install
Copy-Item .env.example .env.local
npm run dev
```

Set `VITE_API_URL` to the Laravel `/api/v1` URL and fill the Firebase web application settings in `.env.local`. Enable Firebase Email/Password Authentication and configure authorized domains. Without configuration, account and shop requests show errors; the app does not invent users, shops, or successful bookings.

```powershell
Set-Location backend
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Create the `rentique` MySQL database and configure `DB_*` before migrating. The initial generated local environment uses SQLite for development; automated PHP tests use an isolated in-memory SQLite database. MySQL remains the deployment target and must be used to validate concurrent production booking behavior.

Set `FIREBASE_PROJECT_ID` and `FIREBASE_CREDENTIALS` in `backend/.env` for authentication. The credentials value is an absolute path to a Firebase service-account JSON file stored outside the application directory and source control. Never place service-account credentials in a `VITE_*` variable. Configure `FRONTEND_ORIGINS` for the exact browser and Capacitor origins in use.

Uploads use Laravel's `local` disk at `backend/storage/app/private/rentique/`. The database stores relative file paths and metadata. Approved shop and product images are served through API endpoints; verification documents require owner/admin authorization to obtain a five-minute signed link. Firebase Storage, its billing plan, and `storage:link` are not required. Back up this storage directory together with the database. The existing `storage.rules` file is a legacy safeguard for any unused Firebase bucket and is not used by local uploads.

`GEOCODING_URL` optionally points to an authorized GeoJSON geocoder compatible with `q` and `limit` query parameters. Address search is button-triggered and rate-limited. Without a provider, manual map selection remains available. Configure `VITE_MAP_TILE_URL` with a provider appropriate for deployment traffic and preserve its required attribution. The OpenStreetMap default is for local development, subject to its usage policy.

## Implemented application flows

- Customer home, shop directory, Leaflet map, location permissions, distance filters, external directions, product search, date availability, reservation requests, account forms, and booking history.
- Owner shop applications with confirmed draggable map positions, an ownership-protected workspace, product/inventory creation, reservation actions, cash payments, and reporting APIs.
- Administrator shop approval/suspension/reactivation and user suspension/reactivation through protected endpoints.
- Server verification of Firebase ID tokens, server-controlled roles, normalized marketplace schema, private verification uploads/downloads, activity logs, and in-app reservation notifications.
- Scheduled pickup, return, and overdue reminders with daily duplicate prevention.
- Registration includes full name and a Customer/Shop Owner choice. The choice is saved once on the server; shop approval remains separate. Existing accounts keep their roles when the profile migration runs.
- The signed-in Profile tab provides editable name/phone, a locally stored profile photo, email verification status, verification resend/refresh, password reset, and role-appropriate account links.

## Rental rules

- Pickup and return dates are both billable, as confirmed by the project owner. June 1–3 costs three daily rental charges.
- Prices are stored as integer Philippine centavos. Refundable deposits are separate from rental revenue.
- Pending requests do not guarantee inventory. Approval rechecks inventory and allocates a physical unit inside a transaction.
- Approved rental date ranges are inclusive. Ongoing and overdue items remain blocked until returned.
- The current cancellation policy permits customer cancellation of pending requests only. Changes to cancellation fees, refunds, and completion/balance requirements require a defined business policy.
- Payments use idempotency keys. Owners manually record cash; no online payment processor is connected.

## API overview

All routes begin with `/api/v1`. Run `php artisan route:list --path=api` from `backend` for the authoritative list.

Public: `GET /shops`, `/shops/nearby`, `/shops/{id}`, `/products`, `/products/{id}`, `/products/{id}/availability`, `/categories`, `/geocode`.

Authenticated requests require `Authorization: Bearer <Firebase ID token>`. Identity synchronization is `POST /auth/sync`; identity details are `GET /auth/me`.

Complete new-account registration with `POST /auth/complete-registration` using `full_name`, optional `phone`, and `account_type` (`customer` or `owner`). This endpoint is one-time and cannot grant administrator access. Update personal details with `PATCH /profile`; upload a profile image with multipart `POST /profile/photo` (`photo`, maximum 5 MB). Profile images use five-minute signed local URLs. Email identity and verification remain controlled by Firebase; profile edits cannot change role, email, UID, or account status.

Customer reservations: list/create at `/customer/reservations`, cancel with `PATCH /customer/reservations/{id}/cancel`.

Owner: `/owner/shops`, `/owner/shop-applications`, `/owner/shops/{id}/location`, `/owner/products`, `/owner/reservations`, `/owner/reports`. Reservation action endpoints use `PATCH /owner/reservations/{id}/{approve|reject|ready|pickup|return|complete}`. Cash recording uses `POST /owner/reservations/{id}/payments` with `amount_centavos`, `kind`, and a UUID `idempotency_key`.

Files: `POST /owner/shops/{id}/files` accepts multipart `file`, `kind`, and `product_id` when required. Verification document metadata is available only to the owner or administrator at `/shops/{id}/documents`. `/documents/{id}` returns an authorized short-lived link.

Administrator: `/admin/shops`, `/admin/shops/{id}/status`, `/admin/users`, and `/admin/users/{id}/status`. Administrator roles must be assigned by a trusted database operator; registration cannot grant that role.

Lists use Laravel pagination (`data`, pagination metadata). Validation failures return HTTP 422, missing identity 401, forbidden operations 403, unavailable public records 404, conflicting rental actions 409, and missing external service configuration 503.

## Checks

```powershell
npm run build
npm run lint
npm run test:unit
npm run test:e2e
Set-Location backend
php artisan test
php vendor/bin/pint --dirty --format agent
```

Browser tests use Playwright's Mobile Chrome profile. If its bundled browser is unavailable, install it using `npx playwright install chromium`, or set `$env:PLAYWRIGHT_CHANNEL='chrome'` to use an installed Chrome. Live identity, GPS, and uploads additionally need Firebase configuration and physical Android verification. SQLite tests validate conflicts sequentially; they do not prove concurrent MySQL locking behavior.

## Scheduled tasks

Run `php artisan schedule:work` locally. Configure production's scheduler to invoke `php artisan schedule:run` every minute. `rentique:reminders` runs hourly; it marks overdue rentals and sends each applicable reminder once per day. Dates currently use Laravel's configured application timezone; set it to the business timezone before deployment.

## Android

```powershell
npm run build
npx cap add android
npx cap sync android
npx cap open android
```

The Android platform has already been added to this repository; skip `cap add android` for this checkout. Fine/coarse location permissions are declared. Use a reachable HTTPS API host rather than desktop `localhost` on a physical phone and configure Firebase authorized origins. Build debug or signed release APKs using Android Studio. Signing keys and Firebase credentials must remain outside source control.

## Remaining release work

The source is an initial working implementation, not a production release. Remaining work includes complete profile/settings editing, review submission/moderation, full inventory editing, refund/receipt workflows, advanced reports and announcements/settings interfaces, polished role-specific navigation, map clustering and bounds-driven discovery, push notifications, dark mode, production cleanup/retention policies, signed Android builds, and live-service/device testing. Shop and product images use local storage and appear in the catalog. Shop verification documents can be uploaded securely; approval policy must define the required evidence before enforcing a document checklist.

See `RENTIQUE_DEVELOPMENT_TASK.md` for the full target scope and acceptance criteria.
