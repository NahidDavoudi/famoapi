# Student Parent Contacts Implementation Plan

> **For agentic workers:** Execute this plan task-by-task in the current isolated worktree. Steps use checkbox syntax for tracking.

**Goal:** Provide authenticated CRUD for multiple parent/guardian contacts using the existing `parent_contacts` table.

**Architecture:** Add a focused `ParentContacts` module with Model, Service, and Controller, wired into the existing Slim route registry. Keep data access in the model, student ownership and primary-contact rules in the service, validation and JSON response envelopes in the controller, and update schema only through a safe migration against the already-present table.

**Tech Stack:** PHP 8.2+, Slim 4, PDO/MySQL-compatible MariaDB, PHPUnit 9.5, OpenAPI 3.1.

**Spec:** `docs/superpowers/specs/2026-09-30-parent-contacts-design.md`

## Global Constraints

- Use the existing `parent_contacts` table; do not create another contacts table.
- Keep route authorization: admin/supporter reads, admin writes.
- All API responses use `{success,data,pagination,error}`.
- Keep relationships to existing `students`; do not add a foreign key.
- Never alter existing user changes or unrelated behavior.

---

### Task 1: Add integration tests for parent contact routes

**Files:**
- Create: `tests/Modules/Students/ParentContactsTest.php`
- Modify: `tests/TestCase.php` only if reusable test helpers are strictly necessary.

**Interfaces:**
- Consumes: existing `$this->app`, `$this->db`, `adminRequest()`, `supporterRequest()`, and `studentRequest()` test helpers.
- Produces: route-level regression coverage for `/api/v1/students/{studentId}/parent-contacts` and nested `{id}` operations.

- [ ] **Step 1: Write failing tests** for empty and sorted contact listing, adding a first contact that defaults primary, creating a second non-primary contact, setting a primary with update, refusing cross-student IDs, deleting contact, and enforcing supporter-read/admin-write permissions.
- [ ] **Step 2: Run the focused PHPUnit test and confirm expected failures** because no parent contact routes are registered.
- [ ] **Step 3: Keep test fixtures isolated** by creating two uniquely named students and deleting created contact/student rows in `tearDown`; use a DB transaction only if the request lifecycle shares the same connection and no code commits independently.
- [ ] **Step 4: Re-run the focused test** and confirm failures remain feature-related rather than fixture or syntax errors.

### Task 2: Implement model and service persistence rules

**Files:**
- Create: `app/Modules/ParentContacts/ParentContact.php`
- Create: `app/Modules/ParentContacts/ParentContactService.php`
- Test: `tests/Modules/Students/ParentContactsTest.php`

**Interfaces:**
- `ParentContact::findAllForStudent(int $studentId): array`
- `ParentContact::findForStudent(int $studentId, int $id): ?array`
- `ParentContact::countForStudent(int $studentId): int`
- `ParentContact::create(int $studentId, array $data): int`
- `ParentContact::updateForStudent(int $studentId, int $id, array $data): int`
- `ParentContact::deleteForStudent(int $studentId, int $id): bool`
- `ParentContactService::{list,create,update,delete}` use `App\Core\ApiException` for 404/errors.

- [ ] **Step 1: Implement the model with parameterized SQL** targeting only existing fields; list ordered by `is_primary DESC, id ASC`; ensure update/delete SQL matches both contact ID and student ID.
- [ ] **Step 2: Implement the service student-existence check** by querying `students`, return 404 for unknown students/contact pairs, and expose list/create/update/delete methods.
- [ ] **Step 3: Implement transactional primary handling:** lock/clear the sibling primary rows when choosing a primary; first contact becomes primary when omitted; when an existing contact is updated, omitted `is_primary` remains unchanged; do not auto-promote on delete.
- [ ] **Step 4: Run focused tests** and confirm CRUD, scope checks, and primary uniqueness pass.

### Task 3: Implement controller, validation, and route authorization

**Files:**
- Create: `app/Modules/ParentContacts/ParentContactController.php`
- Modify: `routes/api.php`
- Test: `tests/Modules/Students/ParentContactsTest.php`

**Interfaces:**
- Controller methods `list(Request, Response, array $args)`, `create(Request, Response, array $args)`, `update(Request, Response, array $args)`, `delete(Request, Response, array $args)`.
- Routes instantiate `ParentContactController(new ParentContactService())`.

- [ ] **Step 1: Add validation tests** for missing/blank name, blank or >15-character phone, invalid relationship, non-boolean `is_primary`, and empty update body; assert 422 envelope.
- [ ] **Step 2: Register routes:** GET with `$requireSupporter` and `$authMiddleware`; POST/PUT/DELETE with `$requireAdmin` and `$authMiddleware`. Register fixed `/students/{id}/parent-contacts` paths alongside student routes.
- [ ] **Step 3: Implement JSON-envelope controller responses**; validate trimmed `parent_name`, non-empty phone max 15 chars, relationship enum `father|mother|guardian|other`, and boolean `is_primary`; return 201 on create and 200 on reads/updates/deletes.
- [ ] **Step 4: Run focused module tests** and confirm validation and access checks pass.

### Task 4: Add a safe migration for the existing table

**Files:**
- Create: `database/migrations/007_parent_contacts_indexes.sql`
- Test: `tests/Modules/Students/ParentContactsTest.php` or a focused migration test if migrator tests already exist.

**Interfaces:**
- Migration runs through existing `App\Core\Migrator`; no API interface changes.

- [ ] **Step 1: Inspect existing migration conventions and MariaDB 10.4 compatibility**; use information_schema checks and prepared statements for conditional DDL because ordinary SQL scripts do not support procedural `IF` blocks in the current migrator.
- [ ] **Step 2: Ensure no duplicate IDs exist before adding a primary key; if duplicate IDs are present, fail explicitly without deleting contact rows.**
- [ ] **Step 3: Conditionally add primary key to `id` only when absent, conditionally make it `AUTO_INCREMENT` while retaining its `INT` type, and conditionally add a non-unique index on `student_id`; do not create table, modify contact values, or add FK.
- [ ] **Step 4: Validate SQL statement splitting and migration syntax; run migration only against an explicitly configured test DB if available.**

### Task 5: Document API contract in OpenAPI

**Files:**
- Modify: `openapi.yaml`

**Interfaces:**
- Documents all four operations and schemas produced by Task 3.

- [ ] **Step 1: Add GET/POST/PUT/DELETE path entries** with the student and contact IDs, bearer auth, admin/supporter role description, body schemas, and 200/201/401/403/404/422 responses.
- [ ] **Step 2: Add reusable ParentContact schema** using database/API field names `id`, `student_id`, `parent_name`, `relationship`, `phone`, `is_primary`, `created_at`, `updated_at`.
- [ ] **Step 3: Parse/validate OpenAPI YAML** with an installed project tool if available; otherwise perform structural inspection and report lack of validator.

### Task 6: Verify all behavior and review changes

**Files:**
- Review: all files from Tasks 1–5.

- [ ] **Step 1: Run focused PHPUnit tests** using the discovered XAMPP PHP CLI and repository PHPUnit binary if present.
- [ ] **Step 2: Run full PHPUnit suite** using the same environment.
- [ ] **Step 3: Run PHP syntax checks** for each changed PHP file.
- [ ] **Step 4: Inspect `git diff --check`, `git status`, and full diff**; confirm no unrelated files or existing changes were introduced.
- [ ] **Step 5: Report tests and any environment limitations**; do not claim checks passed unless output confirms it.
