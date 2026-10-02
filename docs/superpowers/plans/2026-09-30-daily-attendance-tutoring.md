# Daily Attendance + Tutoring (رفع اشکال) Implementation Plan

> **For agentic workers:** Execute this plan task-by-task in the current isolated worktree. Steps use checkbox syntax for tracking.

**Goal:** Add daily student attendance with four timestamps (arrival, exam start, exam end, departure), a teacher (instructor) tutoring panel for رفع اشکال sessions, and a live teacher-status board — as REST endpoints under `/api/v1`.

**Architecture:** Two new Slim modules (`Attendance`, `Tutoring`) following the existing Controller → Service → static data-class pattern. One additive SQL migration creates the tables and extends `users.role`. Teacher identity reuses the existing `instructors` table via `users.linked_id`, exactly like supporter/student. Tehran calendar days and UTC timestamps are computed in PHP using the existing `App\Modules\Bot\IranDay` helper (never `NOW()` in SQL for Tehran days).

**Tech Stack:** PHP 8.0+, Slim 4, PDO/MariaDB 10.4, PHPUnit 9.5, OpenAPI 3.1.

## Global Constraints

- All API responses use the envelope `{success, data, pagination, error}` with `Content-Type: application/json; charset=utf-8`.
- Read endpoints for the secretary use role set **admin + supporter**; tutoring-panel endpoints use role **teacher** only; the live board uses **admin + supporter**.
- All `*_at` DATETIME values are **UTC**, generated in PHP via `IranDay::nowUtc()`. All `*_date` values are the **Tehran** calendar day via `IranDay::today()` / caller-supplied `date`. Never use `NOW()`/`CURDATE()` in SQL for Tehran days.
- Reuse `App\Modules\Bot\IranDay` for all Iran-calendar and UTC-now logic. Do not duplicate calendar math.
- Every table/column name, enum value, and constraint must match the migration in Task 1 exactly — the migration is the binding schema.
- Validate the `field` column parameter against an explicit allow-list (never interpolate raw client input into SQL).
- Writes that accept `client_uuid` must be idempotent: a duplicate `client_uuid` returns the already-stored row with HTTP 200, not an error.
- Never alter unrelated modules or existing behavior beyond the additive `users.role` enum change.

---

### Task 1: Additive migration + teacher role in auth/authorization

**Files:**
- Create: `database/migrations/012_daily_attendance_tutoring.sql`
- Modify: `app/Core/Authorization.php`
- Modify: `app/Modules/Auth/AuthService.php`
- Modify: `app/Core/Auth.php`
- Test: `tests/Modules/Auth/TeacherAuthTest.php`

**Interfaces:**
- Migration creates `tutoring_classrooms`, `daily_attendance`, `tutoring_sessions`, `tutoring_teacher_status_log` and runs `ALTER TABLE users MODIFY role ENUM('admin','supporter','student','teacher') NOT NULL`.
- `Authorization` supports a third allowed role `teacher` (role must equal `teacher`).
- `Auth::encode(array $user)` payload gains an optional `instructor_id` claim (null unless provided).
- `AuthService::login` treats role `teacher` like admin/supporter (sends 2FA code, returns `requires_2fa`), and `verify2fa` issues a token containing `instructor_id` equal to `users.linked_id` for teacher accounts.

- [ ] **Step 1: Write `database/migrations/012_daily_attendance_tutoring.sql`** with exactly this DDL (use `CREATE TABLE IF NOT EXISTS` for each table so the file is re-runnable):

```sql
ALTER TABLE users
  MODIFY role ENUM('admin','supporter','student','teacher') NOT NULL;

CREATE TABLE IF NOT EXISTS tutoring_classrooms (
  id            INT NOT NULL AUTO_INCREMENT,
  name          VARCHAR(100) NOT NULL,
  instructor_id INT NOT NULL,
  sort_order    INT NOT NULL DEFAULT 0,
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_classroom_instructor (instructor_id),
  CONSTRAINT fk_classroom_instructor FOREIGN KEY (instructor_id) REFERENCES instructors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS daily_attendance (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  attendance_date DATE NOT NULL,
  student_id      INT NULL,
  guest_name      VARCHAR(255) NULL,
  field           VARCHAR(50) NULL,
  status          ENUM('present','absent') NOT NULL DEFAULT 'present',
  arrived_at      DATETIME NULL,
  exam_started_at DATETIME NULL,
  exam_ended_at   DATETIME NULL,
  departed_at     DATETIME NULL,
  removed_at      DATETIME NULL,
  removed_by      INT NULL,
  created_by      INT NULL,
  client_uuid     CHAR(36) NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_att_student_day (attendance_date, student_id),
  UNIQUE KEY uq_att_client_uuid (client_uuid),
  KEY idx_att_day_field (attendance_date, field),
  CONSTRAINT fk_att_student    FOREIGN KEY (student_id)  REFERENCES students (id),
  CONSTRAINT fk_att_removed_by FOREIGN KEY (removed_by)  REFERENCES users (id),
  CONSTRAINT fk_att_created_by FOREIGN KEY (created_by)  REFERENCES users (id),
  CONSTRAINT chk_att_identity  CHECK (student_id IS NOT NULL OR guest_name IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutoring_sessions (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  session_date      DATE NOT NULL,
  classroom_id      INT NOT NULL,
  student_id        INT NOT NULL,
  attendance_id     BIGINT UNSIGNED NULL,
  entered_at        DATETIME NOT NULL,
  ended_at          DATETIME NULL,
  client_entered_at DATETIME NULL,
  entry_source      ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  needs_review      TINYINT(1) NOT NULL DEFAULT 0,
  created_by        INT NULL,
  client_uuid       CHAR(36) NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  open_student_key  INT AS (IF(ended_at IS NULL, student_id, NULL)) VIRTUAL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sess_client_uuid (client_uuid),
  UNIQUE KEY uq_sess_one_open_per_student (open_student_key),
  KEY idx_sess_day_class (session_date, classroom_id),
  KEY idx_sess_class_open (classroom_id, ended_at),
  CONSTRAINT fk_sess_classroom  FOREIGN KEY (classroom_id)  REFERENCES tutoring_classrooms (id),
  CONSTRAINT fk_sess_student    FOREIGN KEY (student_id)    REFERENCES students (id),
  CONSTRAINT fk_sess_attendance FOREIGN KEY (attendance_id) REFERENCES daily_attendance (id),
  CONSTRAINT fk_sess_created_by FOREIGN KEY (created_by)    REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tutoring_teacher_status_log (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  instructor_id INT NOT NULL,
  status        ENUM('absent','ready','break') NOT NULL,
  status_date   DATE NOT NULL,
  started_at    DATETIME NOT NULL,
  ended_at      DATETIME NULL,
  client_uuid   CHAR(36) NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_status_client_uuid (client_uuid),
  KEY idx_status_teacher_day (instructor_id, status_date, ended_at),
  CONSTRAINT fk_status_instructor FOREIGN KEY (instructor_id) REFERENCES instructors (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Then run it: `php migrate.php` from the repo root (the migrator records applied files; already-applied migrations 002-011 are skipped). Confirm the four tables exist and `users.role` includes `teacher`.
- [ ] **Step 2: Extend `Authorization`** with a role-set table: `admin` → `['admin']`, `supporter` → `['admin','supporter']`, `teacher` → `['teacher']`; preserve existing messages and 403 envelope.
- [ ] **Step 3: Extend `Auth::encode`** to include `instructor_id` in the JWT payload (null when absent), leaving existing claims unchanged.
- [ ] **Step 4: Extend `AuthService`** so `teacher` is in the 2FA branch of `login()`, and `verify2fa()`/`login-recognition` resolves the teacher's `instructor_id` from `users.linked_id`. Add a private helper analogous to `studentIdFor`.
- [ ] **Step 5: Write and run `TeacherAuthTest`** (in `tests/Modules/Auth/`) proving: a `teacher`-role user gets `requires_2fa`, and a verified teacher token carries `instructor_id`. Seed a user with `role='teacher'`, `linked_id` pointing at an `instructors` row, and clean up in `setUp`.
- [ ] **Step 6: Run `vendor/bin/phpunit --filter TeacherAuthTest`** and the existing `--filter Auth` suite; both green.

### Task 2: Attendance module + endpoints

**Files:**
- Create: `app/Modules/Attendance/Attendance.php`
- Create: `app/Modules/Attendance/AttendanceService.php`
- Create: `app/Modules/Attendance/AttendanceController.php`
- Modify: `routes/api.php`
- Test: `tests/Modules/Attendance/AttendanceTest.php`

**Interfaces:**
- `Attendance::listDay(string $date, ?string $field): array` — active students (+ any stored row that day) merged with guest rows (`student_id IS NULL`, `removed_at IS NULL`), optional `field` filter.
- `Attendance::upsertEntry(string $date, int $studentId, ?string $status, bool $removed, ?string $clientUuid): array`
- `Attendance::setTime(int $id, string $column, ?string $value): array`
- `Attendance::addGuest(string $date, string $guestName, string $field): array`
- Controller methods: `list`, `upsertEntry`, `setTime`, `addGuest`, `printList`.

- [ ] **Step 1: Write failing tests** in `tests/Modules/Attendance/AttendanceTest.php` covering: list day returns active students; upsert marks present; upsert with `removed=true` hides the row from list; `PATCH .../times` sets `arrived_at` with `value:"now"`; invalid time field returns 422; guest creation appears in list; duplicate `client_uuid` upsert returns 200 idempotently; print response omits the four `*_at` keys; supporter can read, anonymous cannot.
- [ ] **Step 2: Implement `Attendance` data class** with parameterized PDO (reuse `App\Core\Database`), time-column allow-list `arrived_at|exam_started_at|exam_ended_at|departed_at`, and duplicate-`client_uuid` handling (catch SQLSTATE 23000 and re-select).
- [ ] **Step 3: Implement `AttendanceService`** delegating to the data class, using `IranDay::today()` for the default date and `IranDay::nowUtc()` for `"now"`; throw `ApiException` on unknown rows/fields.
- [ ] **Step 4: Implement `AttendanceController`** with the JSON envelope and `Validator`; register routes in `routes/api.php` under role set `$requireSupporter` + `$authMiddleware`:
  - `GET  /api/v1/attendance`
  - `PUT  /api/v1/attendance/entries`
  - `PATCH /api/v1/attendance/{id:[0-9]+}/times`
  - `POST /api/v1/attendance/guests`
  - `GET  /api/v1/attendance/print`
- [ ] **Step 5: Run `vendor/bin/phpunit --filter AttendanceTest`** and confirm green.

### Task 3: Tutoring module + endpoints

**Files:**
- Create: `app/Modules/Tutoring/Tutoring.php`
- Create: `app/Modules/Tutoring/TutoringService.php`
- Create: `app/Modules/Tutoring/TutoringController.php`
- Modify: `routes/api.php`
- Test: `tests/Modules/Tutoring/TutoringTest.php`

**Interfaces:**
- `Tutoring::classroomForInstructor(int $instructorId): ?array`
- `Tutoring::currentStatus(int $instructorId, string $date): ?array` — latest open (`ended_at IS NULL`) status row for the date.
- `Tutoring::openSession(int $instructorId): ?array`
- `Tutoring::setStatus(int $instructorId, string $status, string $date): array` — closes prior open status row, inserts new.
- `Tutoring::searchStudents(string $query): array`
- `Tutoring::startSession(int $instructorId, int $classroomId, int $studentId, string $date, ?string $clientUuid): array` — links `attendance_id` when a same-day present row exists; idempotent on `client_uuid`; blocked by the one-open-session-per-student constraint.
- `Tutoring::endSession(int $instructorId, int $sessionId): array`
- `Tutoring::board(?string $since): array`
- Controller methods: `me`, `setStatus`, `students`, `startSession`, `endSession`, `board`.

- [ ] **Step 1: Write failing tests** in `tests/Modules/Tutoring/TutoringTest.php` covering: teacher `GET /tutoring/me` returns the classroom + status + open session; `PUT /tutoring/status` closes the previous row; `GET /tutoring/students?q=` filters active students; `POST /tutoring/sessions` creates an open session and is idempotent on `client_uuid`; a second open session for the same student is rejected; `POST /tutoring/sessions/{id}/end` closes it; `GET /tutoring/board` (admin/supporter) lists each classroom with its current student; a non-teacher token gets 403 on teacher endpoints.
- [ ] **Step 2: Implement `Tutoring` data class** with parameterized PDO; `board()` joins `tutoring_classrooms` to the latest open status row and the open session.
- [ ] **Step 3: Implement `TutoringService`**, resolving the caller's `instructor_id` from the decoded JWT (`instructor_id` claim, falling back to a `users.linked_id` lookup); throw `ApiException` for missing classroom/session.
- [ ] **Step 4: Implement `TutoringController`** and register routes in `routes/api.php`:
  - teacher-only (`$requireTeacher`): `GET /tutoring/me`, `PUT /tutoring/status`, `GET /tutoring/students`, `POST /tutoring/sessions`, `POST /tutoring/sessions/{id:[0-9]+}/end`
  - staff (`$requireSupporter`): `GET /tutoring/board`
  - add `$requireTeacher = new Authorization('teacher');` beside the other guards.
- [ ] **Step 5: Run `vendor/bin/phpunit --filter TutoringTest`** and confirm green.

### Task 4: OpenAPI contract for new endpoints

**Files:**
- Modify: `openapi.yaml`
- Test: `tests/Core/OpenApiRemedialTest.php` is NOT required; validate by parsing the YAML.

**Interfaces:**
- New paths under `/api/v1/attendance/*` and `/api/v1/tutoring/*` with the roles above and `security: [bearerAuth: []]`.
- New schemas: `AttendanceEntry`, `AttendanceDay`, `TutoringClassroom`, `TutoringStatus`, `TutoringSession`, `TutoringBoardClass`.
- Extend the role enum in auth response schemas to include `teacher`.

- [ ] **Step 1: Add the paths and schemas** mirroring the exact request/response shapes implemented in Tasks 2 and 3 (no invented fields).
- [ ] **Step 2: Validate** the document parses (`php -r "yaml_parse_file"` if available, otherwise a YAML well-formedness check) — no syntax errors introduced.

### Task 6: Remove dead Remedial code (separate commit)

**Files:**
- Delete: `app/Modules/Remedial/Remedial.php`
- Delete: `app/Modules/Remedial/RemedialService.php`
- Delete: `app/Modules/Remedial/RemedialController.php`
- Delete: `tests/Modules/Remedial/RemedialTest.php`
- Modify: `routes/api.php` (remove the Remedial import, controller instance, and the "Remedial (protected)" route block)
- Modify: `openapi.yaml` (remove the Remedial paths, `RemedialSession`/`RemedialClass` schemas, and the `Remedial` tag)
- Create: `docs/migrations/013_drop_legacy_attendance_remedial.sql.txt` (a NON-executed reference file — see Step 4)

**Context:** The `remedial_*` tables do not exist in the database, so the entire Remedial module is dead; its test also fatals the whole suite via a `seedStudent()` signature clash. This removal is a separate commit so it can be reverted independently. This task does NOT drop any database tables.

- [ ] **Step 1: Delete the four dead files** and remove the Remedial route block, import, and controller instantiation from `routes/api.php`. Confirm no remaining reference: `grep -ri remedial app routes tests`.
- [ ] **Step 2: Remove the Remedial paths, the two Remedial schemas, and the `Remedial` tag** from `openapi.yaml`; confirm the YAML still parses.
- [ ] **Step 3: Run the previously-blocked suite** `php vendor/bin/phpunit` and confirm the fatal is gone.
- [ ] **Step 4: Write the legacy-drop SQL for the operator** to `docs/migrations/013_drop_legacy_attendance_remedial.sql.txt` (the `.txt` extension keeps the auto-running migrator from ever executing it). Its contents: a commented `mysqldump` backup command for the `attendance_*` tables, then `DROP TABLE IF EXISTS` for `attendance_records, attendance_session_students, attendance_sessions, attendance_classes, attendance_archive, session_student_times, remedial_attendance, session_students, remedial_classes, remedial_sessions`, wrapped in `SET FOREIGN_KEY_CHECKS=0/1`. State in the file that it must be run manually after a backup.
- [ ] **Step 5: Commit** the removal and the reference SQL file together.

### Task 5: Full-suite verification

**Files:**
- Test: whole `tests/` suite.

- [ ] **Step 1: Run the full suite** `php vendor/bin/phpunit` and record the result. Any remaining failures must be shown to be pre-existing (unrelated to Tasks 1-6) or fixed.
- [ ] **Step 2: Run a syntax lint** over changed PHP files (`php -l`) — all clean.
