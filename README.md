# KhelSutra — Multi-Organisation Sports Management Platform

KhelSutra is a multi-organisation Sports Management Software platform (Sports ERP) engineered to provide sports academies, state federations, clubs, schools, and tournament organizers with a centralized, cloud-ready operational backbone.

The platform provides isolation across organisations managed by a Super Admin, delivering comprehensive administrative, competitive, athletic, inventory, and financial management capabilities.

---

## 1. Overview
KhelSutra streamlines the entire sports management lifecycle:
- Multi-organisation onboarding and subscription lifecycle.
- Athlete lifecycle tracking from registration, medical records, and performance metrics to competition history.
- Coach assignments, team building, training scheduling, and daily attendance.
- Facility, ground, and court booking with automated conflict resolution.
- Tournament orchestration, bracket/group scheduling, fixtures, and standings.
- HR, leave management, and monthly payroll calculation for staff and coaches.
- Equipment, inventory stock management, and full vendor purchase workflows.
- Dynamic web portal and role-aware Flutter mobile companion.

---

## 2. Features
- **Tenant Isolation**: Deep multi-tenancy securing organizational data at the database, query, and API layer.
- **RBAC Matrix**: 7 pre-defined system roles with granular module-level permissions and tenant overrides.
- **Unified Sports Roster**: Configurable sport metrics, team hierarchies, and athlete historical records.
- **Competitions & Fixtures**: Multi-format tournament engine (Knockout, League, Round Robin, Group+Knockout).
- **Facility Engine**: Venue hierarchy (Organisation → Venue → Facility/Court) with maintenance and housekeeping tracking.
- **Procurement & Inventory**: Purchase requests, orders, goods receipts, and itemized equipment assignment.
- **Role-Aware Mobile App**: Flutter app dynamically adjusting UI for Coaches and Athletes.

---

## 3. Architecture

```
[Flutter Mobile App]       [Web Browser]       [External Clients]
         │                       │                      │
         └───────────────┬───────┴──────────────────────┘
                         │ HTTPS / REST JSON
                         ▼
        ┌───────────────────────────────────┐
        │       Laravel 10/11 Backend       │
        │                                   │
        │  [EnsureOrganizationAccess]       │
        │  [RequirePermission Middleware]   │
        │  [V1 API Versioned Controllers]   │
        │                 │                 │
        │  [Form Requests Validation]       │
        │                 │                 │
        │  [Domain Service Layer]           │
        │                 │                 │
        │  [Repository Abstraction]         │
        │                 │                 │
        │  [Eloquent Models + Tenant Scope] │
        └─────────────────┬─────────────────┘
                          │ PDO / MySQL 8.0+
                          ▼
        ┌───────────────────────────────────┐
        │   MySQL Multi-Tenant Database     │
        │   (khelsutra schema - 60+ tables) │
        └───────────────────────────────────┘
```

---

## 4. Technology Stack
- **Backend**: Laravel 10/11, PHP 8.2+, Composer, MySQL 8+, Laravel Sanctum (Token Auth).
- **Web**: Blade templating, Bootstrap 5, Vanilla JavaScript, CSS3.
- **Mobile**: Flutter 3.x, Dart 3.x, REST API Integration.
- **Database**: MySQL 8.0+ (`sports_management_database.sql`).
- **Cloud & Deployment**: AWS (EC2/ECS, RDS Aurora MySQL, S3, CloudFront).
- **API Documentation**: OpenAPI 3.0 (Swagger) and Postman Collections.

---

## 5. Project Structure
```
KhelSutra/
├── backend/          # Laravel Backend Application
├── web/              # Web application assets, views, layouts, and components
├── mobile/           # Flutter Mobile Application
├── database/         # Master SQL schema, diagrams, and seeders
├── docs/             # 10-part comprehensive documentation suite
├── tests/            # System & integration test suites
├── postman/          # Postman collection & environments
├── swagger/          # OpenAPI 3.0 specifications
├── scripts/          # Automation and development utility scripts
├── deployment/       # AWS deployment architecture and Docker configurations
├── .github/          # GitHub Actions CI/CD workflows
├── .editorconfig     # Uniform editor formatting rules
├── .gitignore        # Root gitignore
├── .env.example      # Root environment template
├── README.md         # Master project documentation
├── CONTRIBUTING.md   # Team collaboration & branching guidelines
├── CHANGELOG.md      # Project version history
└── LICENSE           # MIT License
```

---


### Logistics Automation & Scheduling
*   `GET /api/v1/context/{type}/{id}` - Resolve logistics context for tournaments, events, or fixtures
*   `GET /api/v1/{resource}/{id}/audit` - Fetch full audit history for Bookings, Trips, and Allocations
*   `GET /api/v1/calendar/resources` - Unified resource timeline API mapping bookings, trips, and allocations
*   `GET /api/v1/scheduling/clashes` - Report double-bookings and scheduling conflicts

**Idempotency**
POST requests in the Operations module support the optional `Idempotency-Key` header to safely retry network failures without duplicating records.

**Scheduler Setup (Required)**
To process waitlists, auto-turnover tasks, and compliance warnings, add the following to your crontab:
`* * * * * cd /home/y1506/Documents/Programming/Projects/khelsutra/backend && php artisan schedule:run >> /dev/null 2>&1`

## 6. User Roles
The platform operates on 7 predefined roles:
1. **Super Admin**: Software provider admin with global multi-organization access.
2. **Sports Administrator**: Full operational administrative control over an organization.
3. **HR & Finance**: Single combined role for staff, payroll, leave, budgets, and expenses.
4. **Coach**: Team management, training plans, attendance, and match tactics.
5. **Athlete**: Personal schedules, attendance, performance statistics, and leave requests.
6. **Venue & Tournament Manager**: Single combined role managing grounds, bookings, maintenance, and tournaments.
7. **Inventory Manager**: Equipment stock, procurement, and vendor purchase workflows.

---

## 7. Multi-Tenancy
Tenant isolation is enforced through:
- **Middleware**: `EnsureOrganizationAccess` validates user membership and active subscription status.
- **Tenant Scope**: Eloquent models use the `BelongsToOrganization` trait, ensuring every query includes `WHERE organization_id = ?`.
- **Super Admin Bypass**: Explicitly bypassed for platform administrators performing cross-tenant auditing.

---

## 8. Database
The database schema (`database/sports_management_database.sql`) is the **immutable source of truth**:
- 60+ normalized relational tables.
- Foreign keys enforcing referential integrity.
- Soft deletes on critical business records (`deleted_at`).
- Files stored as cloud storage paths (`file_path`), not binary blobs.

---

## 9. Backend
The backend adheres strictly to Clean Layered Architecture:
`Controller → Form Request → Policy / Permission → Service → Repository → Model → MySQL`
- Controllers remain thin and focused on HTTP transport.
- Business rules reside exclusively in `app/Services/`.
- Repositories encapsulate data querying.

---

## 10. Web
The web frontend is built with:
- Bootstrap 5 for clean, professional ergonomics without gaming flair.
- Modular Blade components (`cards`, `tables`, `forms`, `modals`, `badges`, `alerts`).
- Responsive layouts with sidebar navigation and tenant selector.

---

## 11. Mobile
The Flutter companion app (`mobile/`) features:
- Clean modular structure (`core/`, `data/`, `features/`, `shared/`).
- `ApiClient` supporting GET, POST, PUT, PATCH, DELETE with Bearer auth.
- `RoleRouter` directing authenticated users to role-specific dashboards.
- Initial implementation for Coaches and Athletes, cleanly extensible to all roles.

---

## 12. API
All endpoints follow versioning: `/api/v1/`
- Standard Success Envelope:
  ```json
  { "success": true, "message": "...", "data": {} }
  ```
- Standard Error Envelope:
  ```json
  { "success": false, "message": "...", "errors": {} }
  ```
- Interactive documentation: `swagger/openapi.yaml` & `postman/KhelSutra.postman_collection.json`.

---

## 13. Testing
Automated test suite covering:
- Feature Tests: Authentication, Tenant Isolation, RBAC, Athlete CRUD, Team CRUD, Venue Bookings.
- Unit Tests: Services, Policies, Helpers.
- Command: `php artisan test` (inside `backend/`).

---

## 14. Documentation
Extensive documentation located in `docs/`:
- `01-requirement-brief/`: Business Requirements Specification (BRS).
- `02-solution-design/`: Architecture, components, and data flows.
- `03-use-cases/`: Domain-specific functional use cases.
- `04-wireframes/`: Information architecture and wireframes.
- `05-database/`: Schema design, ER diagrams, and relationship catalogs.
- `06-api/`: API specifications and integration guides.
- `07-testing/`: Test plans, matrices, and execution reports.
- `08-development/`: Coding standards, branching rules, and checklists.
- `09-deployment/`: AWS architecture, ECS/RDS configuration, deployment checklist.
- `10-user-guide/`: User manuals for Admins, Coaches, and Athletes.

---

## 15. Git Workflow
- `main`: Production-ready code.
- `develop`: Main integration branch.
- `feature/*`: Specific module features.
- PRs require passing automated tests and minimum one peer review.

---

## 16. Local Setup

### Prerequisites
- PHP 8.2+
- MySQL 8.0+ (via WAMP or standalone)
- Flutter 3.x & Dart 3.x
- Composer

### Installation Steps
1. Clone the repository and navigate into `KhelSutra/`.
2. Configure your environment:
   ```bash
   cp .env.example backend/.env
   ```
3. Import the database schema and seeders:
   ```bash
   mysql -u root -p khelsutra < database/sports_management_database.sql
   mysql -u root -p khelsutra < database/seeds/roles.sql
   mysql -u root -p khelsutra < database/seeds/permissions.sql
   mysql -u root -p khelsutra < database/seeds/reference-data.sql
   ```
4. Start the backend:
   ```bash
   cd backend
   php -S 127.0.0.1:8000 -t public
   ```
5. Verify the health check:
   ```bash
   curl http://127.0.0.1:8000/api/v1/health
   ```
6. Run the Flutter mobile application:
   ```bash
   cd mobile
   flutter run -d chrome # or android/ios emulator
   ```

---

## 17. Environment Variables
Reference `.env.example` for all required settings:
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`
- `API_VERSION`

---

## 18. Development Workflow
1. Pull latest `develop`.
2. Create feature branch (`feature/<module-name>`).
3. Follow the layered pattern (`Request → Controller → Service → Repository → Model`).
4. Write tests in `backend/tests/`.
5. Run linting and static checks.
6. Submit Pull Request with detailed description.

---

## 19. Deployment
Target environment: **Amazon Web Services (AWS)**
- Compute: AWS ECS (Fargate) running containerized PHP-FPM and Nginx.
- Database: Amazon RDS for MySQL (Multi-AZ).
- Storage: Amazon S3 with CloudFront CDN for athlete/employee document attachments.
- Security: AWS WAF, AWS Secrets Manager, VPC private subnets.

---

## 20. Team Development
Division of responsibility across our team of 5 developers:
- **Member 1**: Core backend, authentication, RBAC, multi-tenancy, organisations.
- **Member 2**: Athletes, Coaches, Teams, Training sessions, Performance tracking.
- **Member 3**: Tournaments, Fixtures, Matches, Venues, Events.
- **Member 4**: HR, Staff, Payroll, Inventory, Equipment, Finance, Budgets.
- **Member 5**: Flutter mobile app, API client integration, Notifications, UX.

## API Reference Updates

*   `GET /api/v1/venues?sport_id={id}` - Filter venues by supported sport
*   `GET /api/v1/facilities?sport_id={id}` - Filter facilities by supported sport
*   `POST /api/v1/trips/auto-plan` - Dry-run vehicle auto-assignment algorithm
*   `POST /api/v1/trips` - Pass `auto_assign=true` to automatically allocate optimal vehicles for a group
