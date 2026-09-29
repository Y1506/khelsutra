# Sport-Aware Linking and Smart Transport Assignment: Findings & Assumptions

## 1. Schema Findings
- `sports` table already exists (`id`, `organization_id`, `name`, `code`, `description`, `status`, `is_global`). I will write a migration to add `slug`, `is_team_sport` (boolean), `min_team_size` (int), and `max_team_size` (int).
- `teams`, `tournaments`, and `training_camps` already have a `sport_id` column.
- `events` and `fixtures` DO NOT have a `sport_id` column. I will add a nullable `sport_id` to both.
- Pivot tables `athlete_sports`, `coach_sports`, `facility_sports`, `venue_sports` do not exist. I will create them scoped to `organization_id`.
- `venue_bookings` does not have a `sport_id` column, but it doesn't strictly need one if it derives from its parent (`team_id`, `event_id`, `tournament_id`) or if the rule is just validated at creation.
- `transport_trips` requires `assignment_mode` and `passenger_count`.

## 2. Assumptions & Touchpoints
- **Members 1-2 (Auth/Athletes/Coaches/Teams):** Adding `athlete_sports` and `coach_sports` pivot tables. Will use a generic `HasSports` trait to link Eloquent models.
- **Member 3 (Competitions):** `tournaments` and `fixtures` will now filter by `sport_id`.
- **Member 4 (Operations - Me):** `venues`, `venue_facilities`, `events`, and `venue_bookings` will implement `SportCompatible` logic.
- **Transactions & Locking:** `lockForUpdate()` will be applied to `Vehicle` rows inside `TransportAssignmentService` during auto-assign commits.
- **Docker/Native Compatibility:** Migrations will strictly use `Schema::table` and `Schema::create` (Delta migrations).

## 3. Plan
1. Create migration `..._add_sports_fields_and_pivots`.
2. Create `HasSports` trait and update Models.
3. Seed `sports` and pivots.
4. Implement `SportContextService` and `SportCompatible` rule.
5. Update Controllers/Views for dependent dropdowns.
6. Implement `TransportAssignmentService` and update UI for auto-assignment.
