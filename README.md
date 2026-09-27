# TravelConnect

TravelConnect is a Laravel marketplace connecting travelers with verified travel agencies. Travelers can discover agencies, search by destination or specialty, save agencies, view agency travel experiences, and submit moderated ratings and reviews.

This README is also the continuation handoff for future AI agents. Read the current-state and next-work sections before changing the architecture.

## Current features

- Traveler registration, login, logout, and profile photo upload
- Traveler dashboard with live saved-agency, inquiry, and review data
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

The traveler-facing marketplace and the agency workspace are active product surfaces. Agency owners can manage their public profile, media, verification documents, and travel experiences; inquiry response/status actions and notifications remain deferred.

### Latest session handoff

- Agency owners use the protected `GET /agency/dashboard` route and the dedicated `x-agency-shell`. Packages, availability, bookings, customers, reviews, payment status, analytics, and profile settings are active.
- Agency profile editing supports company/contact details, destinations, services, logo and cover uploads, verification documents, and travel experiences. Logo and cover URLs include an update version so browsers refresh changed uploads; public agency covers use `Agency::coverImageUrl()`.
- Booking and payment screens use responsive filters and spaced currency totals. Booking payment status is tracked, but there is no payment-provider integration or payout ledger.
- Traveler discovery now performs partial case-insensitive matching, so names such as `wakeu` can resolve to the correct agency without falling back to a generic profile.
- Travelers can submit real inquiries to agencies, and agency owners receive database notifications with unread counts in the header bell.
- Duplicate reviews are blocked once a traveler has already reviewed an agency.
- Public agency profiles show the five highest-rated published reviews; aggregate rating and review count use the complete published review set.
- The agency shell preserves `?lang=en` / `?lang=fr` on profile navigation and keeps the sidebar state stable when the locale changes.
- MySQL/MariaDB connections default new tables to InnoDB. The local development database has package, availability, and booking migrations applied; empty conflicting package tables were preserved under backup names during repair.
- Recent validation: the traveler workflow suite covering the dashboard, discovering agencies, inquiry flow, review limits, and notifications was re-run for the completed fix pass. The final pass should be treated as the project baseline before any remaining feature polish.

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

Authenticated traveler routes include `/dashboard`, `/account/discover`, `/account/saved`, `/account/activity`, `/agencies/{slug}`, `POST /agencies/{slug}/favorite`, and `POST /agencies/{slug}/reviews`.

Authentication routes include login, registration, password recovery, verification-code reset, and logout.

The active agency dashboard is `GET /agency/dashboard` (`agency.dashboard`), protected by `auth` and `agency-owner`. Keep agency mutations and booking transitions scoped to the authenticated owner, with validation and feature tests.

## Important behavior

### Search

The account header search submits to the agency directory. It searches agency name, description, linked countries, and linked services. Empty searches show the three popular agencies; searches show all matching verified agencies.

### Favorites

The heart button calls `POST /agencies/{slug}/favorite`. It requires an authenticated traveler, uses the unique `favorites` relationship, and returns JSON with the new `saved` state. The UI is initialized from the database on every page load.

### Reviews

Travelers submit reviews through `POST /agencies/{slug}/reviews`. New reviews are stored as `pending`; only published reviews affect the public rating summary. The submitting traveler sees a pending-approval notice. Published reviews on the agency profile come from the database and display the reviewer, rating, comment, and relative timestamp such as `2 weeks ago`.

Agency profile review rows are intentionally limited to five published reviews, ordered by rating and then creation date. The summary distribution is calculated from all published reviews, not only the visible five.

### Agency travel experiences

Agency profiles load their gallery through the `Agency::experiences()` relationship. Each `AgencyExperience` record contains `agency_id`, `image_path`, `destination`, `caption`, `alt_text`, and `published_at`.

Gallery images use local paths such as `images/traveler_experience/p1.jpeg`; do not replace them with Unsplash URLs. Existing demo agencies use different rotating image sets from `p1.jpeg` through `p7.jpeg`, and each image displays its destination/country.

The gallery is agency-specific. Do not place a generic experience feed on the traveler dashboard. Future agency posting forms must save the selected agency, uploaded image, destination, caption, and alt text to `agency_experiences`.

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

1. Complete the agency inbox workflow so owners can reply to traveler inquiries and update inquiry status from the dashboard.
2. Finish the notification action layer so review, inquiry, and admin events can route to dedicated inbox and unread-state screens rather than only the header bell count.
3. Tighten the traveler dashboard and agency dashboard UX for non-technical users by simplifying some labels, help text, and empty states.
4. Finish the package and listing image workflow so every package card supports a clear cover image upload and preview experience with consistent validation.
5. Select a payment provider and define payout/reconciliation requirements before adding payout features to the payment screen.
6. Add browser-level coverage for locale switching, profile-media replacement, and mobile agency navigation if a browser test tool is introduced.
7. Keep booking state transitions, payment filtering, and currency summaries covered by feature tests.

## Continuing-project rules

1. Keep business logic in controllers/models rather than Blade views.
2. Add feature tests for every new authenticated endpoint.
3. Keep review moderation and traveler authorization in place.
4. Preserve locale query parameters on new navigation links.
5. Use local or approved image assets instead of unreliable remote image URLs.
6. Keep each actor’s data scoped to the authenticated owner.
7. Never use `firstOrFail()` for an agency dashboard profile without handling an agency-owner account that has no linked agency row.
8. Run `php artisan test`, `php artisan view:cache`, and `npm run build` before pushing changes.

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
