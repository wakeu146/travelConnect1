# TravelConnect

TravelConnect is a Laravel marketplace connecting travelers with verified travel agencies. Travelers can discover agencies, search by destination or specialty, save agencies, view agency travel experiences, and submit moderated ratings and reviews.

This README is also the continuation handoff for future AI agents. Read the current-state and next-work sections before changing the architecture.

## Current features

- Traveler registration, login, logout, and profile photo upload
- Traveler dashboard with live saved-agency, inquiry, and review data
- Traveler "My bookings" section with owner-scoped reservations, status/payment summaries, and in-page booking details
- In-profile booking dialog for published agency packages, with capacity-checked checkout and localized validation/success dialogs
- Verified agency directory with popular-agency ranking
- Search by agency name, description, destination, or service with case-insensitive partial matching
- Default directory shows the three most popular verified agencies
- Country searches return every matching verified agency
- Save and unsave agencies with persistent database state
- Saved Agencies page with real agency images, ratings, and all destinations
- Agency detail pages with multiple destinations, trust signals, ratings, and reviews
- Real inquiry submission to agency owners and unread-activity notification counts
- Traveler reviews require a completed booking and are limited to one review per booking
- Agency-owned travel experience galleries backed by the database
- Agency package, departure, booking, customer, review, payment-status, and analytics workspace
- Traveler-facing agency profiles show only published packages with at least one active departure after today
- Past departures expire automatically and are hidden from traveler profile and booking flows
- Agency availability totals separate current reservations from completed travelers
- Public agency profiles keep agency email and phone private
- Dashboard brand marks are static and do not navigate when clicked
- Package destinations are selected from the owning agency's configured countries and validated server-side
- Owner-scoped icon deletion for saved agencies, packages and package images, eligible verification documents, closed inquiries, notifications, and agency travel experiences
- Local traveler-experience images stored under `public/images/traveler_experience`
- Traveler review submission with 1-5 rating, comment validation, and pending moderation
- English/French locale switching using the `?lang=en` or `?lang=fr` query parameter
- Responsive account shell with mobile navigation, notification bell, and persistent light/dark theme

## Technology

- PHP 8.3+
- Laravel 13
- MySQL (configured through `.env`)
- Blade views
- Tailwind CSS 4
- Vite
- PHPUnit

## Admin authentication and security

Admins are existing `users` records with the `admin` role. They use the shared email/password login form; there is no web-based admin creation, Google sign-in, magic-link login, or API-token login. The `role` attribute is not mass assignable.

### Login and second factors

- A correct admin password starts a pending login but does not authenticate the session. The session is authenticated and its ID regenerated only after second-factor verification.
- Admins without authenticator enrollment receive a six-digit email code. Codes are stored hashed, expire after 60 seconds, allow at most five attempts, and email resend is rate limited.
- After email verification, an unenrolled admin is offered authenticator enrollment. Enrollment is optional; skipping continues to the dashboard for that session, and setup is offered again after a later email-code login.
- TOTP enrollment uses a locally rendered QR code. The secret is encrypted at rest. Once enrolled, login requires an authenticator code; email-code fallback is not available. TOTP time-step reuse is rejected.
- Ten hashed recovery codes are shown once after enrollment. Each code can be used once. If the admin loses the authenticator and recovery codes, use `admin:reset` to clear enrollment and recover through email verification.
- Authenticator codes cannot be resent. Email-code users have a resend action; TOTP users can use one unused recovery code instead.

### Password, session, and device protections

- Five failed admin password attempts in a rolling 15-minute window temporarily lock the account for 15 minutes. The admin receives a security email; attempts during lockout are rejected even if the password is correct. Login failures keep the same generic credential error.
- Admin remember-me is ignored. The remember token is rotated when an admin login starts.
- Admin routes require a fully verified session and enforce a 20-minute idle timeout by default.
- After a complete login, the application issues a random trusted-device cookie (`HttpOnly`, `SameSite=Lax`, `Secure` in production) and stores only the token hash. The default cookie lifetime is 90 days.
- Alerts are sent for an accepted password from an unrecognized device, a successful login from a new device/IP, and a burst of failed attempts. Login events record the email, IP, user agent, outcome, failed step, and device hash.
- Login-event retention defaults to 180 days. `admin:prune-login-events` runs daily at 01:00.
- `TRUSTED_PROXIES` configures which proxies may supply forwarded client IP headers. Set it to the actual proxy IPs/CIDRs in production; do not trust arbitrary forwarded headers.

### Admin commands and configuration

Create the initial admin interactively; the password is entered through a hidden prompt and must be at least 12 characters:

```powershell
php artisan admin:create
```

Reset an existing admin's email and password interactively:

```powershell
php artisan admin:reset
```

Reset rotates the remember token and session version, removes pending login challenges, TOTP enrollment/recovery hashes and trusted devices, invalidates database-backed sessions, and writes an audit log entry. Never pass admin passwords as command-line arguments or commit credentials.

Relevant environment settings are listed in `.env.example`, including `ADMIN_LOGIN_CODE_EXPIRES_MINUTES`, `ADMIN_LOGIN_CODE_MAX_ATTEMPTS`, `ADMIN_PASSWORD_LOCKOUT_ATTEMPTS`, `ADMIN_PASSWORD_LOCKOUT_MINUTES`, `ADMIN_IDLE_TIMEOUT_MINUTES`, `ADMIN_TRUSTED_DEVICE_DAYS`, and `ADMIN_LOGIN_EVENT_RETENTION_DAYS`. Configure a real mail transport for deployed environments; the example uses the `log` mailer. Keep `APP_KEY` stable and backed up: it encrypts TOTP secrets.

Admin routes are registered from `routes/admin.php` under `/admin`. The dashboard remains a placeholder; no admin moderation, verification queue, user management, or reporting features are implemented.

The additive migrations are `2026_10_06_094104_create_admin_security_tables.php` and `2026_10_06_115313_create_admin_totp_credentials_table.php`. They add admin security tables and a defaulted session-version column; they do not delete traveler, agency, or booking data. Review pending migrations before deploying: this repository also has older unrelated migrations that should not be applied blindly.

Implementation entry points are `routes/admin.php`, `app/Http/Controllers/Admin`, `app/Services/ConfiguredAdminSecondFactor.php`, `app/Services/TotpAdminSecondFactor.php`, `app/Services/EmailAdminSecondFactor.php`, `app/Models/AdminTotpCredential.php`, and `resources/views/admin/auth`. Focused verification commands:

Latest verification on 2026-10-06: the full suite passed 140 tests with 939 assertions; Composer audit was clean; Blade caching and the production asset build succeeded. `php artisan route:list` is still blocked by the unrelated missing `TravelerConversationController` reference noted below.

```powershell
php artisan test --compact tests/Feature/AdminConsoleCommandsTest.php tests/Feature/AdminTotpAuthenticationTest.php tests/Feature/AgencyDashboardTest.php tests/Feature/PasswordRecoveryTest.php
php artisan view:cache
composer audit --locked
npm run build
```

## Current implementation state

TravelConnect is an active Laravel marketplace with separate traveler and agency workspaces. The main discovery, booking, inquiry, verification, and review flows use database-backed records and role-scoped routes. Treat the current dashboard and booking behavior below as the continuation baseline.

### Marketplace implementation baseline

The following focused checks passed during the departure-expiry, booking-review, and dashboard updates:

```powershell
php artisan test --compact tests/Feature/AgencyBookingManagementTest.php tests/Feature/TravelerDashboardTest.php tests/Feature/AgencyDashboardTest.php tests/Feature/AgencyOperationsTest.php
php artisan test --compact tests/Feature/TravelerDashboardTest.php
php artisan schedule:list --no-interaction
vendor/bin/pint --dirty --format agent
```

These results describe the earlier marketplace/departure work and are retained as historical context. For the current full-suite result, run `php artisan test --compact` after your changes.

### Active session handoff

- **Agency workspace:** `GET /agency/dashboard` uses the `x-agency-shell` and links to package, availability, booking, customer, review, payment, analytics, profile, and inquiry management. The overview keeps compact summaries; detailed work belongs in each dedicated section.
- **Agency packages:** agencies can create, edit, publish, archive, and soft-delete packages. Package destinations are a required selection from the countries linked to that agency; server-side validation rejects destinations outside that list. Public agency profiles show only published packages with active departures after today; packages with no upcoming departures are hidden.
- **Traveler bookings:** `GET /account/bookings` is the traveler-only “My bookings” page. It scopes records to the signed-in user and shows reference, package, agency, departure, status, travelers, total, and payment state. “View booking” opens a details dialog; pending and confirmed records retain the existing cancel action. Departures dated today or earlier are hidden and rejected by the booking service.
- **Booking from an agency profile:** package booking opens in a centered, square-cornered dialog on the profile and posts to the existing capacity-checked booking endpoint. Validation errors return to the same profile, preserve locale and entered values, and reopen the dialog. Successful submissions return to the profile and show a confirmation dialog with the booking reference. The database schema was not changed for this modal flow.
- **Agency profile media and verification:** owners can edit company/contact details, destinations, services, logo, cover, and verification documents. Render uploaded agency media through `Agency::logoUrl()` and `Agency::coverImageUrl()` so updated assets refresh correctly.
- **Inquiries:** the lifecycle is open -> responded -> closed. Agency owners can reply, close, and delete only their own already-closed inquiries. Travelers receive database notifications for agency replies and closure events.
- **Notifications and safe deletion:** both dashboards show unread notification counts; the traveler activity page shows the latest 20 notifications and includes an icon-only delete action scoped to the authenticated user's notifications. Other safe deletion controls exist for saved agencies, packages, package images, editable verification documents, closed inquiries, and an agency's own published travel experiences. Transactional bookings and traveler reviews are not hard-deleted.
- **Search, reviews, and localization:** agency discovery performs case-insensitive partial matching by agency, description, service, and destination. A traveler can review each completed booking once; older reviews without a booking association do not block reviews for new completed bookings. Reviews remain pending until agency approval. `?lang=en` and `?lang=fr` are preserved on dashboard navigation and form actions.
- **Departure expiry and capacity:** active departures dated today or earlier transition to `expired`. A daily scheduled task runs at 00:05, and the agency availability page catches up any missed expirations when opened. Traveler-facing profile and booking queries exclude dates on or before today even if the scheduler has not run. Availability statistics show pending, confirmed, completed travelers, remaining capacity, and occupancy; completed travelers are historical and do not reserve current seats.
- **Booking reviews:** completed booking details include a review form. The endpoint checks that the booking belongs to the signed-in traveler and agency, is completed, and has not already been reviewed. Existing unlinked reviews are preserved.
- **Public contact and payment:** public agency profiles do not display agency email or phone and direct booking requests through TravelConnect. Online payment processing is not integrated; booking totals are recorded, but actual payment collection requires a provider integration.
- **Dashboard branding:** traveler and agency dashboard logos are static brand marks, not navigation links.
- **Theme and layout:** traveler and agency shells share the same professional palette and navigation behavior. The traveler theme toggle updates both the shell and body so surfaces, text, borders, fields, and dialogs switch together. Mobile traveler search sits below the top utility row.
- **Preserved data foundation:** `AgencyExperience`, `TravelRequest`, notification storage, and agency logo/cover columns remain in use. Do not remove these models or migrations during dashboard work.

The data foundation was intentionally preserved for future work:

- `AgencyExperience` model and `agency_experiences` table
- Agency media columns: `logo_path` and `cover_image_path`
- Notifications table migration
- `TravelRequest` model, factory, and migration

Do not delete these preserved models or migrations when rebuilding the agency workspace.

## Requirements

Install PHP, Composer, Node.js, and npm. Verify PHP and Composer:

```powershell
php -v
composer -V
node -v
npm -v
```

## Installation

From the project root:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm install
```

Configure the database in `.env`, then run migrations and seed demo data:

```powershell
php artisan migrate --seed
```

Build frontend assets:

```powershell
npm run build
```

Start the application:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open `http://127.0.0.1:8000`.

## Development commands

Run the Vite watcher in a second terminal when actively editing frontend files:

```powershell
npm run dev
```

Clear compiled configuration and views when debugging stale output:

```powershell
php artisan optimize:clear
php artisan view:cache
```

Run the Laravel scheduler during local development so departures expire without an agency page visit:

```powershell
php artisan schedule:work
```

In production, configure the host scheduler to run `php artisan schedule:run` every minute. The agency availability page also catches up past departures when opened.

## Tests and validation

Run the complete suite:

```powershell
php artisan test
```

Run the main traveler workflow tests:

```powershell
php artisan test tests/Feature/TravelerDashboardTest.php
```

The frontend production build is:

```powershell
npm run build
```

## Application structure

- `app/Models`: User, Agency, AgencyExperience, Favorite, Review, Inquiry, TravelRequest, and related models
- `app/Http/Controllers`: authentication, favorite, review, and agency dashboard overview actions
- `app/Enums`: user roles, verification states, review states, document states, and document types
- `app/Http/Middleware/SetLocale.php`: validates and stores the active locale
- `app/Http/Middleware/EnsureAgencyOwner.php`: protects agency-owner routes and verifies an owned agency record
- `app/Policies/AgencyPolicy.php`: authorizes agency dashboard ownership
- `routes/web.php`: web routes and current agency directory data
- `resources/views/components/account-shell.blade.php`: authenticated navigation shell, theme toggle, and header search
- `resources/views/components/agency-shell.blade.php`: agency navigation, locale-aware links, theme, and loading behavior
- `resources/views/components/agency-card.blade.php`: reusable agency card
- `resources/views/auth`: traveler dashboard, saved agencies, activity, and settings pages
- `resources/views/agencies`: active dashboard, package, availability, booking, payment, profile, review, and analytics views
- `resources/views/agency-show.blade.php`: agency profile and review experience
- `resources/css/app.css`: theme, responsive, and account UI rules
- `resources/js/app.js`: locale-preserving navigation, favorites, carousels, and UI interactions
- `database/migrations`: database schema
- `database/seeders/DatabaseSeeder.php`: local demo agencies, travelers, reviews, and inquiries
- `lang/en/messages.php` and `lang/fr/messages.php`: translated UI strings

Role-specific view convention:

- `auth/` is currently used for authentication and the existing small dashboard because that is the stable legacy structure.
- Agency-only views live in the dedicated `agencies/` view folder.
- Keep traveler-only dashboard views in a dedicated `traveler/` folder only as part of a deliberate migration, updating every route and test reference together.

## Route map

Public routes include `/`, `/how-it-works`, `/for-agencies`, `/terms`, `/privacy`, and `/accessibility`.

Authenticated traveler routes include `/dashboard`, `/account/discover`, `/account/saved`, `/account/activity`, `/account/bookings`, `/agencies/{slug}`, `POST /agencies/{slug}/favorite`, `POST /agencies/{slug}/reviews`, and `DELETE /account/notifications/{notification}`. Package booking uses `GET /packages/{package}/book`, `POST /packages/{package}/book`, `GET /bookings/{booking}`, and `GET /bookings/{booking}/confirmation`.

Authentication routes include login, registration, password recovery, verification-code reset, and logout. Admin login verification, TOTP setup/confirmation, and admin logout are under `/admin`; TOTP routes require an authenticated, second-factor-verified admin session.

The active agency dashboard is `GET /agency/dashboard` (`agency.dashboard`), protected by `auth` and `agency-owner`. Agency mutations, experience deletion, and booking transitions must remain scoped to the authenticated owner, with validation and feature tests.

## Important behavior

### Search

The account header search submits to the agency directory. It searches agency name, description, linked countries, and linked services. Empty searches show the three popular agencies; searches show all matching verified agencies.

### Favorites

The heart button calls `POST /agencies/{slug}/favorite`. It requires an authenticated traveler, uses the unique `favorites` relationship, and returns JSON with the new `saved` state. The UI is initialized from the database on every page load.

Saved-agency cards also use the same favorite endpoint for their icon-only remove action. On a successful unsave, the list refreshes so the collection and empty state reflect database state.

### Reviews

Travelers submit reviews through `POST /agencies/{slug}/reviews`. A review requires a completed booking belonging to that traveler and agency, and each completed booking can be reviewed once. New reviews are stored as `pending`; only published reviews affect the public rating summary. Travelers can submit from the completed booking details page and see a pending-approval notice. Published reviews on the agency profile come from the database and display the reviewer, rating, comment, and relative timestamp such as `2 weeks ago`.

Agency profile review rows are intentionally limited to five published reviews, ordered by rating and then creation date. The summary distribution is calculated from all published reviews, not only the visible five.

### Package booking

Agency profile package cards only include published packages with active departures after today. The profile's booking dialog submits to `POST /packages/{package}/book`; `BookingService` rechecks package publication, departure date, active status, and remaining capacity before creating a pending booking. Departures expire on or before their date and are excluded from traveler profile, package, and booking-selection views. Submissions originating from the profile return to that profile with a confirmation dialog. The standalone booking form and confirmation route remain available for callers that do not originate from the profile modal.

The package destination field is a dropdown of countries assigned to that agency. Both package creation and updates validate against the owner's current country relationship; do not accept arbitrary destination text.

### Safe deletion

Delete icons appear only where an existing, ownership-safe delete action is available. Saved agencies reuse the favorite toggle. Agency-owned packages and images use their existing authorization rules. Verification documents can only be deleted during the existing edit window. Inquiries can only be deleted after closure. Notifications are resolved through the signed-in user's notification relation. Travel experiences can only be deleted by their owning agency, and deletion also removes the stored image. Do not add hard-delete actions for bookings or reviews, which are transactional/moderated history.

### Agency travel experiences

Agency profiles load their gallery through the `Agency::experiences()` relationship. Each `AgencyExperience` record contains `agency_id`, `image_path`, `destination`, `caption`, `alt_text`, and `published_at`.

Gallery images use local paths such as `images/traveler_experience/p1.jpeg`; do not replace them with Unsplash URLs. Existing demo agencies use different rotating image sets from `p1.jpeg` through `p7.jpeg`, and each image displays its destination/country.

The gallery is agency-specific. Do not place a generic experience feed on the traveler dashboard. Future agency posting forms must save the selected agency, uploaded image, destination, caption, and alt text to `agency_experiences`.

An agency owner can remove an experience only from their own public profile. The delete action removes both the `agency_experiences` row and its uploaded public-disk image. Cross-agency deletion must remain forbidden.

### Agency dashboard overview

Agency dashboard and operations queries are scoped to the authenticated owner's agency. The dashboard summarizes packages, departures, bookings, and inquiries. Currency totals are grouped by each booking's original currency; do not present them as payouts because no payout ledger or payment-provider integration exists. Render uploaded agency media through `Agency::logoUrl()` and `Agency::coverImageUrl()` so changed images get a fresh URL.

### Localization

Use `?lang=en` or `?lang=fr`. `SetLocale` validates the value, applies it to the request, and stores it in the session. Language links are excluded from the generic locale-preservation handler and navigate directly.

### Theme

The account theme is stored in browser `localStorage` under `travelconnect-dashboard-theme`. Dark-mode rules are scoped to account surfaces, cards, forms, badges, and review panels. Mobile keeps Theme and EN/FR visible until the search field receives focus.

## Demo data

The seeders create verified agencies including Atlas Horizon Voyages, Lumiere Routes, Northstar Escapes, Sirocco Travel House, and Blue Fern Expeditions. They also create traveler accounts, agency owners, destinations, services, inquiries, reviews, favorites, trust-score records, and local agency experience galleries.

To refresh the local agency experience records after changing gallery assignments:

```powershell
php artisan db:seed --class=AgencyExperienceSeeder
```

This seeder replaces only the experience rows for each agency. It does not remove agencies, reviews, inquiries, or users.

Factory-created user passwords default to `password` for local testing. Never use seeded credentials in production.

## Remaining work before final polish

1. Fix the `TravelerConversationController` reference used in `routes/web.php`; currently `php artisan route:list` fails while reflecting that controller. TOTP routes themselves are registered and feature tested.
2. Decide whether admin TOTP enrollment should remain optional or become mandatory. Currently an unenrolled admin can skip setup for a session and is prompted again after a later email-code login.
3. Run a real mail/TOTP/recovery-code smoke test in a production-like environment before deployment.
4. Add pagination to the activity notification feed, which currently shows the latest 20 notifications, and ensure review moderation/admin events produce notifications.
5. Continue simplifying labels, help text, and empty states for users with limited computer experience.
6. Select a payment provider and define reconciliation/payout rules before adding payout functionality; current booking payments are status tracking only.
7. Add browser-level coverage for locale switching, profile-media replacement, booking dialogs, delete actions, and mobile navigation if a browser test tool is introduced.
8. Keep booking transitions, cancellation, payment filters, and currency totals covered by focused feature tests.

## Continuing-project rules

1. Keep business logic in controllers/models rather than Blade views.
2. Add feature tests for every new authenticated endpoint.
3. Keep review moderation and traveler authorization in place.
4. Preserve locale query parameters on new navigation links.
5. Use local or approved image assets instead of unreliable remote image URLs.
6. Keep each actor’s data scoped to the authenticated owner.
7. Never use `firstOrFail()` for an agency dashboard profile without handling an agency-owner account that has no linked agency row.
8. Treat the inquiry lifecycle as a product requirement: open -> respond -> close -> delete-closed is the accepted flow for the current agency dashboard.
9. Run `php artisan test`, `php artisan view:cache`, and `npm run build` before pushing changes.

## Handoff checklist for a new AI

Before editing:

- Read this README and `AGENTS.md`.
- Run `git status --short` and preserve unrelated user changes.
- Check the active route and view name before moving any Blade file.
- Preserve the existing traveler dashboard output.
- Use local images from `public/images`, especially `public/images/traveler_experience`.

After editing:

```powershell
php artisan optimize:clear
php artisan view:cache
php artisan test
npm run build
```

Check the relevant page manually at `http://127.0.0.1:8000` when a local server is available.

## Security notes

`.env`, dependencies, compiled assets, storage links, and local credentials are ignored by Git. Configure secrets only through environment variables and do not commit real credentials.
