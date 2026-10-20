# TravelConnect

TravelConnect is a Laravel marketplace connecting travelers with verified travel agencies. Travelers can discover agencies, search by destination or specialty, save agencies, view agency travel experiences, and submit moderated ratings and reviews.

This README is also the continuation handoff for future AI agents. Read the current-state and next-work sections before changing the architecture.

## Current features

* Traveler registration, login, logout, and profile photo upload
* Traveler dashboard with live saved-agency, inquiry, and review data
* Traveler "My bookings" section with owner-scoped reservations, status/payment summaries, and in-page booking details
* In-profile booking dialog for published agency packages, with capacity-checked checkout and localized validation/success dialogs
* Verified agency directory with popular-agency ranking
* Search by agency name, description, destination, or service with case-insensitive partial matching
* Default directory shows the three most popular verified agencies
* Country searches return every matching verified agency
* Save and unsave agencies with persistent database state
* Saved Agencies page with real agency images, ratings, and all destinations
* Agency detail pages with multiple destinations, trust signals, ratings, and reviews
* Real inquiry submission to agency owners and unread-activity notification counts
* Traveler reviews require a completed booking and are limited to one review per booking
* Agency-owned travel experience galleries backed by the database
* Agency package, departure, booking, customer, review, payment-status, and analytics workspace
* Traveler-facing agency profiles show only published packages with at least one active departure after today
* Past departures expire automatically and are hidden from traveler profile and booking flows
* Agency availability totals separate current reservations from completed travelers
* Public agency profiles keep agency email and phone private
* Dashboard brand marks are static and do not navigate when clicked
* Package destinations are selected from the owning agency's configured countries and validated server-side
* Owner-scoped icon deletion for saved agencies, packages and package images, eligible verification documents, closed inquiries, notifications, and agency travel experiences
* Local traveler-experience images stored under `public/images/traveler\_experience`
* Traveler review submission with 1-5 rating, comment validation, and pending moderation
* English/French locale switching using the `?lang=en` or `?lang=fr` query parameter
* Responsive account shell with mobile navigation, notification bell, and persistent light/dark theme
* Admin dashboard with a real-record platform overview, current status summaries, attention queue, and recent activity
* Admin Alerts queue for pending agency verification, flagged reviews, failed payments, failed jobs, and suspicious admin login events
* Admin alert search, filters, pagination, record details, bilingual reason summaries, and audited acknowledge/resolve actions

## Technology

* PHP 8.3+
* Laravel 13
* MySQL (configured through `.env`)
* Blade views
* Tailwind CSS 4
* Vite
* PHPUnit

## Current implementation state

TravelConnect is an active Laravel marketplace with separate traveler and agency workspaces. The main discovery, booking, inquiry, verification, and review flows use database-backed records and role-scoped routes. Treat the current dashboard and booking behavior below as the continuation baseline.

### Recent implementation baseline

The following focused checks passed during the departure-expiry, booking-review, and dashboard updates:

```powershell
php artisan test --compact tests/Feature/AgencyBookingManagementTest.php tests/Feature/TravelerDashboardTest.php tests/Feature/AgencyDashboardTest.php tests/Feature/AgencyOperationsTest.php
php artisan test --compact tests/Feature/TravelerDashboardTest.php
php artisan schedule:list --no-interaction
vendor/bin/pint --dirty --format agent
```

All listed commands passed. The affected feature suites reported 101 passing tests, the final traveler-dashboard run reported 46 passing tests, and the complete suite reported 118 passing tests with 659 assertions. Blade caching and the production asset build also passed.

### Bug-fix handoff (2026-10-18)

The `Fixing-System-Bugs` branch contains these focused bug fixes:

* **Agency package verification:** unverified agencies are told why package creation is unavailable, and the server rejects both package creation and duplication unless the agency is verified.
* **Agency profile and localization:** profile validation errors are displayed in the selected language, French is retained after saving, and the latest verification document is passed to the profile view. Agency phone numbers are restricted to Cameroon’s `+237` country code and nine digits. The destination checklist is collapsed by default and retains selections after validation errors.
* **Agency logout theme:** the sidebar logout hover/focus styling uses the agency theme colors instead of turning into an all-white block in dark mode.
* **Booking capacity display:** the booking detail page calculates capacity on each page load. Active pending and confirmed bookings both reserve seats; confirming a booking moves its count between those categories without changing total occupancy or remaining seats. The page now explains this in English and French, with a feature test asserting the rendered values.

Focused package, profile, phone-validation, and booking tests passed during this work; the three booking-capacity/lifecycle tests were rerun together immediately before this handoff. The inquiry messaging/reply error reported earlier is **not fixed** in this branch and remains for a later bug-fix session. Capacity figures are refreshed on page load, not automatically while the detail page stays open.

### Admin dashboard Overview and Alerts (2026-10-20)

The admin dashboard's **Main → Overview** and **Main → Alerts** pages now use current application records instead of placeholder panels.

* **Overview:** shows registered travelers, agencies by verification status, published packages, bookings by status, recent activity, and a prioritized attention list for pending verification, bookings, inquiries, reviews, and travel requests. Historical charts remain in Business & Insights → App Analysis.
* **Alerts queue:** brings together pending agency verifications, flagged reviews, failed payment transactions, failed queue jobs, and failed or blocked admin login events. Admins can search and filter by status, severity, and category, then open each source record for context and its available reason.
* **Alert actions:** acknowledge and resolve are available from the opened record. They update only the alert state; each successful transition is recorded with the acting admin and timestamp. They do not approve or otherwise mutate the underlying agency, review, payment, job, or login-event record.
* **Source coverage:** complaints, booking disputes, and integration errors are not shown as alerts because this application does not yet have dedicated tracked records for those categories.
* **Localization and appearance:** Overview and Alerts labels are available in English and French. The Overview uses square-cornered panels and the existing admin theme colors.
* **Database:** the `admin_alerts` and `admin_alert_actions` tables are created by their migrations. Run `php artisan migrate` after updating an environment before using alert status actions there.

**Verification:** the focused admin Overview and Alerts feature tests passed (11 tests, 560 assertions), as did the admin sign-in-to-Overview feature test (1 test, 16 assertions). `npm run build`, Pint, and `git diff --check` passed.

### Remembered-session dashboard fix (2026-10-19)

**Reported behavior:** after signing in as an agency owner with “Remember me,” reopening the browser could show the traveler-style `/dashboard` page with the agency owner's name and email. The owner also saw traveler navigation while viewing a public agency profile, and opening the site root did not return them to the agency workspace.

**Root causes and changes:**

* The authenticated `/dashboard` route rendered a generic fallback for every non-traveler role instead of routing agency owners to their own dashboard. It now redirects agency owners to `/agency/dashboard` and admins to `/admin/dashboard`.
* The `/` route always rendered the public homepage. It now routes an authenticated visitor to the dashboard matching their account role.
* The public agency profile uses the shared account shell. That shell now selects the agency workspace navigation for agency-owner sessions, avoiding traveler links on an agency owner's view of a public profile.
* Authenticated dashboards and agency profile responses now send `private, no-store, no-cache` headers. When a browser restores an authenticated workspace from its back-forward cache, the page reloads to check the current session and role.
* Local URLs are standardized on `http://127.0.0.1:8000` because browsers treat `localhost` and `127.0.0.1` as separate hosts with separate session and remember-me cookies. Google OAuth's local callback and origin must use the same host.

**Verification:** all 42 `AgencyDashboardTest` tests passed, plus 2 selected traveler dashboard tests. The frontend production build and `git diff --check` passed. One separate older traveler agency-profile test has a departure fixture dated before the current date and fails its package-visibility assertion; that fixture needs a future date before it can validate the intended behavior. The browser's own startup preference may still restore multiple tabs; the application redirects each restored page to the correct signed-in workspace but does not control which tabs the browser opens.

### Active session handoff

* **Agency workspace:** `GET /agency/dashboard` uses the `x-agency-shell` and links to package, availability, booking, customer, review, payment, analytics, profile, and inquiry management. The overview keeps compact summaries; detailed work belongs in each dedicated section.
* **Agency packages:** agencies can create, edit, publish, archive, and soft-delete packages. Package destinations are a required selection from the countries linked to that agency; server-side validation rejects destinations outside that list. Public agency profiles show only published packages with active departures after today; packages with no upcoming departures are hidden.
* **Traveler bookings:** `GET /account/bookings` is the traveler-only “My bookings” page. It scopes records to the signed-in user and shows reference, package, agency, departure, status, travelers, total, and payment state. “View booking” opens a details dialog; pending and confirmed records retain the existing cancel action. Departures dated today or earlier are hidden and rejected by the booking service.
* **Booking from an agency profile:** package booking opens in a centered, square-cornered dialog on the profile and posts to the existing capacity-checked booking endpoint. Validation errors return to the same profile, preserve locale and entered values, and reopen the dialog. Successful submissions return to the profile and show a confirmation dialog with the booking reference. The database schema was not changed for this modal flow.
* **Agency profile media and verification:** owners can edit company/contact details, destinations, services, logo, cover, and verification documents. Render uploaded agency media through `Agency::logoUrl()` and `Agency::coverImageUrl()` so updated assets refresh correctly.
* **Inquiries:** the lifecycle is open -> responded -> closed. Agency owners can reply, close, and delete only their own already-closed inquiries. Travelers receive database notifications for agency replies and closure events.
* **Notifications and safe deletion:** both dashboards show unread notification counts; the traveler activity page shows the latest 20 notifications and includes an icon-only delete action scoped to the authenticated user's notifications. Other safe deletion controls exist for saved agencies, packages, package images, editable verification documents, closed inquiries, and an agency's own published travel experiences. Transactional bookings and traveler reviews are not hard-deleted.
* **Search, reviews, and localization:** agency discovery performs case-insensitive partial matching by agency, description, service, and destination. A traveler can review each completed booking once; older reviews without a booking association do not block reviews for new completed bookings. Reviews remain pending until agency approval. `?lang=en` and `?lang=fr` are preserved on dashboard navigation and form actions.
* **Departure expiry and capacity:** active departures dated today or earlier transition to `expired`. A daily scheduled task runs at 00:05, and the agency availability page catches up any missed expirations when opened. Traveler-facing profile and booking queries exclude dates on or before today even if the scheduler has not run. Availability statistics show pending, confirmed, completed travelers, remaining capacity, and occupancy; completed travelers are historical and do not reserve current seats.
* **Booking reviews:** completed booking details include a review form. The endpoint checks that the booking belongs to the signed-in traveler and agency, is completed, and has not already been reviewed. Existing unlinked reviews are preserved.
* **Public contact and payment:** public agency profiles do not display agency email or phone and direct booking requests through TravelConnect. Online payment processing is not integrated; booking totals are recorded, but actual payment collection requires a provider integration.
* **Dashboard branding:** traveler and agency dashboard logos are static brand marks, not navigation links.
* **Theme and layout:** traveler and agency shells share the same professional palette and navigation behavior. The traveler theme toggle updates both the shell and body so surfaces, text, borders, fields, and dialogs switch together. Mobile traveler search sits below the top utility row.
* **Preserved data foundation:** `AgencyExperience`, `TravelRequest`, notification storage, and agency logo/cover columns remain in use. Do not remove these models or migrations during dashboard work.

The data foundation was intentionally preserved for future work:

* `AgencyExperience` model and `agency\_experiences` table
* Agency media columns: `logo\_path` and `cover\_image\_path`
* Notifications table migration
* `TravelRequest` model, factory, and migration

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

Always use `http://127.0.0.1:8000` for local development and sign-in. Do not alternate between `localhost` and `127.0.0.1`; the browser stores separate session and remember-me cookies for those hosts.

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

* `app/Models`: User, Agency, AgencyExperience, Favorite, Review, Inquiry, TravelRequest, and related models
* `app/Http/Controllers`: authentication, favorite, review, and agency dashboard overview actions
* `app/Enums`: user roles, verification states, review states, document states, and document types
* `app/Http/Middleware/SetLocale.php`: validates and stores the active locale
* `app/Http/Middleware/EnsureAgencyOwner.php`: protects agency-owner routes and verifies an owned agency record
* `app/Policies/AgencyPolicy.php`: authorizes agency dashboard ownership
* `routes/web.php`: web routes and current agency directory data
* `resources/views/components/account-shell.blade.php`: authenticated navigation shell, theme toggle, and header search
* `resources/views/components/agency-shell.blade.php`: agency navigation, locale-aware links, theme, and loading behavior
* `resources/views/components/agency-card.blade.php`: reusable agency card
* `resources/views/auth`: traveler dashboard, saved agencies, activity, and settings pages
* `resources/views/agencies`: active dashboard, package, availability, booking, payment, profile, review, and analytics views
* `resources/views/agency-show.blade.php`: agency profile and review experience
* `resources/css/app.css`: theme, responsive, and account UI rules
* `resources/js/app.js`: locale-preserving navigation, favorites, carousels, and UI interactions
* `database/migrations`: database schema
* `database/seeders/DatabaseSeeder.php`: local demo agencies, travelers, reviews, and inquiries
* `lang/en/messages.php` and `lang/fr/messages.php`: translated UI strings

Role-specific view convention:

* `auth/` is currently used for authentication and the existing small dashboard because that is the stable legacy structure.
* Agency-only views live in the dedicated `agencies/` view folder.
* Keep traveler-only dashboard views in a dedicated `traveler/` folder only as part of a deliberate migration, updating every route and test reference together.

## Route map

Public routes include `/`, `/how-it-works`, `/for-agencies`, `/terms`, `/privacy`, and `/accessibility`.

Authenticated traveler routes include `/dashboard`, `/account/discover`, `/account/saved`, `/account/activity`, `/account/bookings`, `/agencies/{slug}`, `POST /agencies/{slug}/favorite`, `POST /agencies/{slug}/reviews`, and `DELETE /account/notifications/{notification}`. Package booking uses `GET /packages/{package}/book`, `POST /packages/{package}/book`, `GET /bookings/{booking}`, and `GET /bookings/{booking}/confirmation`.

Authentication routes include login, registration, password recovery, verification-code reset, and logout.

The active agency dashboard is `GET /agency/dashboard` (`agency.dashboard`), protected by `auth` and `agency-owner`. Agency mutations, experience deletion, and booking transitions must remain scoped to the authenticated owner, with validation and feature tests.

The admin dashboard is `GET /admin/dashboard` (`admin.dashboard`) and its configured pages use `GET /admin/dashboard/pages/{page}` (`admin.dashboard.page`). These routes and the alert acknowledge/resolve POST routes require `auth` and `EnsureAdminSession`. Apply pending database migrations before using the admin Alerts page.

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

Agency profiles load their gallery through the `Agency::experiences()` relationship. Each `AgencyExperience` record contains `agency\_id`, `image\_path`, `destination`, `caption`, `alt\_text`, and `published\_at`.

Gallery images use local paths such as `images/traveler\_experience/p1.jpeg`; do not replace them with Unsplash URLs. Existing demo agencies use different rotating image sets from `p1.jpeg` through `p7.jpeg`, and each image displays its destination/country.

The gallery is agency-specific. Do not place a generic experience feed on the traveler dashboard. Future agency posting forms must save the selected agency, uploaded image, destination, caption, and alt text to `agency\_experiences`.

An agency owner can remove an experience only from their own public profile. The delete action removes both the `agency\_experiences` row and its uploaded public-disk image. Cross-agency deletion must remain forbidden.

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

1. Add pagination to the activity notification feed, which currently shows the latest 20 notifications, and ensure review moderation/admin events produce notifications.
2. Continue simplifying labels, help text, and empty states for users with limited computer experience.
3. Select a payment provider and define reconciliation/payout rules before adding payout functionality; current booking payments are status tracking only.
4. Add browser-level coverage for locale switching, profile-media replacement, booking dialogs, delete actions, and mobile navigation if a browser test tool is introduced.
5. Keep booking transitions, cancellation, payment filters, and currency totals covered by focused feature tests.

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

* Read this README and `AGENTS.md`.
* Run `git status --short` and preserve unrelated user changes.
* Check the active route and view name before moving any Blade file.
* Preserve the existing traveler dashboard output.
* Use local images from `public/images`, especially `public/images/traveler\_experience`.

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

## Google authentication

TravelConnect supports Google OAuth through Laravel Socialite as an alternative to the application's normal email/password authentication. Google provides a stable account identifier and profile information (such as the user's name and email); it does **not** provide the user's Google password to TravelConnect. Never try to retrieve or store a Google password.

### Where it is included

* **Shared login page (`/login`):** “Sign in with Google” starts the Google OAuth flow. An account that is already linked by its Google provider ID authenticates as that same TravelConnect user. Admin accounts must use password-based sign-in and complete the configured two-step verification.
* **Traveler signup page:** “Sign up with Google” provides a shorter signup path. A new Google-created traveler account is assigned the traveler role by the server, and its Google provider ID is stored in `users.google_id`.
* **Agency signup page:** Agency Google signup has a dedicated onboarding flow. It returns the Google-provided name and email to the agency form, where the user enters the agency name and a **TravelConnect password**. The owner and associated `Agency` record are created together only after the form is submitted successfully. The account role is assigned server-side; existing users are not silently converted to agency owners.

The agency flow is separate from traveler signup. Its pending Google identity is kept in the session for a limited time and is not accepted from user-submitted identity fields.

### How it works

1. The user chooses Google signup or sign-in from the relevant page.
2. Laravel Socialite redirects the browser to Google.
3. Google returns the browser to the configured callback URI. Laravel validates the OAuth state/session and exchanges the authorization response for the Google identity.
4. The application uses Google's stable provider ID (`google_id`) to identify an already-linked account. Email conflicts must be handled safely; do not create duplicate accounts, overwrite passwords, overwrite another user's Google ID, or silently change account roles.
5. For a new traveler, the application creates a traveler record and completes the Google sign-in flow. For agency signup, the application must collect the required agency information and a TravelConnect password before creating the agency-owner account and its associated agency record.
6. Where both sign-in methods are supported for the same linked account, Google sign-in and the normal email/password form authenticate the same TravelConnect user. A user-created TravelConnect password is separate from the Google account password.

### Advantages

* Reduces the amount of information a user must type during initial signup.
* Lets users return through Google without needing to remember a separate password, where their account is linked.
* Provides a stable provider identifier for recognizing the same Google account.
* Keeps traveler and agency onboarding requirements distinct while reusing Google's identity verification flow.
* Avoids collecting or handling users' Google account passwords.

### Dependencies and project configuration

The following must be available for Google authentication to work correctly:

* **Composer dependency:** `laravel/socialite` must be included in `composer.json` and `composer.lock`; install project dependencies with `composer install` after cloning, or use directly `composer require laravel/socialite` if the project was already cloned
* **Service configuration:** `config/services.php` must map the Google service to the following environment variables:

```php
  'google' => [
      'client_id' => env('GOOGLE_CLIENT_ID'),
      'client_secret' => env('GOOGLE_CLIENT_SECRET'),
      'redirect' => env('GOOGLE_REDIRECT_URI'),
  ],
  ```

* **Environment variables:** Configure these locally in `.env` (and use blank placeholders, not real credentials, in any committed example file):

```dotenv
  GOOGLE_CLIENT_ID=your-google-oauth-client-id
  GOOGLE_CLIENT_SECRET=your-google-oauth-client-secret
  GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
  ```

  Never commit real client secrets or paste them into logs, issues, or source control. After changing environment configuration, run `php artisan config:clear`.

* **Google Cloud OAuth client:** Create a Web application OAuth client. For local development with the example `APP_URL`, register the exact redirect URI `http://127.0.0.1:8000/auth/google/callback` and the authorized origin `http://127.0.0.1:8000`. The redirect URI must exactly match `GOOGLE_REDIRECT_URI` in Laravel. For deployment, configure the real HTTPS domain and its callback URI in both Google Cloud and the deployment environment.
* **Database schema:** The `users` table needs the nullable, unique `google_id` column. The project migration that adds it must be applied with `php artisan migrate` on each environment. Existing email/password users can keep `google_id` as `NULL` until their account is linked.
* **Session/state support:** Laravel's session must persist between the redirect to Google and the callback. Do not disable Socialite's OAuth state verification or use `stateless()` as a workaround for session problems.
* **PHP HTTPS certificate trust:** PHP/cURL must be able to validate Google's HTTPS certificate. On Windows/XAMPP, configure `curl.cainfo` (and, where needed, `openssl.cafile`) in the `php.ini` used by the PHP process to point to a valid CA certificate bundle. Do not disable SSL certificate verification.
* **Agency data requirements:** Agency signup must use the existing agency validation rules and create the owner and its associated `Agency` record using the project's existing relationship and required fields. The agency name must be collected before creation; the submitted TravelConnect password must be hashed using the existing `User` model conventions.

### Local verification

After configuring the environment and Google Cloud client:

```powershell
php artisan config:clear
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Then test the traveler Google signup, linked-account Google sign-in, normal email/password sign-in, and the agency Google onboarding flow separately. Confirm that administrator accounts still require password-based sign-in and two-step verification. Confirm that user records are saved to the database configured by `DB_DATABASE` in `.env`. Run the project's authentication feature tests and `php artisan test` before merging or deploying.
