# Logistics Automation Notes - Findings & Assumptions

## 1. Schema & Prior Work Verification
- **Athletes:** The `athletes` table has `date_of_birth` and `gender`, but is missing `weight_kg`.
- **Fixtures/Matches:** `fixtures` and `matches` exist. I will verify if they lack `facility_id` and time fields and add them if needed.
- **Medical/Fitness:** `athlete_injuries` and `athlete_medical_clearances` tables already exist! I will use these for the `AthleteFitnessProvider` implementation.
- **Drivers & Vehicles:** No distinct `drivers` table (likely uses `employees` or `user_id` on the `vehicles` table). `vehicles` lacks `insurance_expiry`, `fitness_certificate_expiry`, `cargo_capacity_kg`, etc.
- **Audit Logs:** The `audit_logs` table exists (`organization_id`, `user_id`, `action`, `module`, `table_name`, `record_id`, `old_values`, `new_values`). I will build an `Auditable` trait on top of this.
- **Notifications:** The `notifications` table exists. I will use standard Laravel database notifications mapping to this schema.

## 2. Finance Integration
- Member 5's finance system is present (`FinanceService.php`). There is also a stubbed `ExpenseRecorderInterface.php` inside the Operations namespace. I will expand this to `FinanceExpenseGateway` with `voidExpense()` logic, acting as an adapter wrapping Member 5's logic.

## 3. Deviations & Assumptions
- **Vehicles/Drivers:** I assume drivers are represented by `employee_id` inside `vehicles` and `transport_trips`. I will compute duty hours across `transport_trips`.
- **Soft Deletes:** I will add `deleted_at` to `transport_trips`, `venue_bookings`, and `accommodation_allocations` and adapt the AvailabilityService to ignore them.
- **Cron Job:** Will instruct users to add `* * * * * cd /path-to-project/backend && php artisan schedule:run >> /dev/null 2>&1`.

## Plan Summary
We will break the implementation into the requested 10 phases. Given the sheer scale, I will orchestrate subagents for parallel execution.
