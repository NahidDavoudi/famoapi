

Read-only. No files changed. Line numbers are from the current `prod` snapshot; "~" means ±3 lines.

## System map

```
public/index.php → bootstrap/app.php (Dotenv, Slim AppFactory)
  middleware (outer→inner): Content-Type JSON → CORS/CSRF-origin → ErrorMiddleware → BodyParsing → $_POST merge
  routes/api.php: 87 routes under /api/v1, per-route AuthMiddleware (JWT Bearer or famo_jwt cookie) + optional Authorization('admin'|'supporter')
  Controller → Service → static Model → Database::getConnection() (PDO singleton, native prepares) → MySQL `nadcot_famo`
Core: Auth (HS256 JWT: sub, role, student_id, iat, exp), AuthCookie, Authorization, StudentScope, Validator, Pagination, Storage, ExceptionHandler, Migrator, SmsService, EmailService (unused), ResponseHelper (unused)
Modules (15): Auth, Public, Blog, Students, Courses, Instructors, Supporters, Exams, WeeklyPlans(+PlanTemplate), Topics, Appointments, Remedial, Reports, Files, Dashboard
Schema: 5 migrations; 17 of 22 tables used in code are never created by any migration (legacy DB assumed)
Contracts: openapi.yaml (87 ops, path/method match routes 1:1), ARCHITECTURE.md, IMPL_PLAN.md. No frontend code in repo.
```

---

## Executive summary

- **Anyone on the internet can get a valid `student` JWT** via public `POST /auth/register`, and ~20 routes are guarded only by "has a token". Every finding below marked "student-reachable" is therefore effectively public.
- **SQL injection** in `PUT /remedial/students/time` (INSERT branch interpolates the client-supplied `field` name). Student-reachable, errors swallowed, always returns `success:true`.
- **Whole Remedial module has no role check**: students can delete classes, toggle anyone's attendance, add/remove students, create sessions.
- **`GET /students` and `GET /students/list` are student-reachable** and return `s.*` (phone, national_id) for every student. Combined with **fixed password `1234`** for every admin-created student and supporter, no change-password endpoint, no login rate limit, and no 2FA for students: this is an account-takeover chain for the entire student base.
- `POST /students/{id}/toggle-status` has no role or scope check: any student can deactivate any student.
- `PlanTemplate` stores `serialize()` and reads `@unserialize()` with no `allowed_classes`; client can supply raw `items_json`. Admin-only, but a PHP object-injection sink.
- JWT secret has a hard-coded fallback; tokens cannot be revoked; deactivated/deleted users keep valid tokens for 24h; login never checks `is_active`.
- 2FA: multiple codes valid simultaneously, no attempt counter, no challenge token, `verify-2fa` needs only `user_id + code`.
- **Systemic error-handling bug**: every controller catches `\RuntimeException` (which includes `PDOException`) and calls `withStatus($e->getCode())` with a SQLSTATE string. Slim rejects it, so every DB error becomes an opaque 500; Blog and Plans leak raw SQL messages instead.
- **Systemic pagination bug**: `Pagination::build` clamps `per_page` to 1..100 but every service passes the raw value to SQL. `per_page=1000000` dumps whole tables (including on unauthenticated `/public/blog/posts`); `per_page=-1` is a 500.
- Course/instructor image upload is **dead code**: controller and service use different keys, and `Storage::upload` expects `$_FILES` arrays but receives PSR-7 objects. PUT + multipart silently does nothing (200, unchanged row). No download endpoint exists for any uploaded file.
- Data integrity: `Student::delete` leaves orphans in 9+ tables; `register` allocates ids with `MAX(id)+1` (races, and id reuse hands a new student the deleted student's data). Plan delete orphans `events`; class delete orphans attendance. `national_id` is BIGINT (leading zeros lost).
- Exam bulk save always INSERTs (re-submit duplicates all rows), accepts negative counts, silently skips invalid rows.
- Remedial "create session copies all students" was never ported; `GET /remedial/sessions/{id}` returns classes only (attendance and times written by other endpoints can never be read back).
- Migrator swallows failures and exits 0; migration 001 never applies the blog FK (SET @sql never executed) and uses MariaDB-only `IF NOT EXISTS`; CI runs no tests; `composer.lock` is missing; `config/*.php` is never loaded (dead config).

---

## Critical findings

### C1. SQL injection via column-name interpolation — `PUT /api/v1/remedial/students/time`
- **Category:** Security / SQL injection · **Confidence:** CONFIRMED (code path verified directly)
- **File:** `app/Modules/Remedial/Remedial.php` `updateStudentTime()` L148–183; `RemedialController::updateStudentTime` ~L141–160; `routes/api.php` L179
- **What it does:** Allowlist check (`$allowed = ['start_time','end_time','total_minutes']`, L159–160) runs only in the UPDATE branch. In the INSERT branch (L165–176) `$field` from the request body goes straight into `INSERT INTO session_student_times (session_id,student_id,{$field}) VALUES (...)`. The whole method is wrapped in `catch (\Exception $e) {}`.
- **Failure scenario:** Register as a student. Send `session_id`/`student_id` that have no row (forces INSERT branch) with `field` = `start_time) VALUES (1,1,(SELECT IF(ASCII(SUBSTR((SELECT password_hash FROM users WHERE role='admin' LIMIT 1),1,1))>64,SLEEP(3),0)))#`. Placeholders stay balanced so native prepares accept it. Time-based blind extraction of any table (password hashes, live 2FA codes) with zero error signal or log entry.
- **Fix:** Validate `field` against the allowlist before either branch (controller, 422). Map API names to fixed columns. Add `$requireSupporter` to the route. Remove the silent catch.

### C2. Open registration + token-only routes = privilege escalation root cause
- **Category:** Access control design · **Confidence:** CONFIRMED
- **Files:** `routes/api.php` L70 (`register`, no middleware); `app/Core/Authorization.php` L41–76 (knows only `admin`/`supporter`); `AuthService::register` (issues `role:student` token immediately)
- **What it does:** "Authenticated" has no floor. `Authorization` has no default-deny for students; `authMiddleware` alone means "any person on the internet". Routes affected (no role check, no `StudentScope`): `/students`, `/students/list`, `/students/{id}/toggle-status`, `/blog/posts`, `/blog/posts/{id}`, `/courses*`, `/instructors*`, `/supporters*`, `/topics*`, `/subjects/{grade}`, all 9 `/remedial/*`, `/auth/me`.
- **Fix:** Treat every token-only route as public. Add `$requireSupporter` to staff reads, `$requireAdmin` to writes. Consider default-deny for `student` with explicit allowlist. Also note `Authorization` lets unknown role strings pass (LOW, latent).

### C3. Student PII directory open to any token, feeding a mass account-takeover chain
- **Category:** Broken access control + credential management · **Confidence:** CONFIRMED
- **Files:** `routes/api.php` L95, L97; `Student::findAll` L9–54 (`SELECT s.*, u.id AS user_id ...`), `Student::getList` ~L161–168; `StudentService.php` L37, L111, L134 and `SupporterService.php` L37 (`password_hash('1234', ...)`); `AuthService::login` L41 (2FA only for `admin`/`supporter`); no change-password route anywhere.
- **Failure scenario:** (1) `POST /auth/register`. (2) `GET /students?perPage=100000&status=0` and `status=1` → every student's phone + national_id (per-page unclamped, see H10). (3) For each phone: `POST /auth/login {username: phone, password: "1234"}`. Username *is* the phone. No rate limit, no 2FA for students, admin "reset password" writes `1234` again. Result: log in as any admin-created student; read their exams, analytics, files; upload/delete their files.
- **Fix:** `$requireSupporter` on both list routes, explicit column list. Random one-time password on create/reset (openapi already documents returning `password`/`new_password`). `must_change_password` flag + change-password endpoint (IMPL_PLAN L185 asks for this). Rate-limit `/auth/login` per username and IP.

### C4. Entire Remedial module writable by any token
- **Category:** Broken access control · **Confidence:** CONFIRMED
- **Files:** `routes/api.php` L174–182 (all 9 routes `->add($authMiddleware)` only); `RemedialController`/`RemedialService` contain no role or scope check. Compare `/appointments` L168–171 which use `$requireSupporter`.
- **Failure scenario:** Student loops `DELETE /remedial/classes/1..N` (200 every time, even for nonexistent ids). Marks self present everywhere via `POST /remedial/attendance`. Removes rivals via `DELETE /remedial/students`.
- **Fix:** `$requireSupporter` on the group.

---

## High priority findings

| # | Finding | File / location | Confidence |
|---|---|---|---|
| H1 | `POST /students/{id}/toggle-status` has no role check and no `resolveStudentId`; `UPDATE students SET is_active = NOT is_active WHERE id=:id`; response returns full `findById` (phone, national_id) for any id. Also: deactivation does not block login (`AuthService::login` never reads `is_active`). | `routes/api.php` L104; `StudentController::toggleStatus` L275–299; `Student::toggleStatus` L190–196 | CONFIRMED |
| H2 | PHP object injection: `create()` stores `serialize($data['items'])` OR the client's raw `items_json` string unchanged; `applyToStudent()` calls `@unserialize()` with no `allowed_classes`. Response also exposes raw serialized `items_json`. Admin-only route limits severity. | `PlanTemplate.php` L31–45, L80–93 | CONFIRMED sink / POSSIBLE RCE |
| H3 | 2FA brute-forceable: each `login` inserts a new code without invalidating earlier ones; no attempts column (`003_add_2fa.sql`); `verify2fa` needs only `user_id` (small int, returned by login) + 6-digit code; SELECT then UPDATE `used=1` without `rowCount()` check (TOCTOU); codes plaintext; each login = one paid SMS (SMS bombing). | `AuthService::login` L41–66, `verify2fa` L90–131 | CONFIRMED |
| H4 | JWT secret falls back to `'famo-jwt-secret-change-in-production'`; `Dotenv::load()` does not require the key; test relies on the fallback. If prod `.env` lacks `JWT_SECRET`, anyone can forge `{"sub":1,"role":"admin"}`. | `app/Core/Auth.php` L14, L31 | POSSIBLE (depends on deployed .env) |
| H5 | Systemic: every controller `catch (\RuntimeException $e)` → `withStatus($e->getCode() ?: 500)`. `PDOException` extends `RuntimeException`, code is SQLSTATE string `'23000'`. Slim PSR-7 `withStatus` throws `InvalidArgumentException` → generic 500, real DB error lost from logs. `BlogController` returns raw SQL text as 400 `VALIDATION_ERROR`. `PlanController::fail` casts `(int)'23000'`=23000 → falls to 400 with raw SQL message. `ExamController::getDetails` maps any DB error (incl. connection failure) to **404** with the driver message. | All controllers; `ExamController` L70–78, L114–124; `PlanController::fail` L20–31; `StudentController` L98,124,159,185,211,237,289,315,341,367,393 | CONFIRMED |
| H6 | Student search returns 500: `:search` named placeholder used twice with `ATTR_EMULATE_PREPARES=false` (HY093). `File.php` correctly uses `:studentId2`. | `Student::findAll` L25, `countAll` L71; `Database.php` L23 | LIKELY |
| H7 | `Student::delete` removes only `users`+`students`; leaves `exam_results`, `weekly_plans`, `events`, `event_topics`, `files` (rows and disk), `appointments`, `reports_status`, `remedial_attendance`, `session_students`, `session_student_times`, `plan_templates.student_id`. No FKs on `student_id` in any migration. `register` allocates `SELECT MAX(id)+1` → next registrant after deleting highest-id student **inherits that student's exams/plans/files** through `StudentScope`. Concurrent registers collide (PK error → 500 via H5, or duplicate rows if legacy table lacks PK; migration 005 repairs `students`/`events` only, not `users`). | `Student::delete` L151–159; `AuthService::register` L134–188 | LIKELY (orphans CONFIRMED) |
| H8 | Course/instructor image upload is dead code and crashes: controller stores `UploadedFile` under `background_image_url`/`image_url`, service checks `background_image`/`image` (never set) → object bound into PDO → `Error` (not RuntimeException) → 500. Even with keys fixed, `Storage::upload(array $file)` expects `$_FILES` shape, would `TypeError` on PSR-7 object. | `CourseController` ~L52–67, ~L119–133; `CourseService` L23–33, L44–62; `InstructorController` ~L52–66; `InstructorService` L23–35, L47–70; `Storage::upload` L10 | CONFIRMED |
| H9 | `PUT /courses/{id}` and `PUT /instructors/{id}` with multipart silently no-op: PHP does not populate `$_POST`/`$_FILES` for PUT, Slim does not parse multipart, `$_POST` merge in bootstrap only helps POST → `$data=[]` → `Course::update` returns 0 → 200 with old row. | `bootstrap/app.php` L16–23; Course/Instructor `update` | CONFIRMED (PHP behavior) |
| H10 | Systemic pagination: `Pagination::build` clamps to 1..100 in metadata but every service passes raw `$perPage` to `LIMIT`. `per_page=1000000` dumps tables (incl. unauthenticated `/public/blog/posts` with full `content`); `-1` → 500; `0` → empty data with `per_page:20` metadata; `page>total_pages` silently clamped (infinite-scroll clients get repeated rows). `ExamController::getAll` defaults to 200 (already over cap: page 1 returns 200 rows, metadata says 100/4 pages, pages 3–4 empty). | `Pagination.php` L7–30; Student/Exam(3)/Appointment/Blog(3)/File/Report/Course/Instructor/Supporter services | CONFIRMED |
| H11 | No download endpoint for any file; uploads stored outside `public/` at `{repo}/uploads/{module}/{id}/{uniqid}.ext`; DB stores relative path. Frontend receives `file_path`/`background_image_url` it cannot resolve. Student exam files can be listed and deleted but never opened. | `Storage.php` L22, L39; `routes/api.php` Files block; `public/` contains only `index.php` | CONFIRMED (web-server alias unverified) |
| H12 | `national_id` migrated to BIGINT; validator accepts `^\d{10}$` incl. leading `00`; openapi types it string. `0012345678` → stored as `12345678`. Permanent data loss; IMPL_PLAN's own risk register names this. | `001_fix_schema.sql` L3; `Validator::nationalId` L32–37 | CONFIRMED |
| H13 | Migrator: `$pdo->exec()` on multi-statement files reports only the first statement's error; `catch (\Throwable)` → `error_log` and continue; `migrate.php` always exits 0 and prints "executed". Half-applied DDL recorded as done. No `UNIQUE(migration)`, no lock. `migrate.php` at repo root with no CLI guard (web-triggerable if docroot is repo root). | `Migrator.php` L25–47; `migrate.php` | CONFIRMED behavior / LIKELY impact |

---

## Functional / API gaps

Evidence-backed only (docs, schema, unused model methods, or sibling-route conventions).

| Gap | Evidence | Confidence |
|---|---|---|
| **Remedial "create session copies all students" not implemented.** Single `INSERT INTO remedial_sessions`; nothing written to `session_students`; no transaction; no duplicate check. | ARCHITECTURE.md L303, IMPL_PLAN Step 5.2 | CONFIRMED |
| **`GET /remedial/sessions/{id}` returns classes only**, grouped by a hard-coded 5-field list (`ریاضی, تجربی, انسانی, زبان, هنر`). No students, no attendance, no times. `Remedial::getAttendees` (~L134) exists, never called. Classes with any other `field` value (typo, trailing space) are stored but never returned; `createClass` doesn't validate `field`. | `RemedialService::getSessionData` L21–38 | CONFIRMED |
| **No list of enrolled students per session**, so `POST/DELETE /remedial/students` has no read counterpart. | Route table | CONFIRMED |
| **No `PUT /appointments/{id}`, no `GET /appointments/{id}`**; only status update exists. Cannot move an appointment to another date except via the (student,date) upsert. No session update/delete, no class update. | IMPL_PLAN "CRUD for appointments", "Class CRUD per session" | CONFIRMED |
| **No change-password endpoint** for any role; **no `must_change_password`** flag. | IMPL_PLAN L185; fixed `1234` | CONFIRMED |
| **No file download/stream endpoint** (see H11). | Files routes | CONFIRMED |
| **`POST /plans/items` and `POST /plans/bulk`** documented, not routed; `GET /topics/{id}/children` documented as `/topics/{parent_id}`. | ARCHITECTURE.md §7 | CONFIRMED |
| **`GET /blog/categories` (admin)** documented, not routed. | ARCHITECTURE.md §7 | CONFIRMED |
| **Plan template apply cannot target a week**: body has no `week_date`; always uses template's saved week or today. | `PlanTemplate::applyToStudent` L95–101 | CONFIRMED |
| **Appointments list has no filters** (date, status, student); ordered by `appointment_date DESC` only, no tiebreaker → unstable paging. | `Appointment::findAll` L9–23 | CONFIRMED |
| **Reports module is read-only**: no reply/write endpoint; supporters see all supporters' reports (no per-supporter scoping). | `Report.php`, routes | CONFIRMED (design question) |
| **`GET /subjects/{grade}` ignores `field` and reads `events`, not a curriculum table.** | `TopicController::getSubjectsForGrade` | CONFIRMED |
| **`EmailService`, `ResponseHelper`, `JalaliHelper`, `SmsService::sendAbsenceReminder`, `Appointment::getThisWeekForStudent`, `parent_contacts`** all exist and are never called. | grep | CONFIRMED |
| **Public courses/instructors/supporters have no publish/active flag**; `findAllPublished()` is `SELECT * FROM courses` with no filter. Every half-filled admin row is instantly public. | `Public/Course.php` L9–17, `Public/Instructor.php`, `Public/Supporter.php` | CONFIRMED |

---

## Logic & data integrity

| # | Finding | Location | Conf. |
|---|---|---|---|
| L1 | **Exam bulk save always INSERTs** (no delete-then-insert, no upsert). Admin re-submit doubles every subject; `COUNT`, `subject_count`, averages, dashboard, analytics all wrong. | `ExamResult::saveBulk` L150–186 | CONFIRMED |
| L2 | Exam save: only check is `correct+wrong+skipped <= total`. Negatives pass (`{total:10, correct:30, wrong:-20}` → percentage 300.00). Invalid rows `continue` silently; response `success:true, inserted:N`. `total_q:0` stores NULL percentage but counts in `subject_count`. Non-array `subjects` → TypeError 500. `exam_date` unvalidated. | `ExamResult::saveBulk` L159–173; `ExamService::save` L40–48 | CONFIRMED |
| L3 | **Appointments "create/update"**: matches on `(student_id, appointment_date)`; second booking same day silently overwrites first (times/type/description), keeps old `status` (a `cancelled` row gets "rebooked" but stays cancelled), returns 201 for updates, no SMS on change. No slot-overlap/double-booking check. `description`/`type` default `''` → update blanks omitted fields. IMPL_PLAN describes *weekly* upsert; `getThisWeekForStudent` (YEARWEEK) exists unused. Read-then-write, no unique key verified. | `AppointmentService::createOrUpdate` L26–49; `Appointment::findByStudentAndDate` L48–60 | CONFIRMED |
| L4 | Appointment `status`: any string stored, any transition allowed. openapi says `pending/confirmed/completed/cancelled`; IMPL_PLAN says `pending/accepted/rejected/completed/cancelled`. Missing `status` key → `null` to `string` param → TypeError 500. | `AppointmentService::updateStatus` L51–60 | CONFIRMED |
| L5 | **Attendance toggle race**: read-then-write, no transaction/lock. Double-click → duplicate rows (no unique key) or second insert fails → `catch` returns `attended:false` with HTTP 200 while DB says present. Not idempotent. `(int)$body['session_id']` on missing key → 0 → inserts rows with id 0. No check that class belongs to session or student is enrolled. | `Remedial::toggleAttendance` ~L99–131 | CONFIRMED |
| L6 | `deleteClass` leaves `remedial_attendance` orphans; `removeStudent` leaves attendance + times orphans; both return 200 for nonexistent ids. No FKs on `remedial_*`. | `Remedial.php` ~L89–96 | CONFIRMED |
| L7 | **Template apply = destructive replace**: calls `WeeklyPlan::save` with `weekly_notes=>null, times=>[]` → overwrites notes, wipes `times_json`, `DELETE FROM events WHERE plan_id`. No warning. Target week not selectable. | `PlanTemplate::applyToStudent` L95–101; `WeeklyPlan::save` L121–151 | CONFIRMED |
| L8 | **Plan save mutates students outside the plan transaction**: `resolveStudent` may `Student::create` or `Student::update` (name/grade/field/national_id from `meta`) *before* `WeeklyPlan::save` begins its transaction. Match by `name+grade LIMIT 1` can pick wrong person. Failed plan save → committed phantom student, retry creates another. `PUT /plans/{id}` with stale `meta.name` renames the student; without `student_id` can move plan to a different student. | `PlanService::saveFull` L57–79, `resolveStudent` L96–143, `applyStudentUpdates` L145–164 | CONFIRMED |
| L9 | `WeeklyPlan::delete` / `clearForStudent` delete only `weekly_plans`; `events` orphaned (update path deletes them, delete path doesn't). No FK `events.plan_id`. Orphans still counted by `StudentService` analytics (L172, L242) and `/subjects/{grade}`. | `WeeklyPlan.php` L281–293 | LIKELY |
| L10 | `week_date` never validated or normalized to Saturday. Empty string skips "reuse existing plan" lookup → inserted into `DATE NOT NULL`. Saving Monday then Tuesday same week = two "weekly" plans. | `PlanService::saveFull` L62; `WeeklyPlan::save` L110–163 | CONFIRMED |
| L11 | `analytics/week-detail` ignores `plan_id`/`week_date`: `SELECT * FROM events WHERE student_id=?` merges all weeks into one grid. | `StudentService::getAnalyticsWeekDetail` L233–272 | CONFIRMED |
| L12 | Dashboard week start: `strtotime('saturday last week')` = previous ISO week's Saturday → on Sat/Sun (first 2 days of Iranian week) returns the Saturday 7 days earlier; "this week" spans 8–9 days. No upper bound (future exams counted). Server TZ is UTC (`bootstrap` L12): 00:00–03:30 Tehran on Saturday still "Friday". Counts inactive students; `exams_this_week` counts dates; `avg_by_field` weighted by rows. | `DashboardController::stats` L16–35 | CONFIRMED |
| L13 | Reports: `findAll` INNER JOINs `students`, `countAll` doesn't → `total` inflated by orphans, last pages empty. `date_to` uses `<=` on what `getStats` treats as DATETIME → excludes the last day. Dates unvalidated. "Pending" defined 3 ways (`=0 OR NULL OR ='pending'` in Dashboard vs `=0 OR NULL` elsewhere); string/int cast ambiguity. | `Report.php` L9–66; `DashboardController` L29–31 | CONFIRMED / POSSIBLE |
| L14 | **Blog category split**: `getCategories` counts by `bp.category_id = bc.id`; `findByCategory` filters `WHERE category = ?` (string). Frontend gets `post_count:5` from categories, then `/categories/{slug}/posts` returns 0 unless admin typed the slug into the free-text field. Create/update accept both, no sync/validation. | `BlogPost.php` L38–55, L87–139, L149–167 | CONFIRMED |
| L15 | `published_at` resets to now on **every** edit with `is_published=1` (`!empty($data['is_published']) && empty($data['published_at'])`). Old post jumps to top of public list on typo fix. `create` sets `published_at` even for drafts. Can never be cleared. | `BlogPost::update` L110–139 | CONFIRMED |
| L16 | JSON `is_published:false` binds as `''` → error 1366 in STRICT mode → 400 with raw SQL text (via H5). openapi field is `published` (ignored by code, so spec-following clients can never publish). | `BlogPost::create` L94–104, `update` L118–133 | LIKELY |
| L17 | Blog `incrementViews` runs UPDATE on every public read; `makeUniqueSlug` is check-then-insert (race). | `BlogService::getPost` L32–39; `BlogPost::makeUniqueSlug` | CONFIRMED |
| L18 | `PUT /students/{id}` has **no validation**; `phone:"abc"` also rewrites `users.username` (service L69–79) → student locked out. No uniqueness check on username/national_id anywhere in admin create/update/create-account; `findByUsername ... LIMIT 1` with no ORDER BY → siblings sharing a parent phone log in as each other at random. Double-click create-account → two users rows → LEFT JOIN in `findAll` returns student twice. | `StudentController::update` L136–169; `StudentService` L23–117 | CONFIRMED (effects POSSIBLE) |
| L19 | `StudentService::update` (students then users) and `Student::delete` (users then students) touch two tables with no transaction. | L60–81; L151–159 | CONFIRMED |
| L20 | File upload: `findById`/`findAll` JOIN `students`, `countAll` doesn't. Admin upload with nonexistent `student_id` → file moved, row inserted, `findById` null → 500, orphan file+row invisible in list but counted. INSERT failure after `moveTo` → orphan on disk. Students can delete staff-uploaded exam files (owner_id is only owner; uploader not recorded). | `FileService::upload` L23–44, `deleteFile` L46–60; `File.php` L9–57 | CONFIRMED |
| L21 | `Storage::delete` path traversal (admin-level): `CourseController::create` stores arbitrary `background_image_url` string; `CourseService::delete` → `Storage::delete($path)` → `$basePath . '/' . ltrim($path,'/')`, no `realpath`. `../.env` deletable. | `Storage.php` L43–52; `CourseService::delete` L65–79 | CONFIRMED |
| L22 | `JalaliHelper` is wrong in both directions (corrupted jdf: 33-year Gregorian leap rule, `%= 1461` misapplied, dead vars). `gregorianToJalali(2026,9,26)` → `1411/06/31` (correct: `1405/07/04`). Zero callers today; ARCHITECTURE.md names it the single converter. | `app/Helpers/JalaliHelper.php` L17–85 | CONFIRMED |
| L23 | `Topic::getPath`: `while ($currentId !== null)` climbs parents one query per level, no cycle guard, no depth bound; roots use `parent_id=0` so always one extra query for `id=0`. A cyclic row → infinite loop. Student-reachable. | `Topic.php` L35–60 | CONFIRMED code / POSSIBLE data |
| L24 | Migration 005 renumbers `students.id=0` rows but does not update `users.linked_id`, `events.student_id`, `exam_results.student_id`, `files.owner_id` → those students get 403 from `StudentScope` and their data is orphaned. | `005_planner_backend.sql` L25–35 | LIKELY |

---

## Authentication & authorization

Covered in C1–C4, H1, H3, H4. Additional:

| # | Finding | Location | Conf. |
|---|---|---|---|
| A1 | **No revocation, no re-validation**: middleware accepts any signed unexpired token; never loads user. `role`/`student_id` trusted from claims. `logout` is unauthenticated and only expires the cookie; Bearer token stays valid 24h. Demoted/deleted supporters and deleted students retain access. Login never checks `students.is_active`. No `jti`, no `nbf`, no leeway configured. | `AuthMiddleware.php` L26–33; `Auth.php` L15–23; `AuthController::logout` | CONFIRMED |
| A2 | Register: password min length 4, no max (bcrypt truncates at 72 bytes); `field` only `required` (not checked against `validFields`); `national_id` uniqueness unchecked; `students` insert omits `is_active`/`created_at` (visibility depends on unknown defaults); no CAPTCHA/rate limit. | `AuthController::register`; `AuthService::register` | CONFIRMED |
| A3 | Supporter directory readable by students: `GET /supporters/{id}` returns `s.*, u.username, u.role`; `username` = supporter's phone = 2FA SMS target. `chat_id` also exposed, including on **unauthenticated** `/public/supporters` (`SELECT * FROM supporters`). Supporters also created with password `1234`. | `Supporters/Supporter.php` ~L40–51; `Public/Supporter.php` L9–18 | CONFIRMED |
| A4 | Students can read unpublished blog drafts: `GET /blog/posts` and `/blog/posts/{id}` are token-only and `findAll`/`findById` have no `is_published` filter. ARCHITECTURE.md says "published only for non-admin". | `routes` L88–89; `BlogPost::findAll` L57–66 | CONFIRMED |
| A5 | Login 2FA contract drift: code sends SMS to `username`, returns `phone_mask` + `username` + `role`; openapi documents `email_mask`. SMS failure → 500 `AUTH_ERROR`. Cookie auth mode (`X-Auth-Mode: cookie`, `famo_jwt` cookie, `AuthMiddleware` accepts it) is undocumented in ARCHITECTURE §8 and openapi. | `AuthService::login` L40–66; `AuthCookie.php` | CONFIRMED |
| A6 | CORS/CSRF: Origin check for cookie writes is applied to all routes (good). `allowedOrigins()` falls back to hard-coded famoacademy.ir list when `CORS_ORIGINS` unset; `config/app.php` `cors.origins='*'` is dead config (file never loaded). Bearer auth ignores Origin entirely (expected). Cookie: HttpOnly always; Secure/SameSite/Domain derived from env or host. | `bootstrap/app.php` L84–150; `AuthCookie.php` L27–52, L69–104 | CONFIRMED |
| A7 | `Authorization` middleware lets **unknown role strings pass** (only rejects known lower roles). Latent: any future claim tampering or role typo grants access. | `Authorization.php` L41–76 | CONFIRMED |
| A8 | `Storage::upload` checks extension only (`pathinfo` of client filename), no MIME/magic bytes; `mkdir 0755`. Default path is outside webroot (mitigating), but `UPLOADS_PATH` env could point inside. | `Storage.php` L10–41 | CONFIRMED code / POSSIBLE exploit |

---

## Performance

**Confirmed bottlenecks**
- N+1 on public homepage endpoints: one query per course for features, one per instructor for social links (`PublicService` L8–29). 15 courses → 16 queries per uncached request; `Public\Course::getFeatures` swallows exceptions (returns `[]`).
- Write-on-every-read: `incrementViews` UPDATE on every public post view (`BlogService` L35).
- `Topic::getPath` one query per tree level (L35–60), student-reachable, unbounded.
- `getSessionData` runs 5 fixed queries; adding attendance the obvious way would be N+1.
- Zero HTTP caching (`Cache-Control`/`ETag`) anywhere; zero application caching; `SELECT *` in all Public models and most list endpoints (ships `full_description`, full blog `content`, `times_json` for every plan).

**Likely bottlenecks**
- Unclamped `per_page` (H10) allows whole-table dumps with JOINs from a single anonymous request.
- `Student::plannerOverview` (L171–188): `LEFT JOIN weekly_plans × LEFT JOIN events` fan-out per student before `COUNT(DISTINCT)`.
- `StudentService::getAnalyticsSummary` runs 6 queries; weakest/strongest subject could be one.
- `SmsService::send` blocking cURL (15s timeout + 10s connect) inside `POST /appointments` after commit → client timeout → retry → upsert again.
- No index evidence for `blog_posts.slug`/`is_published`/`published_at`, `courses.display_order`, `exam_results(student_id, exam_date)`, `events(plan_id)`, `events(student_id)`. Repo migrations add indexes only on `weekly_plans`, `login_codes`, `event_topics`. Base schema not in repo, so **unverified**; queries make these indexes clearly warranted.

**Speculative**: OPcache/FPM/MySQL config (not visible in repo).

---

## Architecture

| # | Finding | Conf. |
|---|---|---|
| R1 | **Duplicate models**: `Public\Course` vs `Courses\Course`, `Public\Instructor` vs `Instructors\Instructor`, `Public\Supporter` vs `Supporters\Supporter` query the same tables with different assumptions (Public ones named `findAllPublished` but have no filter). | CONFIRMED |
| R2 | **Dead infrastructure**: `config/app.php` and `config/database.php` never `require`d (grep: zero hits). `ResponseHelper` never called; every controller hand-builds the envelope. `EmailService` unused (`users.email` column added in 003 for nothing). `JalaliHelper` unused and broken. `Pagination` metadata computed then ignored by SQL. | CONFIRMED |
| R3 | **Exception taxonomy collapsed**: services throw `\RuntimeException` with HTTP codes; `PDOException` is also a `RuntimeException`; controllers can't distinguish domain from infrastructure errors (H5). | CONFIRMED |
| R4 | **Silent-catch pattern** across Remedial (every method), Topic, PlanTemplate, `Public\Course::getFeatures`: DB outage renders as "no data, 200". | CONFIRMED |
| R5 | **Schema not owned by repo**: 17 of 22 tables never created by a migration (`users`, `students`, `courses`, `appointments`, `remedial_*`, `reports_status`, `files`, `exam_results`, `events`, `blog_categories`, ...). Cannot build fresh env/CI DB. Migrations 001/005 ALTER tables the repo never creates. 002 `CREATE TABLE IF NOT EXISTS weekly_plans` is a no-op against legacy table (its `UNIQUE(student_id, week_date)` never applied). 001/003/005 use MariaDB-only `ADD COLUMN IF NOT EXISTS`; 001 `SET @sql` for blog FK never PREPARE/EXECUTEd; `ALTER DATABASE \`nadcot_famo\`` hard-coded. | CONFIRMED |
| R6 | **Legacy coexistence**: `AuthService::register` uses `MAX(id)+1` ("legacy imports have no AUTO_INCREMENT", L139) while `StudentService::create` relies on AUTO_INCREMENT → two id-allocation strategies on the same tables. `WeeklyPlan` handles `topic_id`/`topicId` legacy shapes (L265–269). `/plans` marked "temporary: admin-only while planner is being migrated" (`routes` L138). | CONFIRMED |
| R7 | **Remedial model collapsed**: ARCHITECTURE/IMPL_PLAN specify 5 models; code has one `Remedial.php` with mixed responsibilities. ARCHITECTURE says `POST /remedial/students` does "Add/remove"; code splits POST/DELETE. `DELETE /remedial/students` relies on a request body (proxies may drop). | CONFIRMED |
| R8 | **Tooling**: no `composer.lock` (CI cache key references it; `validate --strict` will complain); CI on `prod` only, test step commented out; single test file covers cookie attributes only; `composer.json` ignores GHSA-2x45-7fc3-mxwq without reason; `firebase/php-jwt ^6.0` (v7 enforces key length). No `.gitignore`, `.env.example`, `.htaccess`, `storage/` in repo (ExceptionHandler writes to `storage/logs/app.log`, which will fail if dir absent). | CONFIRMED |

---

## Frontend ↔ backend contract issues

No frontend code in repo; verified against `openapi.yaml` (87 ops, path+method match routes exactly).

| Endpoint / schema | Code | openapi / docs | Conf. |
|---|---|---|---|
| Pagination param | `perPage` in Students/Appointments/Files; `per_page` in Blog/Exams/Reports | `per_page` | CONFIRMED |
| `POST/PUT /blog/posts` | `data: {post: {...}}`; input `is_published` | `data: BlogPost`; input `published` (ignored by code) | CONFIRMED |
| File schema | `owner_type, owner_id, file_type, file_path, file_size, created_at, student_name` | `student_id, original_name, stored_name, path, mime_type, size, uploaded_at` (none match) | CONFIRMED |
| Topic | flat `label, parent_id, sort_order` | `title, type` enum, nested `children` | CONFIRMED |
| Report | `report_date, supporter_id, status:int`, wrapped `{reports}` | `date, status:string, description` | CONFIRMED |
| Student | `is_active`, `national_id` numeric | `status` 0/1, `national_id` string | CONFIRMED |
| `create-account` / `reset-password` | return full Student; password `1234` never returned | `{username, password}` / `{new_password}` | CONFIRMED |
| Plan templates | `items_json` (serialized PHP) | `items` array | CONFIRMED |
| Login 2FA | `phone_mask` | `email_mask` | CONFIRMED |
| 401 error code | middleware sends `UNAUTHORIZED` | example shows `AUTH_ERROR` | CONFIRMED |
| Validation status | 422 in Auth/Students/Files; 400 in Exams/Plans/Blog | mixed | CONFIRMED |
| `POST /appointments` | 201 on update too | — | CONFIRMED |
| `createClass` | returns `{id}` | full `RemedialClass` | CONFIRMED |
| Remedial ops | no 403 documented | matches missing role check | CONFIRMED |
| Cookie auth mode | `X-Auth-Mode: cookie`, `famo_jwt` | Bearer only | CONFIRMED |
| Error codes seen | `AUTH_ERROR, UNAUTHORIZED, REQUEST_ERROR, ERROR, CREATION_ERROR, UPDATE_ERROR, DELETE_ERROR, PLAN_ERROR, UPLOAD_ERROR, NOT_FOUND, VALIDATION_ERROR` | no enum | CONFIRMED |
| `BlogController::updatePost` | picks 404 by comparing Persian message string | — | CONFIRMED |

---

## Low priority / technical debt

- `Validator::inArray` loose comparison (`true == 7`); `required` accepts arrays (PDO array-to-string error); `maxLength`/`minLength` call `mb_strlen` on non-strings; `nationalId` no checksum; `phone` no `+98` normalization; LIKE wildcards unescaped in student/topic search (`search=%` matches all; bound, so no injection).
- `status` filter on `/students`: `(int)` cast → `status=all` shows inactive only; undocumented `status=2` shows all.
- `analytics/summary.plan_count` counts `events`, while `overview` counts `weekly_plans`. Same name, different numbers.
- `avg_percentage` returned as string; `AVG(percentage)` unweighted across subjects. Migration 004 replaced standard Konkur negative-marking formula with plain ratio and calls the correct Konkur result "under-reporting". Confirm intent.
- `date('Y/m/d')` default in plans uses UTC.
- Topic search: no min length, full-table LIKE scan.
- Template delete always returns success; `getTemplate` 404 undocumented.
- `addStudent`/`removeStudent` return `success:true` wrapping `data.success:false`.
- Preflight from disallowed origin: 403 with empty body (non-envelope).
- `Public\Course::getFeatures` swallows exceptions.
- `Database::reset()` exists for tests but singleton has no reconnect logic.

---

## Recommended fix order

1. **C1** — allowlist `field` before both branches in `Remedial::updateStudentTime`. One-line fix, closes SQLi today.
2. **C4 + H1 + C3 (routes)** — add `$requireSupporter`/`$requireAdmin` to Remedial, `toggle-status`, `/students`, `/students/list`, `/blog/posts*`, `/supporters*`. Pure route edits; closes the escalation surface before anything else. Do this before touching credentials so leaked phones stop flowing.
3. **C3 (credentials)** — random password on create/reset, `must_change_password`, change-password endpoint, login rate limit. Depends on (2) so the directory isn't harvestable while you roll it out.
4. **H4 + H3** — fail boot without `JWT_SECRET`; 2FA challenge token, invalidate prior codes, attempt counter. Independent; do alongside (3).
5. **H5** — introduce a domain `ApiException` with int code; let `PDOException` reach `ExceptionHandler`; map 23000→409. **Must precede** every data-integrity fix below, because otherwise the new unique constraints will surface as opaque 500s.
6. **H10** — pass `$pagination['per_page']` to all models. Trivial, systemic, closes the anonymous table-dump.
7. **H7 + L24 + R6** — baseline schema migration (`000_baseline.sql` from `SHOW CREATE TABLE`), fix `users` AUTO_INCREMENT/PK, `UNIQUE(users.username)`, drop `MAX(id)+1`, cascade or soft-delete students, fix 005's `linked_id`. Requires **H13** (migrator that fails loudly) first or you can't trust the result.
8. **H13 + R5** — migrator stop-on-failure, non-zero exit, statement splitting, MySQL-compatible guards, execute the blog FK.
9. **L1 + L2** — exam save: validate then delete-and-insert (or upsert with unique key) in one transaction.
10. **L3–L6** — appointments (explicit create vs update, status enum, overlap check), attendance (`PUT` with explicit bool, `ON DUPLICATE KEY`), class/student delete cascades.
11. **L7–L11** — plans: transactional student resolution, `week_date` validation, event cleanup on delete, template apply target week, week-detail filter by plan.
12. **H8 + H9 + H11** — rebuild upload path (PSR-7 `UploadedFileInterface`, consistent keys, POST for multipart), add download endpoint, `realpath` guard in `Storage::delete` (L21).
13. **H12** — `national_id` → `CHAR(10)` + `LPAD` repair.
14. **L14–L16** — blog: `category_id` source of truth, `published_at` transition-only, boolean normalization, `published` alias.
15. **L12, L13, L22** — dashboard week math in `Asia/Tehran`, reports join parity, replace `JalaliHelper` with a tested library before anyone calls it.
16. **R2, R8** — delete or wire `config/*.php`, adopt `ResponseHelper`, commit `composer.lock`, run tests in CI, align openapi (contract table above).
17. Performance (N+1 batching, HTTP caching, indexes) after correctness; the earlier plan I gave you still applies.

Why this order: 1–4 stop active exposure with minimal code churn. 5 is a prerequisite for every DB-integrity change (otherwise constraint violations become undiagnosable 500s). 8 must precede 7 because you cannot apply schema repairs with a migrator that hides failures. Everything after is correctness work that can ship incrementally.

---

## Audit coverage

**Directories inspected:** `public/`, `bootstrap/`, `routes/`, `config/`, `app/Core/` (all 13), `app/Helpers/`, `app/Modules/` (all 15), `database/migrations/` (all 5), `tests/`, `.github/workflows/`, root (`composer.json`, `migrate.php`, `openapi.yaml`, `ARCHITECTURE.md`, `IMPL_PLAN.md`).

**Modules traced end-to-end:** Auth (login/2FA/register/me/logout/cookie), Students (+analytics), Dashboard, Reports, Exams, WeeklyPlans (+templates), Topics, Remedial, Appointments, Blog, Public, Courses, Instructors, Supporters, Files.

**Route groups:** all 87 routes cross-checked against openapi (1:1 path/method match) and ARCHITECTURE §7.

**Database areas:** all 5 migrations; 22 distinct tables referenced in SQL mapped to migration ownership; Migrator/migrate.php; PDO config.

**Frontend:** none in repo. Contract verified against `openapi.yaml` and both design docs only.

**Could not inspect:**
- Live database schema (17 tables have no DDL in repo): unique keys, FKs, column types (`status`, `exam_date`, `report_date`, `owner_id`), `sql_mode`, MySQL vs MariaDB. Several LIKELY/POSSIBLE ratings hinge on this.
- Deployed `.env` (JWT_SECRET, CORS_ORIGINS, UPLOADS_PATH, DB grants), web server config (docroot, `uploads/` alias, gzip).
- `vendor/` (slim/psr7 `withStatus` validation and gadget chains for H2 are based on library knowledge, not local source).
- The frontend zip (`v3.10.1.zip`) is binary and unreadable here.

**Assumptions:** production runs Slim PSR-7 (`slim/psr7 ^1.0`, which validates status codes); `ATTR_EMULATE_PREPARES=false` as configured; routes deployed as in `prod` branch; `students`/`users` are InnoDB at REPEATABLE READ.