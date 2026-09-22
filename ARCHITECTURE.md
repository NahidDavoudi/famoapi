# Famo Unified API — Architecture

**Version:** 1.0
**Status:** Active — all backend agents MUST follow this.

---

## 1. Architecture Style

Famo backend is a **Modular Monolith** with a **JSON REST API** and **Vanilla JavaScript frontends**.

There is **ONE backend API** and **ONE database** (`nadcot_famo`, including all nobat tables).

All frontend applications communicate with the backend **only through the unified API**.

Frontend applications must **never access MySQL directly**.

- **Target backend host:** `api.famoacademy.ir`
- **Framework:** Slim 4 (PSR-15) for HTTP routing + middleware
- **Auth:** JWT (`firebase/php-jwt`)
- **Database:** PDO / MySQL (single connection via Slim DI container)
- **API base path:** `/api/v1`

---

## 2. Core Architectural Flow

```
HTTP Request
  → Slim Router
    → Middleware stack (CORS → JSON body parser → Auth → ...)
      → Controller
        → Service
          → Model
            → PDO / MySQL
          → Model
        → Service
      → Controller
    → JSON Response
```

### Layer Responsibilities

| Layer | Responsibilities | Prohibitions |
|-------|------------------|--------------|
| **Controller** | Read request params & body, invoke validation, call service, select HTTP status, return JSON | NO business logic, NO SQL, NO HTML |
| **Service** | Business rules, workflows, multi-step operations, transactions, model coordination | NO HTTP awareness, NO HTML, NO raw SQL (delegates to Model) |
| **Model** | Query/insert/update/delete DB, entity operations, relationships | NO HTTP/JSON awareness, NO business rules beyond entity scope |
| **Core** | Application-wide infrastructure only (DB, Auth, Validator, etc.) | NO domain-specific logic |

---

## 3. No Repository Layer

Do **NOT** create Repository classes by default.

Use **Controller → Service → Model** exclusively.

A Repository abstraction may only be introduced later if a concrete complexity problem justifies it. Do not create interfaces, factories, DTOs, or abstractions merely out of "best practice".

---

## 4. Directory Structure

```
api/
├── public/
│   └── index.php              # Slim 4 entry point (PSR-15)
├── app/
│   ├── Core/
│   │   ├── Database.php       # PDO factory (registered in Slim DI)
│   │   ├── Router.php         # Helper for route registration conventions
│   │   ├── Validator.php      # Input validation utility
│   │   ├── Auth.php           # JWT encode/decode (wraps firebase/php-jwt)
│   │   ├── Authorization.php  # Role-based access (admin/supporter/student)
│   │   ├── Pagination.php     # Pagination metadata builder
│   │   ├── Storage.php        # File upload handler (validate ext/size, move)
│   │   └── ExceptionHandler.php # Catches unhandled exceptions → JSON error
│   ├── Modules/
│   │   ├── Auth/
│   │   │   ├── AuthController.php
│   │   │   ├── AuthService.php
│   │   │   ├── AuthMiddleware.php
│   │   │   └── User.php                  # Model for `users` table
│   │   ├── Public/
│   │   │   ├── PublicController.php
│   │   │   ├── PublicService.php
│   │   │   ├── Course.php                # Model for `courses`
│   │   │   ├── Instructor.php            # Model for `instructors`
│   │   │   └── Supporter.php             # Model for `supporters`
│   │   ├── Students/
│   │   │   ├── StudentController.php
│   │   │   ├── StudentService.php
│   │   │   └── Student.php               # Model for `students`
│   │   ├── Courses/
│   │   │   ├── CourseController.php
│   │   │   ├── CourseService.php
│   │   │   └── Course.php
│   │   ├── Instructors/
│   │   │   ├── InstructorController.php
│   │   │   ├── InstructorService.php
│   │   │   └── Instructor.php
│   │   ├── Supporters/
│   │   │   ├── SupporterController.php
│   │   │   ├── SupporterService.php
│   │   │   └── Supporter.php
│   │   ├── Blog/
│   │   │   ├── BlogController.php
│   │   │   ├── BlogService.php
│   │   │   └── BlogPost.php              # Model for `blog_posts`
│   │   ├── Exams/
│   │   │   ├── ExamController.php
│   │   │   ├── ExamService.php
│   │   │   └── ExamResult.php            # Model for `exam_results`
│   │   ├── WeeklyPlans/
│   │   │   ├── PlanController.php
│   │   │   ├── PlanService.php
│   │   │   └── WeeklyPlan.php            # Model for `weekly_plans`
│   │   ├── Appointments/
│   │   │   ├── AppointmentController.php
│   │   │   ├── AppointmentService.php
│   │   │   └── Appointment.php           # Model for `appointments`
│   │   ├── Remedial/
│   │   │   ├── RemedialController.php
│   │   │   ├── RemedialService.php
│   │   │   ├── RemedialSession.php
│   │   │   ├── RemedialClass.php
│   │   │   └── RemedialAttendance.php
│   │   ├── Reports/
│   │   │   ├── ReportController.php
│   │   │   ├── ReportService.php
│   │   │   └── Report.php                # Model for `reports_status`
│   │   ├── Files/
│   │   │   ├── FileController.php
│   │   │   ├── FileService.php
│   │   │   └── File.php                  # Model for `files`
│   │   └── Topics/
│   │       ├── TopicController.php
│   │       ├── TopicService.php
│   │       └── Topic.php                 # Model for `topic_tree`
│   ├── Helpers/
│   │   └── JalaliHelper.php              # Single PHP Jalali date converter
├── routes/
│   └── api.php                           # All Slim route definitions
├── config/
│   ├── database.php                      # Returns DB connection params from .env
│   └── app.php                           # JWT secret, CORS origins, upload limits
├── database/
│   ├── migrations/                       # Raw SQL migration files (numbered)
│   └── seeders/                          # Default data inserts
├── storage/
│   └── logs/                             # Application log files
├── bootstrap/
│   └── app.php                           # Slim DI container + middleware setup
├── composer.json
├── .env
├── .env.example
└── .gitignore
```

---

## 5. Module Convention

Every module follows this contract:

```
ModuleName/
├── ModuleNameController.php     # HTTP layer
├── ModuleNameService.php        # Business logic
└── ModuleName.php               # Model (single file per primary table)
```

**Controller methods** are Slim invokable classes or callable methods:

```php
class StudentController {
    public function list(Request $request, Response $response): Response { ... }
    public function create(Request $request, Response $response): Response { ... }
    public function get(Request $response, array $args): Response { ... }
    public function update(Request $request, Response $response, array $args): Response { ... }
    public function delete(Request $request, Response $response, array $args): Response { ... }
}
```

**Service methods** return data or throw typed exceptions:

```php
class StudentService {
    public function list(array $filters, int $page, int $perPage): array { ... }
    public function create(array $data): array { ... }
    public function get(int $id): array { ... }
    public function update(int $id, array $data): array { ... }
    public function delete(int $id): void { ... }
}
```

**Model methods** are static or instance methods querying the DB:

```php
class Student {
    public static function findAll(array $filters, int $page, int $perPage): array { ... }
    public static function findById(int $id): ?array { ... }
    public static function create(array $data): int { ... }
    public static function update(int $id, array $data): int { ... }
    public static function delete(int $id): bool { ... }
}
```

---

## 6. JSON Response Envelope

Every API response MUST use this envelope:

### Success
```json
{
    "success": true,
    "data": { ... },
    "pagination": {
        "page": 1,
        "per_page": 20,
        "total": 150,
        "total_pages": 8
    },
    "error": null
}
```

### Error
```json
{
    "success": false,
    "data": null,
    "pagination": null,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "نام دانش‌آموز الزامی است"
    }
}
```

Use a `ResponseHelper` class or Slim middleware to enforce this envelope.

---

## 7. API Endpoints

```
POST   /api/v1/auth/login                   # Login → returns JWT
POST   /api/v1/auth/register                # Student registration
GET    /api/v1/auth/me                       # Current user from JWT
POST   /api/v1/auth/logout                  # Token invalidation (optional)

GET    /api/v1/students                     # List (paginated, filterable)
POST   /api/v1/students                     # Create
GET    /api/v1/students/{id}                # Get single
PUT    /api/v1/students/{id}                # Update
DELETE /api/v1/students/{id}                # Delete

GET    /api/v1/courses                      # List
POST   /api/v1/courses                      # Create
PUT    /api/v1/courses/{id}                 # Update
DELETE /api/v1/courses/{id}                 # Delete

GET    /api/v1/instructors                  # List
POST   /api/v1/instructors                  # Create
PUT    /api/v1/instructors/{id}             # Update
DELETE /api/v1/instructors/{id}             # Delete

GET    /api/v1/supporters                   # List
POST   /api/v1/supporters                   # Create
PUT    /api/v1/supporters/{id}              # Update
DELETE /api/v1/supporters/{id}              # Delete

GET    /api/v1/blog/posts                   # List (published only for non-admin)
GET    /api/v1/blog/posts/{id}              # Single
POST   /api/v1/blog/posts                   # Create (admin)
PUT    /api/v1/blog/posts/{id}              # Update (admin)
DELETE /api/v1/blog/posts/{id}              # Delete (admin)
GET    /api/v1/blog/categories              # Categories

GET    /api/v1/exams                        # List (filterable)
GET    /api/v1/exams/dates                  # Distinct dates with stats
GET    /api/v1/exams/students               # Students for a date
GET    /api/v1/exams/details                # Per-subject breakdown
POST   /api/v1/exams                        # Bulk save

GET    /api/v1/plans                        # Weekly plan for student
POST   /api/v1/plans/items                  # Save single item
POST   /api/v1/plans/bulk                   # Bulk replace
DELETE /api/v1/plans                         # Clear plan
GET    /api/v1/plans/templates              # List templates
POST   /api/v1/plans/templates              # Save as template

GET    /api/v1/appointments                 # List
POST   /api/v1/appointments                 # Create/update
PUT    /api/v1/appointments/{id}/status     # Update status
DELETE /api/v1/appointments/{id}            # Delete

GET    /api/v1/remedial/sessions            # List sessions
POST   /api/v1/remedial/sessions            # Create session (copies all students)
GET    /api/v1/remedial/sessions/{id}       # Session data (tabs by field)
POST   /api/v1/remedial/attendance          # Toggle attendance
POST   /api/v1/remedial/classes             # Create class
DELETE /api/v1/remedial/classes/{id}        # Delete class
POST   /api/v1/remedial/students            # Add/remove student from session
PUT    /api/v1/remedial/students/time       # Update student time

GET    /api/v1/reports                      # Reports with date filter

GET    /api/v1/files                        # List files
POST   /api/v1/files/upload                 # Upload file
DELETE /api/v1/files/{id}                   # Delete file

GET    /api/v1/topics                       # Topic tree
GET    /api/v1/topics/search                # Search topics
GET    /api/v1/topics/{id}/children         # Children
GET    /api/v1/topics/{id}/path             # Path to root
GET    /api/v1/subjects/{grade}             # Subjects by grade

# Public (no auth required)
GET    /api/v1/public/courses               # Courses with features
GET    /api/v1/public/instructors           # Instructors with social links
GET    /api/v1/public/supporters            # Supporters list
GET    /api/v1/public/blog/posts            # Published posts (paginated)
GET    /api/v1/public/blog/posts/{slug}     # Single post by slug
GET    /api/v1/public/blog/categories       # Active categories
```

---

## 8. JWT Auth Strategy

- **Library:** `firebase/php-jwt`
- **Algorithm:** HS256
- **TTL:** 24 hours (configurable in `config/app.php`)
- **Payload:**
  ```json
  {
      "sub": 42,
      "role": "admin",
      "iat": 1712345678,
      "exp": 1712432078
  }
  ```
- **Transport:** `Authorization: Bearer <token>` header
- **Middleware:** `AuthMiddleware` validates token on protected routes, attaches decoded payload to `$request->getAttribute('user')`
- **Role middleware:** `requireAdmin()`, `requireSupporter()`, `requireStudent()` check role after auth

---

## 9. Database Conventions

- **Single database:** `nadcot_famo` (host, name, user, pass from `.env`)
- **nobat tables** (`appointments`, `parent_contacts`, `remedial_*`, `session_*`) exist in the same DB
- **Migrations:** Numbered SQL files in `database/migrations/`, executed in order
- **No auto-migration in code** (remove legacy `ensureWeeklyPlanTablesExist()` pattern)
- **Charset:** `utf8mb4_persian_ci` consistently across all tables
- **Foreign keys:** Add explicit FK constraints where relationships exist

---

## 10. Schema Fixes (MUST be applied before Phase 1)

| Fix | SQL |
|-----|-----|
| `students.national_id` to BIGINT | `ALTER TABLE students MODIFY national_id BIGINT;` |
| Ensure `blog_posts.category_id` exists | `ALTER TABLE blog_posts ADD category_id INT NULL;` |
| Ensure `weekly_plans` has correct schema | Use existing DB schema, drop admin's auto-create |
| Add foreign keys to `blog_posts.category_id` | `REFERENCES blog_categories(id)` |
| Add FK to `course_features.course_id` | `REFERENCES courses(id) ON DELETE CASCADE` |
| Add FK to `instructor_social_links.instructor_id` | `REFERENCES instructors(id) ON DELETE CASCADE` |
| Remove hardcoded credentials from dashboard/plan | Will use .env exclusively |

---

## 11. Coding Standards

- PHP 8.0+ typed properties and return types
- PSR-4 autoloading (`App\` namespace maps to `app/`)
- Persian labels/messages, English code identifiers
- No inline HTML in PHP backend files
- No raw `echo` — always return `Response` objects
- All SQL uses prepared statements via PDO
- No global state (no `global $pdo`, no `$_SESSION` for auth)
- Error messages in Persian for user-facing errors
- Log unexpected errors to `storage/logs/app.log`

---

## 12. Logging

- Use `error_log()` or a simple Logger in `Core/`
- Log level: errors and warnings only (no routine request logging)
- Log file: `storage/logs/app.log`
- Do NOT log to `vendor/` directory or project root

---

## 13. File Uploads

- Handled by `Core/Storage.php`
- Allowed extensions: `jpg`, `jpeg`, `png`, `pdf`, `doc`, `docx`
- Max file size: 10MB
- Upload directory: configurable in `config/app.php` (default `uploads/`)
- Files stored under: `uploads/{module}/{student_id_or_post_id}/filename.ext`
- Actual file path and metadata stored in `files` table

---

## 14. Migration Sequence

1. Create new `api/` backend at new subdomain (`api.famoacademy.ir`)
2. Deploy alongside existing applications (pointing to same DB)
3. Each frontend app is migrated one at a time to call the new API
4. Old API files removed only after all frontends are migrated

**Never take downtime. Never break existing functionality.**