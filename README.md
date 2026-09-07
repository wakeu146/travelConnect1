# TravelConnect

TravelConnect is a Laravel marketplace connecting travelers with verified travel agencies. Travelers can discover agencies, search by destination or specialty, save agencies, view agency travel experiences, and submit moderated ratings and reviews.

This README is also the continuation handoff for future AI agents. Read the current-state and next-work sections before changing the architecture.

## Current features

- Traveler registration, login, logout, and profile photo upload
- Traveler dashboard with live saved-agency, inquiry, and review data
- Verified agency directory with popular-agency ranking
- Search by agency name, description, destination, or service
- Default directory shows the three most popular verified agencies
- Country searches return every matching verified agency
- Save and unsave agencies with persistent database state
- Saved Agencies page with agency images, ratings, and all destinations
- Agency detail pages with multiple destinations, trust signals, ratings, and reviews
- Agency-owned travel experience galleries backed by the database
- Local traveler-experience images stored under `public/images/traveler_experience`
- Traveler review submission with 1-5 rating, comment validation, and pending moderation
- English/French locale switching using the `?lang=en` or `?lang=fr` query parameter
- Responsive account shell with mobile navigation and persistent light/dark theme

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

- Agency profile reviews now use a Play Store-inspired summary and flat review rows with thin dividers, quieter verified-review footers, and light/dark theme variables.
- Agency profiles display the five highest-rated published reviews; the aggregate rating and review count still use the complete published review set.
- Agency cover images are stored locally in `public/images/agencies` for Atlas Horizon Voyages, Lumiere Routes, Northstar Escapes, Sirocco Travel House, and Blue Fern Expeditions.
- Agency profile routes use local cover assets instead of remote Unsplash URLs. The reusable agency card also maps these agency names to the same local files.
- The review Blade view was validated with `php artisan view:cache`; the focused feature suite passed with 14 tests and 61 assertions.
- Frontend assets were rebuilt successfully with `npm run build`.
- The protected agency dashboard overview is available at `GET /agency/dashboard` as `agency.dashboard` and reuses `x-account-shell`.
- Agency owners can edit their company/contact details, destinations, services, logo, cover image, upload verification documents, and publish travel experiences from `agency.settings`.
- Agency owners are redirected from the shared `/dashboard` entry point to the agency workspace; travelers retain the existing dashboard.

The following agency-dashboard work was intentionally removed from the runtime after an unstable implementation and remains deferred:

- Agency profile CRUD is active for the current profile/media fields
- Agency experience publishing is active
- Agency document upload is active; review/approval management remains an admin workflow
- Agency inquiry response/status actions are not active
- No traveler-request posting route is active
- Agency owners now use the protected dashboard overview at `agency.dashboard`

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
- `resources/views/components/agency-card.blade.php`: reusable agency card
- `resources/views/auth`: traveler dashboard, saved agencies, activity, and settings pages
- `resources/views/agencies`: agency dashboard views and future agency-only workspace views
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

The active agency-dashboard route is `GET /agency/dashboard` (`agency.dashboard`). It is protected by `auth` and `agency-owner`. Add profile, experience, document, and inquiry mutations only with policies, requests, and feature tests together.

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

The agency dashboard loads only the authenticated owner’s agency and displays profile completion, verification status, trust score, inquiry activity, published review summaries, recent reviews, recent inquiries, pending documents, and recent travel experiences. Agency settings persist public company information, contact details, destinations, services, logo, and cover image; the public agency profile reads these saved values.

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

## Next recommended work

Continue the agency workspace step by step without changing the stable traveler output:

1. Add agency profile CRUD for company data, logo, cover image, countries, and services.
2. Add agency experience CRUD requiring image, destination, caption, and alt text.
3. Add agency document upload and verification status management.
4. Add inquiry inbox and response/status workflow.
5. Add database notifications for traveler inquiries and admin actions.
6. Add feature tests for each role authorization and ownership boundary before expanding the UI.

Do not begin by adding a large dashboard Blade file. Establish controller, Form Request, policy, route, model relationship, and feature-test boundaries first.

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
