# KhelSutra

![PHP](https://img.shields.io/badge/php-%23777BB4.svg?style=for-the-badge&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/laravel-%23FF2D20.svg?style=for-the-badge&logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/mysql-%2300f.svg?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/bootstrap-%238511FA.svg?style=for-the-badge&logo=bootstrap&logoColor=white)
![Flutter](https://img.shields.io/badge/Flutter-%2302569B.svg?style=for-the-badge&logo=Flutter&logoColor=white)

## 1. Project Overview & Architecture
KhelSutra is a comprehensive, multi-tenant Sports Management Software designed for sports academies, federations, and organizations. The platform provides end-to-end management of athletes, coaching staff, training sessions, tournaments, physical infrastructure (venues & accommodation), transportation logistics, and finance. It supports strict Role-Based Access Control (RBAC) securely scoped per tenant (organization).

**Architecture Stack:**
*   **Web Frontend:** Blade Templates, Bootstrap 5, JavaScript (Native, Neumorphic UI Design system)
*   **Mobile Frontend:** Flutter (Consumes REST API)
*   **Backend:** Laravel 11.x (PHP 8.3) - Custom routing layer via `public/index.php`
*   **Database:** MySQL 8.4 (InnoDB engine with strict ACID compliance and row-level locking for conflict prevention)
*   **Infrastructure:** Docker containerized environment

## 2. Prerequisites & Environment Setup
Ensure the following tools are installed on the deployment machine:
*   PHP 8.3+
*   Composer v2+
*   MySQL 8.4+ 
*   Docker & Docker Compose (Primary Development Environment)

### Environment Variables
Create a `.env` file in the root directory. Use `.env.example` as a reference.
*   `DB_CONNECTION`: `mysql` 
*   `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: Database credentials
*   `APP_PORT`, `DB_FORWARD_PORT`, `PMA_PORT`: Host ports for Docker override

## 3. Deployment Instructions

### Option A: Docker Deployment (Recommended)
KhelSutra is designed to be Docker-first for development.
1. **Initialize the Docker Environment:**
   ```bash
   docker compose up -d --build
   ```
2. **Install Dependencies:**
   ```bash
   docker compose exec app composer install
   ```
3. **Database Initialization (Baseline Dump):**
   The database requires the baseline dump `database/sql/khelsutra.sql` to be imported first (handled automatically by the init script on an empty volume, or manually via phpMyAdmin).
4. **Run Migrations (Deltas):**
   ```bash
   docker compose exec app php artisan migrate
   ```

### Option B: Local Development Setup (Manual)
1. **Backend Initialization:**
   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```
2. **Database Setup:**
   Create a database named `khelsutra` and `khelsutra_test`. Import the baseline `database/sql/khelsutra.sql` into the `khelsutra` database.
3. **Migrate the Schema:**
   ```bash
   php artisan migrate
   ```
4. **Serve the Application:**
   ```bash
   php artisan serve
   ```

## 4. Database Schema
KhelSutra utilizes a strictly scoped multi-tenant architecture. Every core entity is tied to an `organization_id` to ensure complete data isolation.

### Entity Relationship Diagram (ERD) Core Modules
*   **Auth & HR Module:** `users`, `organizations`, `organization_users`, `roles`, `permissions`, `employees`, `athletes`, `coach_profiles`
*   **Training Module:** `training_sessions`, `training_camps`, `teams`, `team_members`
*   **Competitions Module:** `tournaments`, `fixtures`, `matches`
*   **Operations & Logistics Module:** `venues`, `venue_facilities`, `venue_bookings`, `accommodations`, `accommodation_allocations`, `vehicles`, `transport_trips`, `events`, `housekeeping_tasks`, `venue_maintenance`
*   **Finance Module:** `expenses`, `finance_categories`, `vendors`, `budgets`

### Key Architectural Constraints
*   **Tenant Isolation:** All queries strictly filter by `organization_id` resolved from the active session context.
*   **Concurrency Locking:** High-conflict operations (Venue Booking, Room Allocation) utilize InnoDB's `lockForUpdate()` pattern inside explicit transactions to guarantee capacity constraints and prevent overlaps.
*   **Polymorphic Patterns:** Participants in Events and Transport are strongly typed via `participant_type` linking to athletes, employees, or coaches.

## 5. API Reference
All endpoints reside under `/api/v1/` and enforce a standardized JSON response format.

### Authentication & Profiles
*   `POST /api/v1/login` - Authenticate user and issue token
*   `GET /api/v1/profile` - Retrieve current user and active organization context
*   `POST /api/v1/organizations` - Create a new tenant (Super Admin only)

### Operations: Venues & Facilities
*   `GET /api/v1/venues` - List venues
*   `POST /api/v1/venues` - Create a new venue
*   `GET /api/v1/venues/{id}/facilities` - Get facilities for a specific venue

### Operations: Bookings & Allocations
*   `GET /api/v1/bookings` - List venue reservations with overlap detection
*   `POST /api/v1/bookings` - Create a reservation (409 on overlap)
*   `PATCH /api/v1/bookings/{id}` - Approve, complete or reject booking

### Operations: Transport & Logistics
*   `GET /api/v1/vehicles` - List fleet inventory
*   `GET /api/v1/trips` - View scheduled trips and passenger manifests
*   `POST /api/v1/trips` - Plan a new vehicle trip (checks driver/vehicle availability)

### Operations: Accommodation
*   `GET /api/v1/accommodations` - List active properties
*   `GET /api/v1/accommodations/{id}/rooms` - Retrieve rooms for a property
*   `POST /api/v1/room-allocations` - Assign athletes to rooms (checks capacity bounds)

### Competitions & Training
*   `GET /api/v1/tournaments` - List all tournaments
*   `POST /api/v1/tournaments` - Create a new tournament
*   `GET /api/v1/training-sessions` - List scheduled training sessions
*   `POST /api/v1/teams` - Create a new team and assign a primary coach
