# Famo Unified API — Implementation Plan

**Version:** 1.0
**Target:** `api.famoacademy.ir` / `api/v1`
**Pattern:** Modular Monolith + Slim 4 + JWT Auth

---

## Phase 0 — Foundation (Day 1–2)

### Step 0.1 — Directory Structure
Create the full directory tree under `api/`:
- `public/index.php`
- `app/Core/` (7 files)
- `app/Modules/` (13 module directories)
- `routes/api.php`
- `config/` (2 files)
- `database/migrations/`, `database/seeders/`
- `storage/logs/`
- `bootstrap/app.php`

### Step 0.2 — Composer Setup
```bash
cd api
composer init --name="famo/api" --type="project"
composer require slim/slim:^4.0
composer require slim/psr7:^1.0
composer require firebase/php-jwt:^6.0
composer require vlucas/phpdotenv:^5.7
```
Configure PSR-4 autoload: `"App\\": "app/"`

### Step 0.3 — .env and config
- Create `.env.example` with `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `JWT_SECRET`, `JWT_TTL`, `CORS_ORIGINS`, `UPLOAD_MAX_SIZE`
- Create `.env` from example
- Create `config/database.php` (reads .env, returns PDO params)
- Create `config/app.php` (JWT config, CORS, upload limits)

### Step 0.4 — Core Classes
Implement `app/Core/`:
1. **Database.php** — PDO factory method, charset utf8mb4_persian_ci, error mode exception
2. **Auth.php** — `encode(array $user): string`, `decode(string $token): object`, wraps firebase/php-jwt
3. **Validator.php** — `required()`, `numeric()`, `phone()`, `maxLength()`, `inArray()`, returns errors array
4. **Authorization.php** — `requireRole(string $role, Request $request): void`
5. **Pagination.php** — `build(int $page, int $perPage, int $total): array` with metadata
6. **Storage.php** — `upload(array $file, string $module): string` returns path, validates ext/size
7. **ExceptionHandler.php** — Catches `Throwable`, logs to `storage/logs/app.log`, returns JSON 500

### Step 0.5 — Bootstrap
Implement `bootstrap/app.php`:
- Create Slim App with DI container
- Register Database as singleton
- Add CORS middleware (allow configured origins)
- Add JSON body parsing middleware
- Add route from `routes/api.php`
- Add ExceptionHandler as last middleware
- Return app

### Step 0.6 — Entry Point
Implement `public/index.php`:
```php
<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->run();
```
Add `.htaccess` rewrite rules for Apache.

### Step 0.7 — Schema Fix Migrations
Create `database/migrations/001_fix_schema.sql`:
```sql
ALTER TABLE students MODIFY national_id BIGINT;
ALTER TABLE blog_posts ADD COLUMN IF NOT EXISTS category_id INT NULL;
ALTER TABLE blog_posts ADD CONSTRAINT fk_blog_category FOREIGN KEY (category_id) REFERENCES blog_categories(id);
-- Add other FK constraints as needed
```
Create `database/migrations/002_fix_weekly_plans.sql`:
- Resolve schema conflict: use existing `weekly_plans(student_id, week_date, weekly_notes, times_json)` schema
- Drop any conflicting table created by admin's auto-migration

### Step 0.8 — `.gitignore`
Ignore: `.env`, `vendor/`, `storage/logs/*.log`, `uploads/`

### Deliverables for Phase 0
- Full directory tree exists
- Composer installs without error
- `public/index.php` returns `{"success":true,"data":{"status":"healthy"}}` on GET `/api/v1/health`
- Schema fix SQL files ready

---

## Phase 1 — Auth Module (Day 3)

### Step 1.1 — Auth Middleware
`app/Modules/Auth/AuthMiddleware.php`:
- Reads `Authorization: Bearer <token>` header
- Validates via `Core/Auth::decode()`
- Attaches decoded payload to `$request->withAttribute('user', $payload)`
- Returns 401 on invalid/missing token

### Step 1.2 — User Model
`app/Modules/Auth/User.php`:
- `findByUsername(string $username): ?array`
- `findById(int $id): ?array`
- `create(array $data): int` (password_hash on insert)
- `updatePassword(int $id, string $password): void`

### Step 1.3 — Auth Service
`app/Modules/Auth/AuthService.php`:
- `login(string $username, string $password): array` — verify credentials, return JWT + user data
- `register(array $data): array` — validate unique username, create user+student, return JWT
- `me(int $userId): array` — return user profile

### Step 1.4 — Auth Controller
`app/Modules/Auth/AuthController.php`:
- `login(Request, Response)` — read JSON body, call service, return JWT
- `register(Request, Response)` — read body, call service, return JWT
- `me(Request, Response)` — get user from request attribute, return profile
- `logout(Request, Response)` — return success (stateless JWT, client deletes token)

### Step 1.5 — Routes
Add to `routes/api.php`:
```php
$app->post('/api/v1/auth/login', [AuthController::class, 'login']);
$app->post('/api/v1/auth/register', [AuthController::class, 'register']);
$app->get('/api/v1/auth/me', [AuthController::class, 'me'])->add($authMiddleware);
$app->post('/api/v1/auth/logout', [AuthController::class, 'logout'])->add($authMiddleware);
```

### Deliverables for Phase 1
- Can login with existing admin credentials → returns JWT
- Can register new student → returns JWT
- `/api/v1/auth/me` with valid token returns user data
- `/api/v1/auth/me` with invalid/missing token returns 401

---

## Phase 2 — Public + Blog Modules (Day 4)

### Step 2.1 — Public Module
Models: `Course.php`, `Instructor.php`, `Supporter.php`
- `findAllPublished(): array` — courses with features, instructors with social links
Service: `PublicService.php` — coordinates joins (prevent N+1)
Controller: `PublicController.php` — 3 endpoints (courses, instructors, supporters)

### Step 2.2 — Blog Module
Model: `BlogPost.php`
- `findAllPublished(int $page, int $perPage): array` — with pagination
- `findBySlug(string $slug): ?array`
- `findByCategory(int $categoryId, int $page, int $perPage): array`
- `findAll(int $page, int $perPage): array` — admin view (includes drafts)
- `findById(int $id): ?array`
- `create(array $data): int` — with cover image upload, slug uniqueness
- `update(int $id, array $data): int` — with image cleanup
- `delete(int $id): bool`

### Step 2.3 — Blog Routes
```
GET    /api/v1/public/blog/posts            (no auth)
GET    /api/v1/public/blog/posts/{slug}     (no auth)
GET    /api/v1/public/blog/categories       (no auth)
GET    /api/v1/blog/posts                   (auth: admin/supporter)
GET    /api/v1/blog/posts/{id}              (auth: admin/supporter)
POST   /api/v1/blog/posts                   (auth: admin)
PUT    /api/v1/blog/posts/{id}              (auth: admin)
DELETE /api/v1/blog/posts/{id}              (auth: admin)
```

### Deliverables for Phase 2
- Blog reads work (matches famo's current output)
- Blog CRUD works for admin (matches admin's current output)
- Public courses/instructors/supporters match famo's current output

---

## Phase 3 — Student + Course + Instructor + Supporter CRUD (Day 5–6)

### Step 3.1 — Students Module
Port from `admin/api/students.php` (284 lines):
- `list`: paginated with field/grade/search/status filters
- `create`: transaction with user account creation, default password validation
- `update`: field-level update
- `delete`: cascade to user account
- `createAccount`: create user for existing student
- `resetPassword`: reset to '1234' with forced change flag
- `toggleStatus`: activate/deactivate student
- `getList`: lightweight dropdown (id + name only)

### Step 3.2 — Courses Module
Port from `admin/api/courses.php` (161 lines):
- Full CRUD with image upload (gradient colors for card display)
- Order by `display_order`
- Student-facing: `getForPublic()` (used by Public/PublicController too)

### Step 3.3 — Instructors Module
Port from `admin/api/instructors.php` (156 lines):
- Full CRUD with image upload
- Auto-generate `initial_letter` from Persian name

### Step 3.4 — Supporters Module
Port from `admin/api/supporters.php` (144 lines):
- Full CRUD with user account management
- Stats: replied count, pending count, avg response time

### Deliverables for Phase 3
- All CRUD operations match existing admin panel output
- Image uploads work for courses and instructors
- Pagination, filtering, search all functional
- Cascade delete works (student → user, supporter → user)

---

## Phase 4 — Exams + Weekly Plans + Reports + Files + Topics (Day 7–8)

### Step 4.1 — Exams Module
Port from `admin/api/exams.php` (187 lines):
- `getDates()`: grouped by exam_date with student/subject/avg stats
- `getStudents()`: students for a date with subject count + avg
- `getDetails()`: per-subject breakdown for student+date
- `getAll()`: filterable (student_id, date_from, date_to)
- `save()`: transaction-based batch insert with validation (correct+wrong+skipped ≤ total)

### Step 4.2 — Weekly Plans Module
Port from `admin/api/weekly-plans.php` (331 lines), **but use existing DB schema**:
- Existing DB schema: `weekly_plans(id, student_id, week_date, weekly_notes, times_json)`
- DO NOT use admin's auto-create schema (normalized day_of_week/time_slot)
- `get()`: fetch plan + parse `times_json` for frontend
- `saveItem()`: upsert into `times_json`
- `saveBulk()`: replace entire `times_json`
- `clear()`: delete or empty
- Templates: CRUD for plan templates (reuse existing template structure)

### Step 4.3 — Reports Module
Port from:
- `admin/api/reports.php` (28 lines): date-filtered `reports_status`
- `plan/api/reports.php` (207 lines): student summaries, week detail, subject stats, all-students

### Step 4.4 — Files Module
Port from `admin/api/files.php` (86 lines):
- List files with student names
- Upload with ext/size validation
- Delete with file system cleanup

### Step 4.5 — Topics Module
Port from `admin/api/topics.php` (216 lines):
- `getChildren()`: cascading tree nodes
- `search()`: autocomplete
- `getPath()`: breadcrumb to root
- `getSubjectsForGrade()`: maps grade+field → subject/chapter tree

### Deliverables for Phase 4
- Exam CRUD matches admin panel output
- Weekly plan CRUD matches (uses existing DB schema)
- Reports match both admin and plan outputs
- File upload/download works
- Topic tree traversal works

---

## Phase 5 — Appointments + Remedial (Day 9–10)

### Step 5.1 — Appointments Module
Port from `nobat/app/Controllers/ApiController.php` + `AppointmentController.php`:
- CRUD for `appointments` table
- Weekly scheduling logic (create or update if exists)
- Status management (pending/accepted/rejected/completed/cancelled)
- SMS integration for parent notification

### Step 5.2 — Remedial Module
Port from `nobat/app/Controllers/RemedialController.php` (188 lines):
- Session management (create with copy-all-students)
- Class CRUD per session
- Attendance toggling
- Student time tracking (arrival, exam start, exam end)

### Step 5.3 — Remedial Models
Port all 5 models from nobat:
- `RemedialSession.php`, `RemedialClass.php`, `RemedialAttendance.php`, `SessionStudent.php`, `SessionStudentTime.php`

### Step 5.4 — SMS Service
Port `nobat/app/Services/SmsService.php`:
- Move Kavenegar credentials to `.env`
- Same SOAP/HTTP POST pattern

### Deliverables for Phase 5
- Appointment CRUD matches nobat output
- Remedial session management matches nobat output
- SMS integration works (credentials from .env)

---

## Phase 6 — Frontend Migration (Day 11–15, parallel per app)

### Step 6.1 — Shared API Client (JS)
Create `shared/js/api-client.js`:
```js
const API = {
    base: 'https://api.famoacademy.ir/api/v1',
    token: localStorage.getItem('jwt_token'),
    
    async request(method, path, data = null) {
        const headers = { 'Content-Type': 'application/json' };
        if (this.token) headers['Authorization'] = `Bearer ${this.token}`;
        const res = await fetch(this.base + path, {
            method, headers,
            body: data ? JSON.stringify(data) : undefined
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.error?.message || 'خطا');
        return json;
    },
    
    async login(username, password) {
        const res = await this.request('POST', '/auth/login', { username, password });
        this.token = res.data.token;
        localStorage.setItem('jwt_token', res.data.token);
        return res;
    }
};
```

### Step 6.2 — Admin SPA Migration
1. Add `shared/js/api-client.js` to admin's JS imports
2. Replace `api-client.js` calls with `API.request(...)`
3. Add login flow that calls `API.login()`, stores JWT
4. Migrate one module at a time: dashboard → students → courses → instructors → supporters → blog → exams → weekly_plans → files → reports
5. Update `checkAuth()` to validate JWT instead of session

### Step 6.3 — Famo Public Migration
1. Add shared API client
2. Replace direct `fetch()` calls in `main.js`, `courses.js`, `team.js` with `API.request()`
3. Replace `famo/api/auth.php` calls with `API.request()` + JWT
4. Replace `famo/api/blog.php` calls with `API.request()`

### Step 6.4 — Dashboard Migration
1. Replace PHP-backed `api/auth.php` calls with new API
2. Replace `dash.php` queries with new API
3. Remove `/dashboard/api/` files after migration

### Step 6.5 — Nobat Migration
1. Replace front controller + local controllers with new API calls
2. Replace PHP session auth with JWT
3. Keep PHP view templates (they call API now instead of local models)

### Step 6.6 — Plan Migration
1. Replace `plan/api/plans.php` etc. with new API calls
2. Remove hardcoded credentials
3. Add JWT auth to plan

### Deliverables for Phase 6
- All 5 frontends functional with new API
- No frontend calls old API files
- All auth uses JWT

---

## Phase 7 — Cleanup (Day 16)

### Step 7.1 — Remove Old Backend Files
- Delete `admin/api/*.php` (all 17 files)
- Delete `famo/api/*.php` (all 6 files)
- Delete `dashboard/api/*.php` (all 4 files)
- Delete `plan/api/*.php` (all 5 files)
- Delete `dashboard/model/*.php` (all 8 files)
- Delete `dashboard/controller/Core.php`
- Delete `nobat/app/Controllers/*` (7 files)
- Delete `nobat/app/Models/*` (9 files)
- Delete `nobat/app/Services/*` (1 file)
- Delete `nobat/app/Helpers/*` (1 file)
- Delete `plan/config.php`

### Step 7.2 — Consolidate Assets
- Move fonts to `shared/fonts/` (single Vazirmatn set)
- Move SVG icons to `shared/icons/` (single sprite)
- Remove duplicate `api-client.js`, `jalali.js`, `chart-theme.js` from sub-projects

### Step 7.3 — Remove Credentials from Source
- Verify no hardcoded DB credentials remain
- Verify no Telegram tokens in source
- Verify no hardcoded SMS credentials

### Step 7.4 — Remove Test Scripts
- `admin/api/test_api.php`
- Any other test/stub files

### Step 7.5 — Final Verification
- All 5 frontends work end-to-end
- All auth flows work (admin login, student login, supporter login)
- JWT token refresh works
- File uploads work
- All CRUD operations work

### Deliverables for Phase 7
- Clean codebase with single API backend
- No duplicated backend logic
- No exposed credentials
- No dead code

---

## Dependency Graph

```
Phase 0 (Foundation)
  └── Phase 1 (Auth) ─────────────────────┐
        └── Phase 2 (Public + Blog) ───────┤
              └── Phase 3 (Student/Course/Instructor/Supporter) ─┤
                    └── Phase 4 (Exams/Plans/Reports/Files/Topics) ─┤
                          └── Phase 5 (Appointments + Remedial) ────┤
                                └── Phase 6 (Frontend Migration)    │
                                      └── Phase 7 (Cleanup) ◄───────┘
```

---

## Risk Register

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Schema conflict destroys data | Low | Critical | Fix schema in Phase 0, backup before migration |
| JWT migration breaks login | Medium | High | Keep old auth running in parallel during Phase 6 |
| Frontend migration misses edge case | Medium | Medium | Manual QA per module after migration |
| SMS service breaks | Low | Medium | Nobat SMS is cron-triggered, easy to test |
| Weekly plan data loss | Medium | High | Use existing DB schema, NOT admin's auto-create |
| national_id already corrupted | High | High | Export affected records in Phase 0, fix during migration |

---

## Verification Checklist (per phase)

- [ ] All endpoints return `{success, data, error}` envelope
- [ ] 401 returned for protected endpoints without token
- [ ] 403 returned for endpoints with wrong role
- [ ] 400 returned for invalid input
- [ ] 404 returned for non-existent resources
- [ ] Pagination metadata present on list endpoints
- [ ] CORS allows configured origins
- [ ] File upload validates extension and size
- [ ] Transactions roll back on error
- [ ] SQL injection not possible (all prepared statements)