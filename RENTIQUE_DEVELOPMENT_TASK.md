# Rentique — Mobile Application Development Task

## Objective

Develop Rentique, a formalwear rental marketplace that connects customers with approved rental shops. Customers must be able to discover nearby shops, check product availability, and manage reservations. Shop owners must be able to register their businesses, confirm their physical locations, manage inventory, and process rentals. Administrators must be able to verify shops and oversee platform activity.

Deliver a working application for web browsers and Android, supported by a secure API and persistent database. Static mockups and simulated successful transactions do not satisfy this task.

## 1. Technical Requirements

| Layer | Required technology and responsibility |
| --- | --- |
| Frontend | Ionic Vue, Vue 3, TypeScript, Vue Router, and Pinia |
| Android | Capacitor, with native location permissions, camera/gallery access, and Android back-button support |
| Authentication | Firebase Authentication for registration, login, password recovery, email verification, and session persistence |
| File storage | Laravel local storage for images and documents, with relative file paths and metadata in MySQL; Firebase remains responsible for authentication |
| Backend | Laravel REST API for authorization, validation, business rules, notifications, scheduled jobs, and reporting |
| Database | MySQL for structured application data and file metadata |
| Identity verification | Firebase Admin SDK for server-side verification of Firebase ID tokens |
| Maps | Leaflet with a Vue 3-compatible integration and OpenStreetMap-based tiles |
| Location | Capacitor Geolocation on Android and browser geolocation on web |
| Optional notifications | Firebase Cloud Messaging through a Capacitor-compatible Android integration |

Firebase must remain the sole credential authentication system. Do not introduce independent Laravel password authentication. Do not use Firestore or Firebase Realtime Database as the main application database. Do not use Google Maps SDK.

Select configurable production tile and geocoding providers that permit the intended usage. Preserve attribution and respect provider rate limits, caching rules, and autocomplete restrictions.

## 2. Roles and Authorization

Support Customer, Shop Owner, and System Administrator roles.

After Firebase authentication, the client must send its ID token in the `Authorization: Bearer` header. Laravel must verify the token, find or provision the corresponding MySQL user by a unique `firebase_uid`, and return the server-controlled role and account status.

Enforce access through Laravel middleware and Policies:

- Customers may access only their own private account, reservation, and payment records.
- Owners may manage only their authorized shops and associated records.
- Administrator endpoints require an administrator role assigned through a trusted administrative process.
- Client requests must never grant administrator privileges or override account restrictions.
- Suspended accounts and shops must be restricted according to documented platform rules.

## 3. Functional Scope

### Customer experience

- Implement registration, login, logout, email verification, password recovery, password changes, profile editing, profile pictures, and account settings through Firebase-supported flows.
- Provide a home dashboard with search, featured formalwear, categories, recommended and nearby shops, recent products, notifications, and promotional banners.
- Include Dresses, Gowns, Suits, Tuxedos, Barong Tagalog, and Other Formalwear categories.
- Provide a dedicated shop directory with name and location search, list/map views, ratings, reviews, contact details, profiles, and shop-specific products.
- Allow product search and filtering by category, size, and price. Show images, descriptions, rental policies, and date-based availability.
- Allow customers to select rental dates, review calculated charges, submit reservations, cancel eligible bookings, and track pickups, returns, payments, and rental history.
- Provide in-app status updates, pickup reminders, return reminders, overdue alerts, and announcements.
- Display only approved, active shops through public directory, product, shop-detail, and map endpoints.

### Maps and nearby shop discovery

- Display an interactive Leaflet map with shop markers and a distinct customer-location marker when permission is granted.
- Provide name/location search and radius filters for 1, 3, 5, 10, and 20 km, plus All Shops.
- Implement **Find Nearest Shop** using approved, active shops with valid coordinates. Sort results by distance and highlight the nearest result.
- Show shop logo, name, address, rating when available, approximate straight-line distance, **View Shop**, and **Get Directions** in marker popups.
- Calculate and validate proximity on the backend using a bounding-box filter followed by Haversine calculations, or suitable spatial queries.
- Clearly label straight-line distance. Open external navigation using the selected shop's coordinates for road directions.
- Refresh results when map bounds change using debounced, bounded requests. Use marker clustering for large result sets.
- Provide manual location search and map browsing when GPS is denied or unavailable. Do not assume a default customer city.
- Handle loading, empty results, permission denial, geolocation timeout, unavailable tiles, and network errors.
- Request location only when needed, explain its purpose, and avoid background tracking or unnecessary customer location history.

### Shop owner experience

- Allow authenticated owners to apply with shop name, owner name, email, contact number, description, business hours, address, province, city/municipality, barangay, logo, cover image, and private verification documents.
- Require a confirmed physical shop location before application submission.
- Provide a Leaflet location picker with address search, manual marker placement, draggable marker, zoom/pan, and **Use My Current Location**.
- Display selected latitude, longitude, and a readable address when available. Require explicit location confirmation; GPS alone must not confirm the shop's physical location.
- Allow manual selection when location permission is denied.
- Keep application details and unapproved locations out of public responses.
- Provide dashboard totals for products, available inventory, reservations, active/overdue/completed rentals, revenue, and upcoming pickups/returns.
- Support product creation, editing, archiving, multiple images, categories, sizes, colors, pricing, deposits, quantities, and maintenance status.
- Support reservation approval/rejection, pickup confirmation, returns, condition photographs, damage records, late fees, and payment recording.
- Allow shop profile and location updates, with an audit trail for important location changes.
- Provide reports for revenue, monthly transactions, popular products, completed/cancelled bookings, inventory utilization, and overdue rentals.

### Administrator experience

- Provide dashboards for customers, owners, shop applications, shop statuses, products, reservations, active rentals, completed rentals, and platform reports.
- Allow application review with owner details, private documents, address, coordinates, and a Leaflet location preview.
- Support shop approval, rejection, suspension, and reactivation, with audit records.
- Support customer search, account suspension/reactivation, activity review, shop monitoring, reservation monitoring, and reported-issue investigation.
- Provide an administrator-only map with distinct approved, pending, and suspended shop markers and status/location filters.
- Provide customer growth, shop registration, reservation, revenue, shop activity, and platform performance reports.
- Support announcements, application settings, activity logs, account management, and security monitoring.

## 4. Reservation, Inventory, and Payment Rules

Support these reservation statuses: Pending, Approved, Rejected, Cancelled, Ready for Pickup, Ongoing, Returned, Completed, and Overdue.

Document permitted status transitions and the roles allowed to perform each transition. Define rental date boundaries, cancellation eligibility, pricing duration, deposits, late fees, damage charges, and completion conditions before implementing the affected workflows.

- Calculate charges on the server and retain the agreed pricing details with each reservation.
- Revalidate inventory when approving a reservation.
- Allocate inventory atomically within database transactions so concurrent approvals cannot double-book the same units.
- Exclude unavailable and maintenance inventory from allocation.
- Make pickup, return, payment, and refund operations safe against duplicate submissions.
- Record cash payments, deposits, balances, receipts, transaction history, and refunds.
- Distinguish rental revenue from refundable deposits in financial reports.
- Prepare payment interfaces for future online providers; live online payment processing is outside the required initial scope.

## 5. Data and API Requirements

Create normalized migrations, relationships, foreign keys, indexes, and appropriate seed data for:

`users`, `shops`, `shop_applications`, `shop_verification_documents`, `product_categories`, `products`, `product_variants`, `product_images`, `inventory_units`, `reservations`, `reservation_items`, `rental_transactions`, `rental_returns`, `payments`, `refunds`, `notifications`, `reviews`, `announcements`, `activity_logs`, `system_settings`, and `device_tokens`.

Include unique Firebase UIDs, server-controlled roles, account/shop status, file paths, timestamps, and ownership relationships. Shops must store latitude and longitude as `DECIMAL(10,7)`, formatted address, province, municipality, barangay, and `location_updated_at`. Validate latitude from -90 to 90 and longitude from -180 to 180.

Expose versioned `/api/v1` routes covering:

| Area | Required operations |
| --- | --- |
| Authentication | `POST /auth/sync`, `GET /auth/me` |
| Public shops | Directory, nearby search, shop details, and shop products |
| Public products | Product listing, details, and date-based availability |
| Customer | Reservation list/create/cancel, profile, payments, history, and notifications |
| Owner | Applications, shop/profile/location updates, products, reservations, approval/rejection, pickup, return, payments, and reports |
| Administrator | Dashboard, applications, approval/rejection, shop status changes, shop map, users, reservations, reports, announcements, and settings |

Preserve the route structure proposed in the original brief where applicable. Register `/shops/nearby` so it cannot be captured as a shop identifier.

Nearby search must validate coordinates, radius, bounds, and query limits; return bounded or paginated results with shop identity, coordinates, address, logo reference, and distance in kilometers. Apply public visibility rules on the server.

Document request/response schemas, authentication requirements, filters, pagination, validation errors, and authorization failures.

## 6. File Storage and Security

Organize storage under `rentique/users/{uid}/profile`, `rentique/shops/{shopId}/logo`, `cover`, and `products/{productId}`, `rentique/verification/{shopId}`, and `rentique/rentals/{reservationId}/condition-photos`.

- Store file references and metadata in MySQL.
- Validate file type and size, generate safe filenames, and control overwrite/deletion permissions.
- Protect verification documents and condition photographs using trusted authorization data or controlled backend access.
- Never treat a shop ID in a storage path as proof of ownership.
- Avoid publicly reusable download links for private documents.
- Enforce file access through Laravel authorization and expiring document links; include a safe orphan-file cleanup strategy.
- Use HTTPS, API rate limiting, validated input, protected secrets, safe error responses, and audit logs.

## 7. Interface and Navigation

Use real Ionic components with responsive, mobile-first layouts, consistent spacing, accessible labels and contrast, touch-friendly controls, loading indicators, useful empty states, error recovery, Android safe areas, and practical light/dark mode support.

Suggested visual palette: Deep Plum `#512B46`, Champagne Gold `#C6A76A`, Warm White `#FAF8F6`, White `#FFFFFF`, and Charcoal `#242124`.

| Role | Navigation |
| --- | --- |
| Customer | Home · Shops · Map · Bookings · Profile |
| Shop Owner | Dashboard · Products · Reservations · Shop · Profile |
| Administrator | Dashboard · Shops · Users · Reports · Settings |

Create reusable map components: `ShopMap.vue`, `ShopMarker.vue`, `LocationPicker.vue`, `NearbyShopsMap.vue`, `ShopLocationPreview.vue`, and `ShopMapPopup.vue`.

Separate geolocation and map behavior into `src/services/geolocationService.ts` and `src/services/mapService.ts`. Separate Firebase configuration, authentication services, and authentication state into dedicated modules.

Handle Leaflet CSS and Vite marker assets, Ionic page visibility/resizing, duplicate initialization, event cleanup, touch gestures, and Android WebView behavior.

## 8. Implementation Plan and Deliverables

Inspect the existing repository before making changes. Reuse working code and document architecture gaps rather than replacing the project without cause.

| Phase | Deliverable | Completion evidence |
| --- | --- | --- |
| 1. Foundation | Architecture, repository structure, environment templates, Ionic/Vue setup, routing, Pinia, and Capacitor configuration | Frontend builds; setup instructions are reproducible |
| 2. Identity and API | Firebase authentication, Laravel token verification, user synchronization, MySQL migrations, role/ownership Policies | Valid login works; invalid tokens and unauthorized access are rejected |
| 3. Shop onboarding | Owner application, private uploads, confirmed location picker, and administrator review | Approval makes a shop publicly visible; pending/suspended shops remain hidden |
| 4. Discovery | Customer dashboard, directory, product browsing, maps, nearby search, and external directions | Browser and Android flows work with GPS granted and denied |
| 5. Inventory and rentals | Product/variant/unit management, availability, reservations, pickup, return, and overdue processing | Concurrent approvals cannot double-book inventory |
| 6. Payments and reporting | Cash/deposit/refund records, receipts, history, and role-specific reports | Totals reconcile with stored transactions and deposits |
| 7. Notifications and administration | In-app notifications, scheduled reminders, announcements, activity logs, and settings | Scheduled work runs without duplicate notifications |
| 8. Release validation | Automated critical-path tests, browser/device verification, Android build configuration, and handover documentation | Debug APK installs; release signing procedure is documented and validated when credentials are available |

Optional Firebase push notifications may follow the core in-app notification implementation.

## 9. Acceptance Criteria

The task is complete when:

1. Firebase registration/login persists correctly, and Laravel consistently enforces account status, roles, and ownership.
2. An owner can submit a complete application with private documents and an explicitly confirmed map location.
3. An administrator can inspect documents and location, approve/reject applications, and suspend/reactivate shops.
4. Public search, details, product listings, and maps expose only approved, active shops.
5. Customers can discover nearby shops, use each distance filter, select markers, open shop profiles, and launch external directions.
6. Map browsing and manual location selection remain usable without GPS permission.
7. Availability is date-based and enforced by the backend; concurrent approvals cannot allocate the same inventory twice.
8. Authorized users can complete the reservation-to-return workflow, including payments, deposits, fees, receipts, refunds, and history as applicable.
9. Unauthorized users cannot retrieve another user's private records, another owner's management records, private files, or administrator data.
10. Critical authentication, authorization, shop visibility, proximity, inventory concurrency, rental lifecycle, and payment workflows have passing automated tests.
11. Browser and physical Android-device checks cover authentication, location permission behavior, maps, uploads, navigation, and network errors.
12. Documentation includes setup, environment variables, database migration/seeding, API contracts, local file storage/access controls, scheduler/queue setup, tests, and Android builds.
13. Debug APK delivery and signed release APK delivery are completed when the required Android toolchain, Firebase project configuration, and signing credentials are supplied. Any unavailable dependency is explicitly recorded with reproducible completion steps.

## 10. Execution and Handover Expectations

Implement actual working code phase by phase and validate each completed milestone. Keep secrets out of source control. Report completed functionality, verification results, unresolved defects, and external configuration dependencies accurately.

Before implementing dependent business rules, record decisions for pricing, date boundaries, cancellation/refund policies, shop ownership cardinality, review eligibility, recommendation logic, report definitions, and notification timing. Do not silently invent financial or operational policies.

Deliver source code, migrations, security rules, API documentation, automated tests, configuration templates, Android build artifacts where feasible, and a concise handover guide.
