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
- Duplicate review prevention for a traveler on the same agency
- Agency-owned travel experience galleries backed by the database
- Agency package, departure, booking, customer, review, payment-status, and analytics workspace
- Agency profiles list all published packages, with booking actions only for packages with upcoming active departures
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

## Current implementation state

TravelConnect is an active Laravel marketplace with separate traveler and agency workspaces. The main discovery, booking, inquiry, verification, and review flows use database-backed records and role-scoped routes. Treat the current dashboard and booking behavior below as the continuation baseline.

### Recent implementation baseline

The following focused checks passed during the latest dashboard and delete-control pass:

```powershell
php artisan test --compact tests/Feature/AgencyDashboardTest.php
php artisan test --compact tests/Feature/AgencyPackageManagementTest.php
php artisan test --compact tests/Feature/TravelerDashboardTest.php --filter='a_traveler_sees_their_dashboard_data|a_traveler_can_view_only_their_own_bookings|a_traveler_can_delete_their_own_activity_notification|a_traveler_cannot_delete_another_users_activity_notification|traveler_sees_the_agency_reply_in_their_activity_feed|saved_agency_cards_show_the_uploaded_cover_image'
php artisan view:cache
npm run build
```

All listed commands passed. The full suite has a known unrelated failure in `test_a_traveler_can_submit_an_inquiry_to_an_agency`: it expects the inquiry subject on the agency overview, whose intended design is a compact, subject-free latest-inquiry preview.

### Active session handoff

- **Agency workspace:** `GET /agency/dashboard` uses the `x-agency-shell` and links to package, availability, booking, customer, review, payment, analytics, profile, and inquiry management. The overview keeps compact summaries; detailed work belongs in each dedicated section.
- **Agency packages:** agencies can create, edit, publish, archive, and soft-delete packages. Package destinations are a required selection from the countries linked to that agency; server-side validation rejects destinations outside that list. Public agency profiles show all published packages and images. A booking button appears when a package has a future active departure.
- **Traveler bookings:** `GET /account/bookings` is the traveler-only “My bookings” page. It scopes records to the signed-in user and shows reference, package, agency, departure, status, travelers, total, and payment state. “View booking” opens a details dialog; pending and confirmed records retain the existing cancel action.
- **Booking from an agency profile:** package booking opens in a centered, square-cornered dialog on the profile and posts to the existing capacity-checked booking endpoint. Validation errors return to the same profile, preserve locale and entered values, and reopen the dialog. Successful submissions return to the profile and show a confirmation dialog with the booking reference. The database schema was not changed for this modal flow.
- **Agency profile media and verification:** owners can edit company/contact details, destinations, services, logo, cover, and verification documents. Render uploaded agency media through `Agency::logoUrl()` and `Agency::coverImageUrl()` so updated assets refresh correctly.
- **Inquiries:** the lifecycle is open -> responded -> closed. Agency owners can reply, close, and delete only their own already-closed inquiries. Travelers receive database notifications for agency replies and closure events.
- **Notifications and safe deletion:** both dashboards show unread notification counts; the traveler activity page shows the latest 20 notifications and includes an icon-only delete action scoped to the authenticated user's notifications. Other safe deletion controls exist for saved agencies, packages, package images, editable verification documents, closed inquiries, and an agency's own published travel experiences. Transactional bookings and traveler reviews are not hard-deleted.
- **Search, reviews, and localization:** agency discovery performs case-insensitive partial matching by agency, description, service, and destination. Duplicate reviews by the same traveler for the same agency are blocked. Review moderation remains pending until approval. `?lang=en` and `?lang=fr` are preserved on dashboard navigation and form actions.
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

Authentication routes include login, registration, password recovery, verification-code reset, and logout.

The active agency dashboard is `GET /agency/dashboard` (`agency.dashboard`), protected by `auth` and `agency-owner`. Agency mutations, experience deletion, and booking transitions must remain scoped to the authenticated owner, with validation and feature tests.

## Important behavior

### Search

The account header search submits to the agency directory. It searches agency name, description, linked countries, and linked services. Empty searches show the three popular agencies; searches show all matching verified agencies.

### Favorites

The heart button calls `POST /agencies/{slug}/favorite`. It requires an authenticated traveler, uses the unique `favorites` relationship, and returns JSON with the new `saved` state. The UI is initialized from the database on every page load.

Saved-agency cards also use the same favorite endpoint for their icon-only remove action. On a successful unsave, the list refreshes so the collection and empty state reflect database state.

### Reviews

Travelers submit reviews through `POST /agencies/{slug}/reviews`. New reviews are stored as `pending`; only published reviews affect the public rating summary. The submitting traveler sees a pending-approval notice. Published reviews on the agency profile come from the database and display the reviewer, rating, comment, and relative timestamp such as `2 weeks ago`.

Agency profile review rows are intentionally limited to five published reviews, ordered by rating and then creation date. The summary distribution is calculated from all published reviews, not only the visible five.

### Package booking

Agency profile package cards only include published packages. The profile's booking dialog submits to `POST /packages/{package}/book`; `BookingService` rechecks package publication, active departure, and remaining capacity before creating a pending booking. Submissions originating from the profile return to that profile with a confirmation dialog. The standalone booking form and confirmation route remain available for callers that do not originate from the profile modal.

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
