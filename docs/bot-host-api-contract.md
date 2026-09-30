# Famo Telegram Bot — API Contract for the Bot Host

**Version:** 1.0 · **Base path:** `/api/v1` · **Audience:** the external PHP bot
host. This document is self-contained; you do not need to read the API source.

The main host (`api.famoacademy.ir`) **cannot reach Telegram**. This API is the
source of truth for data and rules and **never calls Telegram**. The bot host
talks to Telegram and uses this API to store/fetch everything and to pull/send
queued messages.

---

## 1. Authentication

All bot endpoints live under `/api/v1/bot/*` and use a **service key** —
completely separate from user JWT.

- Header: `X-Bot-Key: <BOT_SERVICE_KEY>`
- Optional IP allow-list (`BOT_IP_ALLOWLIST`, comma-separated); when set, the
  caller IP must match or the request is rejected with `403 BOT_IP_FORBIDDEN`.

**Acting-on-behalf headers** (required for endpoints that operate as a user):

| Header | Value |
|--------|-------|
| `X-Bot-Role` | `student` or `supporter` |
| `X-Telegram-User-Id` | Telegram numeric user id |
| `X-Telegram-Chat-Id` | Telegram numeric chat id (must match the stored link) |

The API resolves the acting account from its own stored link and re-checks
role/active/blocked **server-side on every request**. Never assume the header
alone grants access.

### Response envelope

Success:
```json
{ "success": true, "data": { }, "pagination": null, "error": null }
```
Error:
```json
{ "success": false, "data": null, "pagination": null,
  "error": { "code": "BOT_UNAUTHORIZED", "message": "..." } }
```

### Conventions
- Dates shown to users are **Jalali**; every date object includes the Gregorian
  ISO date (`day`, `YYYY-MM-DD`) plus `day_jalali` (`YYYY/MM/DD`).
- Days are **Asia/Tehran** calendar days (00:00–24:00); weeks run
  **Saturday → Friday**.
- Timestamps are UTC (`Y-m-d H:i:s`).
- Attachments are Telegram references only: `{kind, tg_file_id, file_name,
  mime_type, file_size}`; `kind ∈ {photo, document, voice, video, audio}`. Max 10
  attachments per message. Multi-photo albums: send all attachments in one call
  and include `media_group_id`.

---

## 2. Gateway

### `GET /bot/ping`
Service key only.
```json
{ "status": "ok", "utc_time": "2026-09-30 12:00:00",
  "iran_date": "2026-09-30", "iran_date_jalali": "1405/07/08" }
```

### `GET /bot/me`  *(acting)*
Returns the resolved acting account.
```json
{ "role": "student", "account_id": 20, "name": "...",
  "telegram_user_id": 880000001, "chat_id": 880000001,
  "is_blocked": false, "linked_at": "2026-09-30 10:00:00" }
```

---

## 3. Identity & Linking

### `POST /bot/identity/lookup`
Find all roles for a phone. Accepts Persian/Arabic digits and
`+98/98/0098/09/9` forms.
```json
// request
{ "phone": "۰۹۱۲۳۴۵۶۷۸۹", "telegram_user_id": 880000001 }
// data
{ "phone": "09123456789",
  "identities": [
    { "role": "student", "account_id": 20, "name": "...", "is_active": true,
      "is_linked": false, "linked_to_this_telegram": false, "linked_to_other_telegram": false }
  ] }
```
Return all roles so the user can choose which to use.

### `POST /bot/identity/link`
The bot host must have **already verified** the shared contact belongs to the
sender (`contact_verified: true`); the API only stores the link.
```json
{ "telegram_user_id": 880000001, "chat_id": 880000001,
  "role": "student", "account_id": 20, "contact_verified": true }
// data
{ "link_id": 1, "role": "student", "account_id": 20, "name": "...",
  "is_active": true, "is_blocked": false,
  "telegram_user_id": 880000001, "chat_id": 880000001, "linked_at": "..." }
```
Rules: one link per `(telegram_user, role)` and per `(role, account)`.
Re-linking the same pair is idempotent.

### `GET /bot/identity/resolve?telegram_user_id=..&role=..`
or `?chat_id=..`. Returns matching link(s) with account + status.
```json
{ "links": [ { "link_id": 1, "role": "student", "account_id": 20,
  "name": "...", "is_active": true, "is_blocked": false,
  "telegram_user_id": 880000001, "chat_id": 880000001, "linked_at": "..." } ] }
```

### `POST /bot/identity/unlink`
`{ "telegram_user_id": 880000001, "role": "student" }` →
`{ "unlinked": 1 }`. (Alternatively `{ "role", "account_id" }`.)

### `POST /bot/identity/block` / `POST /bot/identity/unblock`
`{ "telegram_user_id": 880000001, "role": "student" }` (or `{ "chat_id" }`)
→ `{ "blocked": true, "affected": 1 }`. A blocked link rejects acting requests
with `BOT_ACCOUNT_BLOCKED`.

---

## 4. Threads & Messages

One thread per (student, Tehran day). Student messages = the daily report.
Supporter and broadcast messages never count as a report.

### `POST /bot/threads/messages`  *(student acting)*
Send a message to **today's** thread.
```json
{ "text": "گزارش امروز", "media_group_id": null,
  "attachments": [ { "kind": "photo", "tg_file_id": "AgAD...",
    "file_name": "a.jpg", "mime_type": "image/jpeg", "file_size": 1234 } ] }
// 201 data
{ "id": 10, "thread_id": 3, "day": "2026-09-30", "day_jalali": "1405/07/08",
  "sender_role": "student", "sender_account_id": 20, "body": "گزارش امروز",
  "media_group_id": null, "is_broadcast": false,
  "read_by_student": true, "read_by_supporter": false,
  "created_at": "2026-09-30 12:00:00",
  "attachments": [ { "id": 1, "kind": "photo", "tg_file_id": "AgAD...",
    "file_name": "a.jpg", "mime_type": "image/jpeg", "file_size": 1234 } ] }
```
Requires an active supporter assignment, otherwise `409 NO_SUPPORTER_ASSIGNED`.

### `GET /bot/threads/day?day=YYYY-MM-DD&page=1&perPage=50`  *(acting)*
Student: omit `student_id` (own threads). Supporter: `student_id` required.
```json
{ "student_id": 20, "day": "2026-09-30", "day_jalali": "1405/07/08",
  "weekday": "چهارشنبه", "thread_id": 3, "supporter_id": 2,
  "report_submitted": true, "unread_replies": 0, "unread_by_supporter": 2,
  "messages": [ /* message objects as above */ ],
  "pagination": { "page": 1, "per_page": 50, "total": 2, "total_pages": 1 } }
```

### `GET /bot/threads/weekly?week_start=YYYY-MM-DD`  *(acting)*
Student: own. Supporter: `student_id` required. Defaults to the current week.
```json
{ "student_id": 20, "week_start": "2026-09-26", "week_start_jalali": "1405/07/04",
  "days": [
    { "day": "2026-09-26", "day_jalali": "1405/07/04", "weekday": "شنبه",
      "state": "missed", "report_submitted": false,
      "has_reply": false, "reply_unread": false, "unread_replies": 0, "supporter_id": 2 }
  ] }
```
`state`: `sent` (has report) · `missed` (past day, no report) · `pending`
(today without report, or any future day). Future days are never `missed`.

### `POST /bot/threads/read`  *(acting)*
Mark messages read (explicit; the bot calls this when it displays them).
- Student: `{ "day": "2026-09-30" }` (or omit day for all) → marks supporter/
  broadcast messages `read_by_student`.
- Supporter: `{ "student_id": 20, "day": "..." }` → marks that student's
  messages `read_by_supporter`; omit `day` to mark all their unread messages.

→ `{ "marked": 2 }`

---

## 5. Supporter Inbox & Replies  *(supporter acting)*

### `GET /bot/supporter/inbox?page=1&perPage=20`
Students with unread student messages.
```json
{ "total_students": 3, "students": [
    { "student_id": 20, "student_name": "...", "grade": 12, "field": "ریاضی",
      "unread_count": 2, "last_message_at": "2026-09-30 12:00:00" } ],
  "pagination": { } }
```

### `GET /bot/supporter/students`
Assigned students + today's status.
```json
{ "total_students": 2, "day": "2026-09-30", "day_jalali": "1405/07/08",
  "students": [ { "student_id": 20, "name": "...", "grade": 12, "field": "ریاضی",
    "report_submitted": true, "has_reply": false, "unread_count": 2 } ] }
```

### `GET /bot/supporter/students/{studentId}/unread?limit=200`
Unread student messages for one student (oldest first), each with `day`.

### `POST /bot/supporter/reply`  *(supporter acting)*
```json
{ "student_id": 20, "text": "آفرین", "day": null,
  "attachments": [], "media_group_id": null }
```
If `day` is omitted, the reply goes to the day of the **latest unread student
message** (or today if none) — so the student sees it under the right day.
Returns `201` with the created message (as in §4).

---

## 6. Broadcasts  *(supporter acting, own students only)*

Audiences: `all_students` (all currently assigned) · `no_report_today`
(assigned students with no report on the target day).

### `POST /bot/broadcasts/preview`
```json
{ "audience": "no_report_today", "day": null }
// data
{ "audience": "no_report_today", "day": "2026-09-30", "day_jalali": "1405/07/08",
  "recipient_count": 5,
  "recipients": [ { "student_id": 20, "name": "...", "grade": 12, "field": "ریاضی" } ],
  "daily_limit": 200, "used_today": 3, "remaining_today": 197 }
```

### `POST /bot/broadcasts/confirm`
Freezes the recipient list now, creates one **broadcast** message per recipient
in their thread for the target day (labelled broadcast, unread), and one outbox
item per recipient.
```json
{ "audience": "all_students", "text": "یادآوری...", "attachments": [], "day": null }
// 201 data
{ "broadcast_id": 7, "audience": "all_students", "day": "2026-09-30",
  "recipient_count": 5, "enqueued": 5, "blocked": 0, "failed": 0,
  "summary": { "pending": 5, "sent": 0, "failed": 0, "blocked": 0 } }
```

### `GET /bot/broadcasts` / `GET /bot/broadcasts/{id}`
List campaigns / read one with per-recipient delivery status
(`pending|sent|failed|blocked`).

---

## 7. Outbox Pull/Report Protocol  *(bot worker; service key only)*

Everything destined for Telegram (report notifications, replies, broadcasts) is
queued in the outbox. The bot worker runs a loop:

### Step 1 — `POST /bot/outbox/claim`
```json
{ "limit": 10, "worker_id": "worker-1" }
// data
{ "worker_id": "worker-1", "items": [
    { "id": 42, "kind": "student_report", "recipient_role": "supporter",
      "recipient_account_id": 2, "telegram_user_id": 880000101, "chat_id": 880000101,
      "attempts": 1, "max_attempts": 3,
      "payload": { "kind": "student_report", "text": "گزارش امروز",
        "attachments": [ /* refs */ ],
        "meta": { "student_id": 20, "student_name": "...", "supporter_id": 2,
                  "day": "2026-09-30", "message_id": 10 } },
      "created_at": "2026-09-30 12:00:00" } ] }
```
- `worker_id` is optional; if omitted the API generates and returns one (use it
  for the matching report).
- Items are **locked** to the worker (`processing`); concurrent workers never
  receive the same item. `limit` is clamped to 1–50.
- Send `payload.text` to `chat_id`; attach each `payload.attachments` entry by
  `tg_file_id`. `payload.meta` carries context for formatting.
- `kind ∈ {student_report, supporter_reply, broadcast}`.

### Step 2 — `POST /bot/outbox/report`
```json
{ "worker_id": "worker-1",
  "results": [
    { "id": 42, "status": "sent", "telegram_message_id": "555" },
    { "id": 43, "status": "failed", "error": "chat not found" },
    { "id": 44, "status": "blocked" }
  ] }
// data
{ "processed": 3, "results": [
    { "id": 42, "result": "sent" },
    { "id": 43, "result": "retry", "attempts": 1, "next_attempt_in": 60 },
    { "id": 44, "result": "blocked" } ] }
```
Per-item `result`:
- `sent` — stored with the Telegram message id.
- `retry` — `status` may be `failed`; re-queued with backoff (`next_attempt_in`
  seconds); retried until `max_attempts`.
- `failed` — terminal after the attempt limit.
- `blocked` — the recipient blocked the bot; the Telegram link is marked blocked
  so **future sends to that account are skipped**.
- `ignored` — the item was not locked to this `worker_id`, or is not processing.

### Retries, dedup, stale locks
- `BOT_OUTBOX_MAX_ATTEMPTS` (default 3) — attempts before terminal failure.
- `BOT_OUTBOX_RETRY_BACKOFF` (default 60s) — `next_attempt_in = backoff × attempts`.
- `BOT_OUTBOX_LOCK_TTL` (default 300s) — a `processing` item whose lock is older
  than the TTL is returned to `pending` on the next claim (dead-worker recovery).
- Supporter notifications are **de-duplicated**: at most one
  `student_report` notification per (supporter, student) within
  `BOT_REPORT_NOTIFY_WINDOW` seconds (default 300). Other kinds are not deduped.

### Suggested worker loop
1. `claim(limit=10, worker_id=<stable id>)`
2. for each item: send to Telegram (text + attachments)
3. collect results; `report(worker_id, results)`
4. sleep ~1–2s; repeat. On claim errors (401), back off and alert.

---

## 8. Error codes

| Code | HTTP | Meaning |
|------|------|---------|
| `BOT_UNAUTHORIZED` | 401 | Missing/invalid `X-Bot-Key` |
| `BOT_IP_FORBIDDEN` | 403 | Caller IP not in allow-list |
| `BOT_NOT_CONFIGURED` | 500 | Server has no `BOT_SERVICE_KEY` configured |
| `BOT_ACTOR_MISSING` / `BOT_ACTOR_INVALID` | 400 | Acting headers absent/malformed |
| `BOT_ROLE_INVALID` | 400 | `X-Bot-Role` not student/supporter |
| `BOT_NOT_LINKED` | 401 | No link for (telegram user, role) |
| `BOT_CHAT_MISMATCH` | 401 | `X-Telegram-Chat-Id` ≠ stored link |
| `BOT_ACCOUNT_BLOCKED` | 403 | Link is blocked |
| `ACCOUNT_INACTIVE` | 403 | Account disabled |
| `FORBIDDEN` | 403 | Role/ownership not allowed |
| `CONTACT_NOT_VERIFIED` | 422 | `contact_verified` not true |
| `ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED` | 409 | Link uniqueness conflict |
| `INVALID_PHONE` | 422 | Phone not an Iranian mobile |
| `NO_SUPPORTER_ASSIGNED` | 409 | Student has no active supporter |
| `EMPTY_MESSAGE` | 422 | No text and no attachments |
| `INVALID_ATTACHMENT` / `TOO_MANY_ATTACHMENTS` | 422 | Bad/too many attachments |
| `INVALID_DAY` | 422 | Malformed date |
| `INVALID_AUDIENCE` | 422 | Bad broadcast audience |
| `NO_RECIPIENTS` | 422 | Broadcast audience empty |
| `BROADCAST_DAILY_LIMIT` | 429 | Supporter daily broadcast limit reached |
| `VALIDATION_ERROR` | 422 | Generic validation failure |
| `NOT_FOUND` | 404 | Resource not found |

---

## 9. Timezone rules

- All days are `Asia/Tehran`. The API returns both the Gregorian ISO date and
  `day_jalali`.
- When the bot computes "today" for display, prefer the API values; do not rely
  on the server's local timezone.

---

## 10. Out of scope for the bot host

Admin statistics (`/api/v1/admin/stats/*`) use the existing **admin JWT**, not
the service key. Reading message content requires a separate permission
(`ADMIN_CONTENT_READER_IDS`) and is disabled by default.

> Coexistence note: legacy tables (`reports_status`, `report_replies`,
> `report_attachments`, `bot_sessions`, `bot_ui_state`) are left untouched and
> are **not** used by this system. The Bot/Threads tables are the system of
> record.
