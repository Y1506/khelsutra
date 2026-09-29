# agent.md — KhelSutra · Member 4 (Operations & Logistics)

> Persistent instructions for any AI coding agent working in this repository on branch `feature/operations-logistics`.
> Read this file fully before writing any code. If a rule here conflicts with a task prompt, this file wins.
> **Schema-aligned to the current database dump `khelsutra.sql`** (generated Sep 20, 2026 · MySQL 8.4.7 · PHP 8.3.28 · utf8mb4_unicode_ci).

---

## 1. Project Context

**KhelSutra** is a multi-tenant Sports Management Software.

- **Stack:** Laravel + PHP 8.3, MySQL 8.4, REST API, Blade + Bootstrap + JavaScript, Flutter (mobile consumes the REST API).
- **My role:** Member 4 — Physical Operations, Event Logistics, Resource Manager.
- **Golden rule:** Own venues, bookings, maintenance, housekeeping, events, transport and accommodation. **Never duplicate** users, athletes, employees, permissions, finance, training or tournaments — reference them.

## 2. Team Boundaries (Do NOT build these)

| Member | Owns (existing tables) | How I integrate |
|---|---|---|
| 1 — Auth/RBAC | `users`, `organizations`, `organization_users`, `roles`, `permissions`, `role_permissions`, `user_permission_overrides`, `employees`, `athletes`, `coach_profiles`, `audit_logs`, `notifications` | Use their models, tenant resolver, permission middleware, JSON response helper. |
| 2 — Training | `training_sessions` (has `venue_id`, `facility_id`), `training_camps`, `training_camp_participants`, `teams`, `team_members` | I provide venues/availability/bookings, transport and accommodation for camps and sessions. |
| 3 — Competitions | `tournaments`, `tournament_venues`, `fixtures` (has `venue_id`, `facility_id`), `matches` | They use MY `venues`/`venue_facilities`. **Do not build a second tournament-venue system.** `tournament_venues` is theirs — I only read it. |
| 5 — Finance/Procurement | `expenses`, `finance_categories`, `finance_payments`, `vendors`, `vendor_invoices`, `budgets`, purchase tables, inventory/equipment | Send maintenance/transport/event costs into `expenses` through one adapter. **No finance tables of my own.** I only *reference* `vendors`. |

If a needed class from another member doesn't exist yet: create a thin interface + stub, document it in `docs/operations/INTEGRATIONS.md`, and continue. Never invent a competing schema.

## 3. My Modules → Existing Tables (**they already exist — do not recreate them**)

| Module | Table(s) in `khelsutra.sql` | Suggested model | Notes |
|---|---|---|---|
| Venue | `venues` | `Venue` | `venue_code` unique per org; `status`: active/inactive/under_maintenance; `opening_time`/`closing_time`; lat/long; `capacity` |
| Facility | **`venue_facilities`** (not `facilities`) | `Facility` (`$table='venue_facilities'`) | `facility_type`, `capacity`, `status` same enum as venue |
| Booking | `venue_bookings` | `VenueBooking` | `booking_date` + `start_time`/`end_time` (TIME, single-day); links `team_id`, `event_id`, `tournament_id`; status: pending/approved/rejected/cancelled/completed |
| Maintenance | **`venue_maintenance`** (singular) | `VenueMaintenance` (`$table`) | `issue_title`, `priority` (low/medium/high/critical), `assigned_employee_id`, `assigned_vendor_id` → `vendors`, `scheduled_date`, `completed_date`, `estimated_cost`, `actual_cost` |
| Housekeeping | `housekeeping_tasks` | `HousekeepingTask` | `task_type`, `assigned_employee_id`, `scheduled_date` + times, status pending/assigned/in_progress/completed/cancelled. **No `priority` column yet → added by delta (§4)** |
| Events | `events`, `event_participants` | `Event`, `EventParticipant` | `event_type` (varchar), `start_date`/`end_date`, `venue_id`, `organizer_employee_id`, status draft/planned/ongoing/completed/cancelled |
| School activities | `school_activities` | `SchoolActivity` | `school_name`, `activity_name`, `activity_date`, optional `event_id`, `sport_id`, `venue_id`, `participant_count` |
| Vehicles | `vehicles` | `Vehicle` | `vehicle_number` unique per org, `capacity` (nullable), `driver_employee_id`, insurance/registration expiry, status available/assigned/maintenance/inactive |
| Trips | `transport_trips` | `TransportTrip` | `trip_date`, `departure_time`, `return_time`, `origin`, `destination`, `purpose`, `driver_employee_id`; only `event_id` link today |
| Passengers | `transport_passengers` | `TransportPassenger` | FK column is **`transport_trip_id`**; `athlete_id`/`employee_id`/`coach_id` or free-text `passenger_name` |
| Accommodation | `accommodations` | `Accommodation` | address, lat/long, `total_rooms` (informational), status active/inactive |
| Rooms | **`accommodation_rooms`** | `AccommodationRoom` | `room_number` unique per accommodation, `capacity`, status available/occupied/maintenance/inactive |
| Allocations | **`accommodation_allocations`** | `AccommodationAllocation` | `accommodation_id`, `room_id`, `event_id`, participant ids, `check_in_date`, `check_out_date` (nullable), status reserved/checked_in/checked_out/cancelled |

### Schema gotchas the code MUST respect
1. **Participants are NOT `user_id`.** Every participant table uses `athlete_id` / `employee_id` / `coach_id` (`coach_id` = `coach_profiles.id`, which itself links to `employees.id`); `event_participants` also has `participant_type` (athlete/employee/coach/team) and `team_id`. Exactly **one** identity per row, and it must match `participant_type` for events. `passenger_name` is for external passengers (no profile).
2. **Non-standard names:** always set `protected $table` and foreign keys explicitly (`venue_facilities`, `venue_maintenance`, `accommodation_rooms`, `accommodation_allocations`, `transport_trips`, `transport_passengers`, `transport_trip_id`).
3. **Timestamps:** `event_participants` and `transport_passengers` have **only `created_at`** → set `public const UPDATED_AT = null;`.
4. **SoftDeletes (`deleted_at` exists) on:** venues, venue_facilities, venue_bookings, venue_maintenance, housekeeping_tasks, events, school_activities, vehicles, accommodations. **No `deleted_at` on:** transport_trips, transport_passengers, accommodation_rooms, accommodation_allocations, event_participants → cancel via `status` (or delete only when no dependants).
5. **References/codes unique per org:** `venue_code`, `booking_reference`, `maintenance_reference`, `task_reference`, `event_reference`, `trip_reference`, `vehicle_number`. Generate server-side (see §5.5), never trust client values without a uniqueness check.
6. **`users` has NO `organization_id`.** Tenancy is via `organization_users` (`organization_id`, `user_id`, `role_id`, `employee_id`, `athlete_id`, `access_status`).
7. **Keys named `fk_*` do include explicit foreign-key constraints.** Ensure they are respected when inserting test data or updating records. While the baseline schema may have been imported with InnoDB, ensure explicit transaction locking is used for critical paths. Referential integrity and tenant ownership must be enforced in application code.
8. Money is `decimal(14,2)`; coordinates `decimal(10,7)`; default country `'India'`.

## 4. Schema Delta (additive migration — the ONLY schema changes I make)

Create Laravel migration(s) prefixed `operations_` that are **additive and idempotent** (`Schema::hasTable` / `hasColumn` guards, working `down()`), and never `DROP`/recreate an existing table.

**A. Engine → InnoDB (mandatory, critical).** MyISAM ignores `DB::transaction()` (no rollback) and `lockForUpdate()` (no row locks), which would silently defeat the booking-conflict and room-capacity guarantees. Convert **my** tables: `venues, venue_facilities, venue_bookings, venue_maintenance, housekeeping_tasks, events, event_participants, school_activities, vehicles, transport_trips, transport_passengers, accommodations, accommodation_rooms, accommodation_allocations` via `ALTER TABLE ... ENGINE=InnoDB`. Do not convert other members' tables in my migration; instead raise a team-wide request (put it in the PR description and `docs/operations/INTEGRATIONS.md`) — the whole DB should be InnoDB. Do **not** add FK constraints to other members' tables.

**B. Tenant column on child tables** (they have none, and the rule is "every query filters by `organization_id`"): add `organization_id BIGINT UNSIGNED NOT NULL` + index to `event_participants`, `transport_passengers`, `accommodation_rooms`, `accommodation_allocations`. Backfill from the parent (`events`, `transport_trips`, `accommodations`) in the same migration (add nullable → backfill → set NOT NULL). Models auto-fill it from the parent/context. `tournament_venues` is Member 3's — leave it alone and validate via `tournaments.organization_id` + `venues.organization_id`.

**C. Missing columns for required features** (all nullable unless stated):
| Table | Add | Why |
|---|---|---|
| `housekeeping_tasks` | `priority` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium' | Brief requires housekeeping priority |
| `venue_bookings` | `cancelled_at` TIMESTAMP, `cancelled_by` BIGINT UNSIGNED, `training_session_id` BIGINT UNSIGNED, `fixture_id` BIGINT UNSIGNED | Cancellation audit; hooks for Member 2/3 (confirm with them before relying on these) |
| `transport_trips` | `training_camp_id`, `tournament_id`, `expense_id` | Brief: trips for Training Camps/Tournaments/Events; finance link |
| `accommodation_allocations` | `training_camp_id`, `tournament_id` | Same |
| `venue_maintenance` | `expense_id` | Finance link (no change to `expenses`) |

**D. Indexes:** `venue_bookings (organization_id, venue_id, facility_id, booking_date, status)`; `accommodation_allocations (organization_id, room_id, status, check_in_date, check_out_date)`; `transport_trips (organization_id, vehicle_id, trip_date)`; `event_participants (organization_id, event_id)`; `transport_passengers (organization_id, transport_trip_id)`.

**E. Baseline vs. migrations.** The dump is the baseline schema. First, inspect the repo: if Laravel migrations already create these tables, add only the delta on top. If the schema exists only as this SQL dump, commit it as `database/sql/khelsutra.sql` (if not already tracked), treat it as the baseline (Docker/native setup loads it, see §8), and ship **only** the delta migrations. **Never** run `migrate:fresh` / `RefreshDatabase` against a database whose baseline comes from the dump — it would wipe it. If you also need a plain SQL for teammates, generate `database/sql/operations_delta.sql` mirroring the migrations.

## 5. Git Rules

```bash
git checkout develop
git pull origin develop
git checkout -b feature/operations-logistics   # or checkout if it exists
```

- Never push to `main`; never commit directly to `develop`. PR against `develop`.
- **Local commits are allowed** without explicit confirmation, provided they follow semantic commit guidelines.
- **No pushing to remote until I explicitly confirm.** After making local commits, the agent must display a final summary of the commits and explicitly ask "Would you like me to push these changes?" before running `git push`.
- **Semantic commits, small and frequent:** `feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `chore:` (e.g. `feat: implement venue booking`, `test: add venue and logistics tests`).
- One commit per logical unit (Docker, delta migration, model+service, controller+routes, tests).

## 6. Non-Negotiable Architecture Rules

### 6.1 Tenant Isolation (MANDATORY)
- Every query filters by `organization_id` resolved **on the backend** from Member 1's tenant context (authenticated user → active `organization_users` row with `access_status='active'`). Find and reuse their resolver; **never** read `organization_id` from request input/route/headers. Strip it in FormRequests.
- `BelongsToOrganization` trait with a **global scope** + auto-set on `creating` on every model (all 14 tables, including child tables after delta B). Role `Super Admin` has global access — handle only through Member 1's mechanism; never add a bypass of my own.
- **Validate every referenced id belongs to the same org:** `venue_id`, `facility_id` (and that the facility belongs to that venue), `room_id`/`accommodation_id` (consistent with each other), `vehicle_id`, `athlete_id`, `employee_id`, `coach_id` (via `coach_profiles.organization_id`), `team_id`, `event_id`, `tournament_id`, `training_camp_id`, `training_session_id`, `assigned_vendor_id`, `driver_employee_id`, `organizer_employee_id`, `expense_id`. `sport_id` may be the org's own **or** `sports.is_global = 1`. Use scoped route-model binding or `Rule::exists(...)->where('organization_id', $orgId)`.
- Cross-org access returns **404** (not 403).

### 6.2 API Standards
- Routes under `/api/v1/` (e.g. `/api/v1/venues`, `/api/v1/venues/{venue}/facilities`, `/api/v1/bookings`, `/api/v1/facilities/{id}/availability`, `/api/v1/vehicles`, `/api/v1/trips`, `/api/v1/accommodations`, `/api/v1/rooms`, `/api/v1/room-allocations`, `/api/v1/events`, `/api/v1/school-activities`, `/api/v1/maintenance`, `/api/v1/housekeeping`).
- Use **Member 1's standard JSON response format** (find their helper). API Resources for output, FormRequests for validation, pagination on lists, codes: 201 create, 404, 409 conflict, 422 validation.

### 6.3 RBAC — use the permissions that ALREADY exist (singular names, seeded in `permissions`)
Do **not** invent `venues.create`-style names. Map like this (manage implies view; use an any-of check via Member 1's middleware; their `user_permission_overrides` resolver decides — don't reimplement):

| Action | Permission(s) |
|---|---|
| View venues/facilities/bookings/maintenance/housekeeping | `venue.view` (or any `venue.*` / `housekeeping.manage` manage perm) |
| Create / update venue & facility | `venue.create` / `venue.update` |
| Delete, change status | `venue.manage` |
| Create a booking | `booking.create` |
| Approve / reject / reschedule / cancel any booking | `venue.booking.manage` (a requester may cancel their own pending booking) |
| Maintenance tickets | `venue.maintenance.manage` |
| Housekeeping tasks | `housekeeping.manage` |
| View events & school activities | `event.view` |
| Create/update events, participants, school activities | `event.manage` |
| Vehicles, trips, passengers | `transport.manage` (view: `event.view` or `transport.manage`) |
| Accommodation, rooms, allocations | `accommodation.manage` (view: `event.view` or `accommodation.manage`) |
| Referencing vendors / recording expenses | `vendor.manage` / `finance.manage` are Member 5's — I only *use* the adapter |

Only add a new permission if nothing fits; do it through Member 1's seeder (idempotent, unique `name` and `module+action`), grant to roles via `role_permissions`, and note it in the PR. Every route (API **and** web) sits behind auth + a permission check.

### 6.4 Thin Controllers, Fat Services
- Controllers: validate (FormRequest) → authorize → call Service → return Resource. No business logic.
- Services in `app/Services/Operations/`: `VenueService`, `FacilityService`, `VenueBookingService`, `VenueAvailabilityService`, `MaintenanceService`, `HousekeepingService`, `EventService`, `SchoolActivityService`, `VehicleService`, `TransportTripService`, `AccommodationService`, `RoomAllocationService`, `ReferenceGenerator`, and the finance adapter `ExpenseRecorder` (interface + implementation).
- `DB::transaction()` for every multi-step write.

### 6.5 Critical Business Logic

**Locking pattern (works only because of delta A / InnoDB):** inside `DB::transaction()`, first `lockForUpdate()` the **parent row** that serializes the resource — the *venue* row for bookings, the *room* row for allocations, the *vehicle* row for trips, the *trip* row for passengers, the *event* row for participants — then check, then insert. Locking candidate rows alone leaves a gap-race, so lock the parent.

**Venue booking.** Conflict when, for the same org and venue, the same `booking_date`, blocking status, and `existing.start_time < new.end_time AND existing.end_time > new.start_time` (half-open: 10:00–12:00 booked → 11:00–13:00 **rejected**, 12:00–13:00 **allowed**).
- Facility scope: if the new booking has a `facility_id`, it conflicts with bookings on **that facility or whole-venue bookings (`facility_id IS NULL`)**; if the new booking is whole-venue, it conflicts with **any** booking at that venue.
- Blocking statuses: `pending`, `approved`, `completed`. Non-blocking: `rejected`, `cancelled`. Exclude soft-deleted rows. On reschedule exclude the booking itself.
- Also reject: `end_time <= start_time` (single-day only; no overnight), venue or facility `status != 'active'`, facility not in that venue, booking outside `opening_time`/`closing_time` when set, booking in the past (configurable).
- Default status: `approved` if the creator has `venue.booking.manage`, else `pending`. Approval sets `approved_by`/`approved_at`.
- Transitions: pending→approved|rejected|cancelled; approved→cancelled|completed. **Cancel** sets `status='cancelled'`, `cancelled_at`, `cancelled_by`, `cancellation_reason`, and frees the slot.
- Conflict → **409** with the conflicting booking's reference/time window (never another org's data). Availability endpoint returns free/busy windows using the same query (single source of truth: `VenueAvailabilityService`, also consumed by Members 2 and 3).

**Room allocation.** Active statuses: `reserved`, `checked_in`. Require `check_out_date > check_in_date` for new rows (legacy NULL check-out is treated as open-ended). Overlap: `existing.check_in_date < new.check_out AND (existing.check_out_date IS NULL OR existing.check_out_date > new.check_in)`. Reject when overlapping active allocations `>= room.capacity` (**409**). Also reject: room `status` maintenance/inactive, accommodation inactive, `room.accommodation_id != accommodation_id`, and the same athlete/employee/coach already holding an overlapping active allocation. Do not use room `status='occupied'` as the capacity guard (capacity is the guard). Transitions: reserved→checked_in→checked_out; reserved→cancelled.

**Vehicles & trips.** Passengers (non-cancelled) ≤ `vehicle.capacity`; if capacity is NULL, reject passenger assignment with a clear message until capacity is set. No duplicate passenger per trip. Vehicle must be `available`/`assigned`, and (default rule, confirm) `registration_expiry_date`/`insurance_expiry_date` must not be past `trip_date`. No overlapping active trip (`planned`/`in_progress`) for the same vehicle or driver on the same `trip_date` (null `departure_time` = start of day, null `return_time` = end of day). Transitions: planned→in_progress→completed; planned→cancelled.

**Events & school activities.** Participants are existing athletes/employees/coaches/teams — never new profiles; unique per (event, type, id); `participant_type` must match the populated column. An event may optionally book a venue through `VenueBookingService` (bookings link back with `event_id`).

**Maintenance & housekeeping.** Maintenance transitions: reported→assigned→in_progress→completed; any (except completed)→cancelled. Housekeeping: pending→assigned→in_progress→completed; →cancelled. Assignees must be employees of the same org. Do **not** silently change a facility's status; changing it is an explicit action.

**References.** Generate `booking_reference`, `maintenance_reference`, `task_reference`, `event_reference`, `trip_reference` inside the transaction (e.g. `BK-20260920-0001`), unique per org; retry on duplicate.

**Deletion.** Soft-delete venues/facilities/vehicles/accommodations only when no future active bookings/trips/allocations reference them (else **409**). Rooms/allocations/trips have no `deleted_at`: use status.

### 6.6 Finance Integration (no finance tables of my own)
- One adapter: `ExpenseRecorder` (interface) → implementation that calls **Member 5's expense service** if it exists, otherwise creates rows through Member 5's `Expense` model on the existing **`expenses`** table. This is the only place my module touches finance.
- Required `expenses` fields: `expense_reference` (unique per org), `expense_date`, `description`, `amount`, `tax_amount` (0), `total_amount`, `payment_status='pending'`, `created_by`. Set `vendor_id` (maintenance vendor), `event_id` (event costs), and `finance_category_id` by looking up an existing **active expense** category for that org (e.g. "Maintenance", "Transport", "Events" from `finance_categories`) — never create categories; leave null if none.
- Maintenance: on completion with `actual_cost > 0` record once and save `venue_maintenance.expense_id` (idempotent: if set, update instead of duplicating). Trips: same with `transport_trips.expense_id`. Event costs: `expenses.event_id`.
- Do **not** alter the `expenses` table.

### 6.7 No Duplicate Tables
Never create: users, employees, athletes, coaches, roles, permissions, expenses/payments/invoices/vendors, tournaments, training tables. Do not add a second venue/tournament-venue system.

## 7. Frontend Rules
- **Blade + Bootstrap + JS**, reuse the shared KhelSutra layout and Blade components; no other CSS framework.
- Views under `resources/views/operations/{venues,facilities,bookings,maintenance,housekeeping,events,school-activities,transport,accommodation}/`.
- Booking calendar/list fed by `/api/v1/` endpoints (use the calendar lib already in the project; else FullCalendar via the existing asset pipeline).
- Show validation and conflict messages clearly; confirm on cancel/delete; hide actions the user lacks permission for (server still enforces). Participant pickers list athletes/employees/coaches of the current org only.
- Web routes in `routes/web.php` (or a module route file); API in `routes/api.php` under `v1`.

## 8. Docker Rules (Docker-first for me, Docker-optional for the app)

**Situation:** I develop on Linux with **no PHP, MySQL, or phpMyAdmin on my host**. Docker is my primary dev environment from day one — the first thing built on the branch. I commit `Dockerfile`, `docker-compose.yml`, `.dockerignore`. **My teammates do not have Docker and don't know it**, so the app MUST also run natively (PHP + Composer + MySQL + Node) exactly as in containers.

### A. Docker environment (Phase 1)
- **Pin versions to the dump:** PHP **8.3**, MySQL **8.4**, `utf8mb4` / `utf8mb4_unicode_ci` (`--character-set-server=utf8mb4 --collation-server=utf8mb4_unicode_ci`), same `sql_mode` behaviour as the dump.
- **Services:** `app` (PHP 8.3 + Composer + `pdo_mysql`, `mbstring`, `bcmath`, `intl`, `zip`, `gd`, and the `mysql`/`mariadb` client for imports; Node if the repo builds assets), `web` (nginx/Apache → `public/`), `db` (MySQL 8.4, named volume, healthcheck), `phpmyadmin` (host port, default 8081). Optional `mailpit`; nothing may require it.
- **Load the baseline dump** into two databases on first init: mount an init script in `/docker-entrypoint-initdb.d/` that creates `khelsutra` and `khelsutra_test` and imports `database/sql/khelsutra.sql` into both. Warn in docs: the dump contains `DROP TABLE IF EXISTS`, so re-importing **resets data**; provide `make db-reset`. Init scripts only run on an empty volume.
- Then `php artisan migrate` applies only my delta (§4). Run tests against `khelsutra_test`.
- **Linux file ownership:** run `app` as host UID/GID (build args, default 1000); bind-mount the repo.
- **All tooling runs in containers** (`docker compose exec app php artisan ...`, `... composer ...`, `... php artisan test`); provide a `Makefile` (`up`, `down`, `shell`, `artisan cmd=`, `composer cmd=`, `test`, `db-reset`). **Git runs on the host.**
- First run must be documented and tested: `docker compose up -d --build` → `composer install` → copy `.env` → `key:generate` → `migrate`.
- Host ports env-overridable (`APP_PORT`, `DB_FORWARD_PORT`, `PMA_PORT`). Don't commit `.env`, volumes, or DB dumps other than the tracked baseline `database/sql/khelsutra.sql`.

### B. Keeping the app Docker-independent (non-negotiable)
- `.env.example` is native-friendly: `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=khelsutra`, `CACHE_DRIVER=file`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`.
- Docker overrides live in `docker-compose.yml` `environment:` (real env vars override `.env` in Laravel) or `.env.docker.example` — never in the default `.env.example`.
- No hardcoded container hostnames/paths (`db`, `mysql`, `/var/www/html`) in code, migrations, seeders, tests, or committed config.
- No feature may require Redis/Mailpit/etc.; everything works with file/database/log drivers.
- Every documented workflow has a plain (non-Docker, non-`make`) equivalent; teammates may be on Windows/macOS.

### C. Test database strategy (because the schema comes from a dump, not migrations)
- **Do not use `RefreshDatabase` / `migrate:fresh`** — they would wipe the baseline. Use a pre-loaded `khelsutra_test` (baseline + delta migrations) and `DatabaseTransactions`.
- `DatabaseTransactions` only rolls back **InnoDB** tables. My tables are converted by delta A; other members' tables (`organizations`, `users`, `athletes`, …) are MyISAM until the team converts them, so tests must create fixture rows with unique codes/emails and clean up (or use a small `CleansOperationsFixtures` helper). Write one test that proves rollback works on my tables.
- Env-driven test DB (`DB_DATABASE` in `phpunit.xml`/`.env.testing`); no Docker-based test services. The same suite must pass via plain `php artisan test` natively.
- Fixtures: the dump seeds one org (`ORG-DEMO`, id 1), three users (admin/coach/athlete) and roles — tenant-isolation tests must create a **second organization** with its own users/venues/etc.

### D. Docs & verification
- `docs/operations/SETUP.md`: **"Run without Docker (default for teammates)"** first — create DB, `mysql -u <user> -p khelsutra < database/sql/khelsutra.sql` (or phpMyAdmin import), `composer install`, `.env`, `key:generate`, `php artisan migrate`, `php artisan serve`, `php artisan test` — then **"Run with Docker (optional)"**, beginner-friendly, incl. phpMyAdmin URL/credentials and DB reset.
- Docker setup is its own first commit: `chore: add docker development environment`.
- Before the PR: verify the Docker workflow end-to-end and that no code assumes Docker (grep for `db`/`mysql`/`/var/www` hostnames). If native PHP isn't available in this environment, say so in the PR rather than claiming a native run.

## 9. Testing (Critical Path — Required)
Use Pest or PHPUnit (match the repo). Factories for every model in this module.

- [ ] Create venue and facility (API + validation failures; facility must belong to a venue of the same org).
- [ ] **Overlap rejected:** with 10:00–12:00 booked → reject 11:00–13:00, 09:00–11:00, 10:30–11:30, 09:00–13:00, identical; **allow** 12:00–13:00, another day, a cancelled/rejected slot. Facility vs whole-venue conflicts both ways. Reschedule excludes itself.
- [ ] **Room capacity rejected** when exceeded; same participant double-allocation rejected; check-out/check-in same-day turnover allowed; concurrent-safe path.
- [ ] Vehicle passenger capacity, duplicate passenger, vehicle/driver overlap.
- [ ] **Tenant isolation** for every resource (venues, facilities, bookings, maintenance, housekeeping, events, participants, school activities, vehicles, trips, passengers, accommodations, rooms, allocations): Org A cannot list/show/update/delete Org B data (404); payload `organization_id` ignored; Org B foreign ids (facility, athlete, vendor, vehicle…) inside Org A requests rejected.
- [ ] RBAC: missing permission → 403 using the existing permission names.
- [ ] Booking cancellation frees the slot; audit fields set.
- [ ] Finance adapter called once per completed maintenance/trip (idempotent).
- [ ] Delta migration: runs twice safely; child-table backfill correct.

Run: `docker compose exec app php artisan test` (or `make test`) — green before PR; the same suite must pass natively.

## 10. Code Quality
- PSR-12, Laravel Pint (`./vendor/bin/pint`) if configured. Enums/constants for statuses and priorities, not magic strings. Money via `decimal:2` casts.
- Eager load (`with()`); no raw SQL with interpolated input; the overlap query uses bindings.
- Seeders/factories for every model; migrations idempotent with `down()`.

## 11. Working Protocol for the Agent
1. **Inspect first:** read existing models, migrations (or confirm there are none), middleware, tenant resolver, response helper, permission seeder, layouts; diff the real DB against §3–4 and report any mismatch with this file before coding.
2. **Build in order:** Docker → Delta migration & foundation → Venues/Facilities → Bookings → Maintenance/Housekeeping → Events/School activities → Transport → Accommodation → Integrations → Tests/hardening → Docs.
3. After each phase: migrate + test **inside the `app` container**, commit from the host with a semantic message.
4. If a dependency is missing, use interface + stub (§2), document it, continue. Don't block, don't duplicate.
5. Don't modify other members' files beyond minimal, documented touchpoints. Never commit secrets or `.env`.

## 12. Definition of Done
- [ ] Docker dev environment (app, web, MySQL 8.4 with baseline loaded, phpMyAdmin) works from a fresh clone
- [ ] App has no Docker dependency; `.env.example` native-friendly; `docs/operations/SETUP.md` covers native (first) and Docker
- [ ] Delta migration applied: InnoDB on my tables, `organization_id` on child tables, new columns/indexes; idempotent
- [ ] Venue & Facility CRUD (API + UI) on `venues` / `venue_facilities`
- [ ] Availability + booking with strict conflict detection (facility vs whole-venue aware)
- [ ] Booking cancellation
- [ ] Maintenance (`venue_maintenance`) & Housekeeping (with priority) CRUD
- [ ] Events & School Activities with existing athletes/employees/coaches/teams
- [ ] Vehicles, Trips, Passenger assignment with capacity checks
- [ ] Accommodation, Rooms, Room Allocation with capacity validation
- [ ] Integrations mapped in `docs/operations/INTEGRATIONS.md` (training venues/bookings, `tournament_venues`, `expenses` adapter, InnoDB request to the team)
- [ ] Tenant isolation flawless on every endpoint (incl. child tables and foreign-id validation)
- [ ] Existing permission names used; any new ones seeded via Member 1's mechanism
- [ ] `/api/v1/` REST APIs + Blade/Bootstrap UI complete
- [ ] Tests written and passing (Docker and native)
- [ ] No duplicate tables; `expenses` untouched
- [ ] PR opened against `develop`
