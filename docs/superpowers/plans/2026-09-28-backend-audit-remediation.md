# Famo Backend Audit Remediation Plan

> **For agentic workers:** Execute this plan task-by-task. Keep each task independently reviewable and run its focused tests before moving on. Do not overwrite pre-existing working-tree changes.

**Goal:** Close confirmed security exposures, repair data integrity and API behavior, and establish reliable schema, testing, and deployment workflows for the Famo backend.

**Architecture:** Treat the audit as a backlog, not as an assertion that every finding remains present in this checkout. Reconfirm each finding against the target branch and deployed schema, then deliver in gated releases: contain security risks, make errors and migrations observable, repair data safely, fix domain behavior and contracts, and finally optimize. Preserve existing route and response contracts where possible; document intentional changes in OpenAPI.

**Tech Stack:** PHP 8.2+, Slim 4 / PSR-7, PDO/MySQL or MariaDB, PHPUnit 9, Composer, SQL migrations, GitHub Actions.

**Spec:** `Famo-Backend-Audit.md` (review together with the current branch and production schema snapshot; confirmed audit paths and line numbers refer to the audit's `prod` snapshot).

## Global Constraints

- Do not run integration tests against production or an unidentified database. The current `tests/bootstrap.php` loads `.env` and connects to `nadcot_famo`; test setup deletes rows.
- Do not apply data migrations until a production-equivalent schema snapshot, backup, restore test, and migration rehearsal exist.
- Support the deployed MySQL/MariaDB versions explicitly; do not infer live constraints, engines, column types, or SQL mode from the PHP code.
- Do not weaken the existing student ownership / `StudentScope` checks while changing role policies.
- Preserve compatibility with existing clients or version/document breaking contract changes in `openapi.yaml`.
- Retain and review pre-existing workspace changes. At plan creation, local changes already touched `.gitignore`, `app/Core/Database.php`, `app/Modules/Students/Student.php`, `composer.json`, `composer.lock`, `routes/api.php`, and added PHPUnit files/configuration.
- The available XAMPP CLI reports PHP 8.0.30 while `composer.json` requires PHP >=8.2. Use a matching PHP >=8.2 runtime for Composer dependency resolution and test execution.

---

## Current-state baseline and gates

This plan was checked against the working tree, not solely copied from the audit:

- Route protections have changed since the audited snapshot: student list/detail, protected blog reads, supporter detail, student status toggle, and Remedial routes now show role middleware. Keep these changes and add route-level tests; also inspect every route, since student analytics, course/instructor detail, exams, and files still need explicit policy/scope review.
- `Remedial::updateStudentTime()` currently performs the allowlist check before both SQL branches. Local Remedial tests include an invalid-field test for the INSERT branch. Preserve this remediation and prove it with a test that actually reaches that branch.
- `Student::findAll()` currently clamps its SQL page size; some other services pass raw `$perPage` to models despite clamped metadata. Fix all remaining paths, not only the ones originally named in the audit.
- `database/migrations/006_preserve_national_id.sql` and current student parameter binding changes already exist locally. Verify their behavior and deployment readiness instead of recreating them.
- `Migrator::run()` currently rethrows migration failures and the root `migrate.php` has a CLI guard, contrary to the old audit snapshot. Remaining migration hazards include multi-statement/implicit-commit behavior, target-engine compatibility, repeatability, and migration rehearsal.
- Composer currently warns that the lock is stale and lacks `illuminate/database` and `illuminate/events`. Resolve with PHP >=8.2 and verify the intended dependency graph before CI relies on it.
- `git diff --check` passes and PHP 8.0 syntax checks passed for tracked and untracked PHP files. These checks do not replace execution on the declared PHP version. Do not run the current PHPUnit integration suite until its database is isolated and disposable.

## Release sequence

### Stage 0 — Establish a safe, reproducible baseline

**Files:** `composer.json`, `composer.lock`, `.github/workflows/php.yml`, `phpunit.xml.dist`, `tests/bootstrap.php`, `tests/TestCase.php`, `.env.example`, `Famo-Backend-Audit.md`.

- [ ] **0.1 Record the target baseline.** Select the actual release branch/commit. Capture a schema-only dump with `SHOW CREATE TABLE` for every table referenced in the audit; record database product/version, SQL mode, storage engines, indexes, constraints, collation, and relevant row counts. Do not copy credentials or data into the repository.
- [ ] **0.2 Make test configuration isolated.** Require an explicit test database name/prefix and fail fast unless `APP_ENV=testing`; add a documented disposable MySQL/MariaDB service for local/CI tests. Remove reliance on loading an arbitrary developer `.env` for destructive test setup.
- [ ] **0.3 Resolve PHP and Composer drift.** Run dependency validation/install with PHP >=8.2. Reconcile `composer.json` and lock entries for `illuminate/database`, `illuminate/events`, and PHPUnit; add a `composer test` script. Decide explicitly whether the GHSA ignore remains necessary and document its rationale or remove it.
- [ ] **0.4 Turn on CI tests.** Update `.github/workflows/php.yml` to install the declared PHP version, bring up the isolated database/schema fixture, run `composer validate --strict`, `composer install`, unit/integration tests, and migration checks. Use least-privilege test DB credentials.
- [ ] **0.5 Add a route-policy baseline test.** Create a data-driven route matrix covering anonymous, student, supporter, and admin access for all 87 routes. Assert both status and that forbidden requests never invoke handlers. Encode the intended public allowlist and student-owned-resource exceptions explicitly.

**Gate:** CI passes on the supported PHP and database versions; tests cannot connect to the production database by default; the route matrix exposes intended versus actual access.

### Stage 1 — Immediate security containment

**Files:** `app/Modules/Remedial/Remedial.php`, `app/Modules/Remedial/RemedialController.php`, `routes/api.php`, affected controllers/models under `Students`, `Supporters`, `Blog`, `Courses`, `Instructors`, `Topics`, `Exams`, and `Files`; corresponding tests.

- [ ] **1.1 Lock down all sensitive endpoints.** Use the Stage 0 route matrix to require supporter/admin middleware or `StudentScope` according to resource and operation. At minimum verify student analytics only resolve the authenticated student's own ID; admin writes remain admin-only; supporter reads cannot expose credentials/PII beyond business need; all Remedial writes are staff-only. Keep the already changed list, blog, supporter-detail, toggle-status, and Remedial route protections.
- [ ] **1.2 Remove sensitive fields from read payloads.** Replace `SELECT s.*` and broad supporter/user joins in list/detail endpoints with explicit response columns. Remove national IDs, phone numbers, usernames, chat IDs, hashes, and serialized internals unless the caller and endpoint specifically require them. Confirm public endpoints expose only published/active records.
- [ ] **1.3 Verify Remedial SQL identifier allowlisting.** Keep a fixed API-name-to-column map and validate before any read/update/insert branch. Map invalid input to 422. Add tests for each allowed field on existing-row and missing-row paths and an injection payload on the missing-row path; use a DB assertion to prove no injected statement ran. Replace silent catches with observable domain/infrastructure errors.
- [ ] **1.4 Fix status and active-account enforcement.** Require admin for `toggle-status`, scope its target, return only intended fields, and make login reject inactive students. Test student/supporter/admin access and inactive login behavior.
- [ ] **1.5 Remove predictable credentials.** Replace the fixed `1234` in student create-account/reset and supporter creation with cryptographically random, one-time credentials or a reset flow. Return plaintext only once to the authorized administrator. Add a persisted `must_change_password` state and authenticated change-password endpoint; force password change before protected access. Update OpenAPI and tests.
- [ ] **1.6 Add abuse controls.** Rate-limit login and registration by both IP and normalized username/phone, and throttle SMS code issuance. Use a shared store suitable for multiple application instances; return stable 429 responses and test window reset/expiry without timing-sensitive tests.
- [ ] **1.7 Harden 2FA.** Replace public `user_id + code` verification with a short-lived, single-use opaque challenge created by successful password verification. Invalidate prior codes, store a hash rather than plaintext where feasible, enforce expiry and bounded attempts, atomically consume the code with affected-row checking, and cap SMS sends. Test replay, brute-force limit, concurrent consume, expired challenge, and prior-code invalidation.
- [ ] **1.8 Harden JWT identity lifecycle.** Remove any fallback/default JWT secret (the current code already rejects an empty secret; inspect the sample env and boot path so production cannot use the sample value). Require a production secret at bootstrap with minimum length and deployment documentation. Add a revocation/version strategy and revalidate user role/active/deleted state for each token or short-lived cache; logout must invalidate bearer tokens as well as clear cookies. Reject unknown role values (default deny) and test demotion/deactivation/revocation.
- [ ] **1.9 Remove PHP object deserialization.** Store plan-template `items` as validated JSON, migrate existing serialized values with a safe one-time converter that does not instantiate classes, reject raw arbitrary `items_json`, and stop returning serialized storage values. Add compatibility tests for valid legacy scalar/array data and malicious object payloads.
- [ ] **1.10 Contain file path and upload abuse.** Canonicalize and constrain delete paths to the uploads root; validate size, MIME and file signature as well as extension; use restrictive directory permissions. Include traversal, symlink, MIME mismatch, oversized, and valid PSR-7 upload tests.

**Gate:** no student token can access staff endpoints or other students' records; dangerous identifiers/data are not public; no predictable account password, bypassable 2FA, arbitrary deserialization, or out-of-root file deletion remains.

### Stage 2 — Error semantics and migration reliability

**Files:** new domain/API exception type under `app/Core/`, `app/Core/ExceptionHandler.php`, all controller catch blocks, `app/Core/Migrator.php`, `migrate.php`, `database/migrations/*.sql`, migration tests.

- [ ] **2.1 Separate expected API errors from database failures.** Introduce a domain exception with an explicit HTTP status and stable public error code. Catch it centrally; let PDO/infrastructure exceptions reach logging and a generic 500 response. Never send SQL, driver, path, or stack details to clients. Log a correlation/request ID and exception details server-side.
- [ ] **2.2 Normalize error mapping.** Map validation to 422, missing resources to 404, authorization to 401/403, duplicate/constraint conflicts to 409, and unexpected DB errors to 500. Remove per-controller `withStatus($e->getCode())` on PDO codes, raw-SQL response messages, and error-to-404 behavior. Add tests for SQLSTATE strings, unique violations, connection errors, and domain errors.
- [ ] **2.3 Characterize supported DDL semantics.** In disposable MySQL and MariaDB instances, determine which migration statements auto-commit and whether the repository actually needs dynamic SQL or delimiter-aware splitting. Do not assume that wrapping arbitrary DDL in a transaction provides rollback.
- [ ] **2.4 Make migration failures fail closed.** Keep non-zero CLI exit on failure; report the exact migration and preserve the original exception internally. Record a migration only after every required statement completes. Add locking against concurrent migration runners and tests for repeat runs, injected failure, restart, and duplicate runner behavior.
- [ ] **2.5 Repair existing migration defects.** Review migrations 001–006 against both supported engines and real starting schemas. Execute the blog category FK creation correctly, remove hard-coded database names, replace unsupported conditional DDL with tested engine/version-aware logic, and ensure the `national_id` alteration preserves leading zeroes. Verify `006_preserve_national_id.sql` is safe on all existing column states and has a tested rollback/restore procedure.
- [ ] **2.6 Constrain migration execution.** Retain the CLI guard, run migrations with a dedicated least-privilege DB account, and verify public routing/web server configuration cannot execute the migration entry point.

**Gate:** injected DB failures produce a red deployment and a generic client response with diagnostic server logs; migration retries are understood and tested on each supported DB engine.

### Stage 3 — Pagination, schema ownership, and identity integrity

**Files:** pagination controllers/services/models, new `000_baseline.sql` or versioned schema migrations under `database/migrations/`, `AuthService`, `Student.php`, `StudentService.php`, `StudentScope.php`, migration/data-repair scripts.

- [ ] **3.1 Fix every pagination path.** Normalize input once and pass the normalized page/size from service to model. Search all list modules, including courses, instructors, appointments, exams, files, reports, supporters, blog, and students. Ensure each uses a consistent default/maximum and stable ordering; resolve the `perPage`/`per_page` contract deliberately. Test `-1`, `0`, over-limit, page beyond total, empty tables, and exam default behavior. Verify SQL row count never exceeds metadata's `per_page`.
- [ ] **3.2 Create schema ownership from reality.** Build a versioned baseline from the reviewed production-equivalent schema snapshot, not reconstructed guesses. Include all tables currently used by code and the intended keys, FKs, types, indexes, charset, and defaults. Add fresh-database tests that build the schema from zero and upgrade tests that start from each supported legacy schema.
- [ ] **3.3 Reconcile legacy IDs and foreign references.** Before adding PK/unique/FK constraints, inventory duplicates/orphans and produce a reviewed repair report. Plan backup, dry run, batched repair, row-count checks, and rollback/restore. Fix migration 005 ID renumbering references (`users.linked_id`, events, exams, files, and every other student reference) before deployment.
- [ ] **3.4 Remove `MAX(id)+1`.** After confirming `users` and `students` PK/AUTO_INCREMENT on real schemas, make all creation paths use database-generated identities. Add unique `users.username` and required national ID/phone constraints only after duplicate resolution; make user/student create, update, reset, and delete transactional.
- [ ] **3.5 Prevent student data inheritance and orphaning.** Choose and document soft-delete versus hard-delete policy. If hard delete, delete or archive all dependent rows/files atomically and ensure disk cleanup is recoverable. If soft delete, preserve original identity and make auth/scope/list queries consistently exclude inactive/deleted records. Add tests proving a later registration cannot read a deleted student's data.
- [ ] **3.6 Preserve identifiers as strings.** Validate the current `national_id` string migration with values containing leading zeroes, nulls, duplicates, and malformed legacy numbers. Backfill leading zeroes only when original width is known; do not fabricate digits for ambiguous rows. Ensure request/response/OpenAPI types all remain strings.

**Gate:** clean install and supported legacy upgrade both pass; uniqueness/FK checks pass without unaccounted orphan rows; registration is race-safe and deleted identities cannot leak data.

### Stage 4 — Transactional domain correctness

**Files:** `app/Modules/Exams/*`, `Appointments/*`, `Remedial/*`, `WeeklyPlans/*`, `Students/*`, `Blog/*`, `Reports/*`, `Dashboard/*`, relevant migrations and module tests.

- [ ] **4.1 Exams (L1–L2).** Validate request shape, date, non-negative counts, total bounds, and every row before writing. Make bulk save an atomic replace/upsert with a verified unique key so resubmission is idempotent; reject invalid rows rather than silently skipping them. Agree on the percentage/Konkur formula and zero-question behavior. Test rollback, duplicate resubmission, malformed arrays, negative values, and aggregate consistency.
- [ ] **4.2 Appointments (L3–L4).** Define whether booking is one appointment per student/date or multiple slots. Separate create from update semantics, preserve omitted fields, define status enum and allowed transitions, validate dates/status, and return correct 200/201. Add DB uniqueness/locking and overlap checks according to the agreed slot model. Send SMS only after commit through retryable background delivery or idempotency protection. Add deterministic date/status/student filters and stable sort keys.
- [ ] **4.3 Remedial attendance and lifecycle (C4, L5–L6, functional gaps).** Define attendance as explicit desired state (idempotent PUT) and enforce unique `(session_id, class_id, student_id)` rows. Validate session/class/enrollment relationships and required body fields. Make create-session enroll the intended snapshot of students transactionally; add readable enrollment/attendance/time data to session detail without N+1 queries; validate class fields. Cascade or explicitly delete attendance/times when class/student enrollment is removed. Test concurrent toggles, nonexistent resources, and rollback.
- [ ] **4.4 Weekly plans and templates (L7–L11).** Resolve/validate student identity in the same transaction as plan save; stop matching students by ambiguous name/grade and stop stale metadata renaming/relinking students. Normalize `week_date` to the agreed calendar-week start and reject invalid dates. Filter week detail by the requested plan/week. Delete dependent events with the plan. Make template application accept an explicit target week and preserve notes/times unless replace is explicitly requested and documented. Test failures do not create phantom students/plans and template application is non-destructive by default.
- [ ] **4.5 Blog consistency (L14–L17, A4).** Make `category_id` the source of truth and resolve category-slug filtering consistently. Normalize JSON booleans and support the documented `published` contract or update OpenAPI. Set `published_at` only on draft-to-published transition and define unpublish behavior. Ensure non-admin reads include only published posts; make slug uniqueness race-safe with a database constraint. Move view-count writes out of the hot read path or make them asynchronous/rate-limited.
- [ ] **4.6 Student/report/dashboard consistency (H1, L12–L13, L18–L20).** Validate all student updates and uniqueness, normalize username/phone consistently, and prevent duplicate account creation. Align report list/count joins and inclusive date ranges; define one pending-status representation. Compute dashboard weeks in `Asia/Tehran` with explicit inclusive/exclusive boundaries, exclude future exam dates, and confirm inactive-student and weighted-average semantics. Make multi-table account update/delete transactional.
- [ ] **4.7 Finish Remedial/API gaps with product-approved semantics.** Add missing read/update/delete operations only where the existing API requirements in `ARCHITECTURE.md`/`IMPL_PLAN.md` are still valid. Ensure every new route has role/scope policy, OpenAPI entry, and contract test. Do not expand scope based on unused helper methods alone.

**Gate:** retries do not duplicate or corrupt data; every multi-table workflow is transactional or explicitly eventual; destructive behavior is documented and covered by tests.

### Stage 5 — Uploads, contract, and dead infrastructure

**Files:** `app/Core/Storage.php`, `Courses/*`, `Instructors/*`, `Files/*`, `bootstrap/app.php`, `routes/api.php`, `openapi.yaml`, `config/*`, `app/Core/ResponseHelper.php`, `app/Helpers/JalaliHelper.php`, public model classes.

- [ ] **5.1 Repair course/instructor upload flow.** Use PSR-7 `UploadedFileInterface` end-to-end, consistent request keys, and a supported multipart method/path. Move files only after validation; use cleanup/compensation if the DB write fails. Test successful upload, failed move, failed insert, replacement, delete, and update with/without a file.
- [ ] **5.2 Add authenticated file download/streaming.** Resolve stored paths under the storage root, authorize owner/staff access, set safe content type/disposition and no-sniff/cache headers, and avoid exposing server paths. Test student ownership, staff access, traversal, missing file, and range behavior if supported.
- [ ] **5.3 Reconcile API contract.** Compare every OpenAPI operation/schema/parameter against routes and actual request/response fixtures. Fix pagination spelling, blog wrapper/publish fields, file shape, topic schema, report shape/status, student national ID/status, credential-reset response, 2FA mask/challenge, cookie auth, Remedial 403, error envelope/status, and create/update statuses. Mark intentional breaking changes and coordinate client migration.
- [ ] **5.4 Resolve duplicated public/admin models.** Establish one authoritative query/service per shared entity or document intentionally separate projections. Add published/active flags only after confirming schema and product requirements; until then ensure the public policy is explicit and cannot expose drafts or private supporter fields.
- [ ] **5.5 Decide dead-config and helper ownership.** Either load `config/app.php` and `config/database.php` through a single bootstrap path with tests, or remove them and document `.env` as authoritative. Adopt `ResponseHelper` only if it reduces inconsistent envelopes without forcing unrelated rewrites. Create required log directories safely at deployment.
- [ ] **5.6 Replace or remove Jalali conversion.** Before any caller is introduced, select a maintained, tested Jalali library or remove the unused helper. Verify known leap dates and round trips; document timezone and Gregorian/Jalali boundaries.

**Gate:** uploaded objects are usable through authorized APIs; OpenAPI examples match executable contract tests; no dead configuration source creates ambiguity.

### Stage 6 — Performance, observability, and release hardening

**Files:** public models/services, `Topic.php`, `RemedialService.php`, blog read flow, SQL migrations/indexes, middleware/bootstrap, CI/deployment docs.

- [ ] **6.1 Remove measured N+1 paths.** Batch public course features and instructor social links; batch remedial attendance/student/time data for session detail; add query-count tests/benchmarks for representative data sizes.
- [ ] **6.2 Bound tree traversal and query work.** Add cycle detection, maximum depth, and batched parent loading to topic paths. Escape LIKE wildcards when literal search is intended and add minimum search lengths where product behavior permits.
- [ ] **6.3 Add caching and indexes from plans.** Define cache headers/ETags for safe public immutable reads. Use the schema snapshot and `EXPLAIN` to add indexes for observed query predicates/sorts (blog publication/category, exam student/date, events plan/student, course display order, and others). Confirm migration write cost and rollback.
- [ ] **6.4 Improve operations telemetry.** Add request IDs, structured error logs, migration results, auth throttling counters, upload failure visibility, and health checks that distinguish process health from DB readiness without leaking internals.
- [ ] **6.5 Run release rehearsal.** Restore a production-like backup into a disposable environment, apply the complete migration chain, run data-integrity queries and all tests, smoke-test the 87-route policy/contract matrix, and rehearse rollback/restore. Deploy security containment first, then migration/data releases with monitoring and an explicit rollback owner.

**Gate:** measured hot paths improve without changing response semantics; a production-like restore and release rehearsal complete successfully.

## Audit finding coverage map

| Audit area | Plan coverage |
|---|---|
| C1 SQL injection; C2–C4 authorization, student directory, Remedial access | Stages 1.1–1.4; verify any already-present local route fixes |
| H1–H4 status/login, object injection, 2FA, JWT | Stages 1.4–1.9 |
| H5 exception handling; H13 migrator; R5 schema ownership | Stages 2.1–2.6 and 3.2 |
| H6 student repeated placeholder | Verify existing local Student query binding under Stage 0; retain query regression test |
| H7 orphaning/ID race; H12 national ID; L24 renumbering | Stages 3.3–3.6 |
| H8–H9 upload handling; H11 download; L21 path traversal; A8 upload checks | Stages 1.10 and 5.1–5.2 |
| H10 pagination | Stage 3.1 |
| L1–L6 exams, appointments, attendance/remedial integrity | Stages 4.1–4.3 |
| L7–L11 plans/templates/events/week detail | Stage 4.4 |
| L12–L13 dashboard/report date semantics | Stage 4.6 |
| L14–L17 blog categories/publication/views | Stage 4.5 |
| L18–L20 student validation, account transactionality, file orphaning | Stages 3.5 and 4.6, plus 5.1 |
| L22–L23 date conversion/topic traversal | Stages 5.6 and 6.2 |
| Functional/API gaps, A3–A7, R1–R8, contract table, low-priority debt | Stages 0.5, 1, 4.7, 5.3–5.5, and 6 |

## Plan self-review

- **Coverage:** Every audit section is assigned to one or more stages above; unknown live-schema assumptions have a discovery/migration gate rather than an invented schema fix.
- **Dependencies:** CI/test isolation precedes security/domain regression tests; security containment precedes credential rollout; exception taxonomy and a reliable migrator precede schema constraints/data repair; backup/snapshot and rehearsal precede production migrations.
- **Working tree:** Existing local changes are explicitly treated as user work to preserve and verify, not to overwrite.
- **Environment:** PHP 8.0 CLI and stale Composer lock are recorded as blockers to trustworthy execution; tests that can delete data are explicitly gated on a disposable DB.
