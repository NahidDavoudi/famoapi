# Telegram Login & Account Linking — Design Spec

**Date:** 2026-10-02
**Owner scope:** API (`v3.10.1/api`) + website login page (`v3.10.1/login`).
**Out of scope:** the Telegram bot host (`tel-bot.zip`) is owned by another agent. This spec only updates the bot contract docs so that agent can conform.

---

## 1. Goal

A user who arrives at the site login page from the Telegram bot must be able to:

1. **Register** a new student account and bind it to their Telegram account, or
2. **Log in** with an existing account (full login rules, including 2FA for supporters) and bind it.

After binding, the user is returned to the bot at `https://t.me/<bot>?start=linked`, and the bot treats the account as authenticated (linked).

### Non-goals

- No Telegram-only website session. The Telegram flow **binds** identity; it does not itself grant a site session for already-linked accounts.
- No `admin` linking.
- Registration remains **student-only**.
- No changes to the bot host source.

---

## 2. Actors and end-to-end flows

### 2.1 New student
1. Bot shows a fixed URL button to the site login page.
2. User opens the page, completes the Telegram Login Widget.
3. Page calls `POST /auth/telegram/verify` → `{linked:false, ticket, telegram}`.
4. User chooses "register", fills the form, page calls `POST /auth/telegram/register` with the ticket.
5. Server creates `users` + `students`, inserts `telegram_links (role=student)`, consumes the ticket, returns a token.
6. Page redirects to `bot_redirect_url`.

### 2.2 Existing student
1. Widget → `verify` → `{linked:false, ticket}`.
2. Page calls `POST /auth/telegram/link {ticket, username, password}`.
3. Server authenticates, inserts the link, consumes the ticket, returns a token.
4. Page redirects to `bot_redirect_url`.

### 2.3 Existing supporter
1. Widget → `verify` → `{linked:false, ticket}`.
2. Page calls `POST /auth/telegram/link {ticket, username, password}`.
3. Server authenticates, then (supporter requires 2FA) creates a bound challenge, sends the SMS code, and returns `202 {requires_2fa:true, challenge_id, phone_mask}` — **no token, no link yet**.
4. Page shows the 2FA form and calls `POST /auth/telegram/verify-2fa {ticket, challenge_id, code}`.
5. Server verifies the code against the server-bound challenge, inserts the link `(role=supporter)`, consumes ticket + challenge, returns a token.
6. Page redirects to `bot_redirect_url`.

### 2.4 Already linked
1. Widget → `verify` → `{linked:true, bot_redirect_url}`.
2. Page shows "already linked" and a return button. **No session/token is issued.**

---

## 3. Security model

### 3.1 Offline widget verification
`data_check_string` = all received fields except `hash`, sorted alphabetically, joined by `\n` as `key=value`.
`secret_key = SHA256(bot_token)` (raw 32 bytes).
`computed = HMAC_SHA256(data_check_string, secret_key)` hex; compare to `hash` with `hash_equals`.

The API **never calls Telegram**.

### 3.2 No bot token in the API environment
Store only the derived value `TELEGRAM_LOGIN_SECRET_KEY` = hex of `SHA256(bot_token)`. Documentation must show generation:

```bash
php -r "echo hash('sha256', '<BOT_TOKEN>');"
```

The API holds nothing that can control the bot.

### 3.3 Widget payload replay and freshness
- Reject when `auth_date` is older than `TELEGRAM_LOGIN_MAX_AGE` (default 300s).
- Consume each verified payload exactly once, keyed by `sha256(canonical payload)`, stored in `telegram_auth_nonces (kind='payload')`. A duplicate insert is rejected as replay.

### 3.4 Ticket separation from session JWTs
- Ticket signed with a **separate derived key**: `hash_hmac('sha256', 'famo.telegram.link.ticket.v1', JWT_SECRET)`.
- Ticket claims include `aud='tg_link'`, `purpose='tg_link'`, `jti`, `tg_user_id`, `tg_username`, `iat`, `exp`.
- Session JWTs are signed with `JWT_SECRET` and carry `sub/role/...` but no `purpose`.
- Consequence: a session JWT can never be decoded as a ticket and a ticket can never be decoded as a session JWT. **Both directions are covered by tests.**

### 3.5 Ticket single-use (mandatory)
- `jti` is recorded in `telegram_auth_nonces (kind='ticket')` at issuance.
- Consumption is an atomic conditional update at the exact moment the link row is created; a second consumption fails.
- TTL `TELEGRAM_AUTH_TICKET_TTL` (default 600s).
- The ticket is validated (signature / `aud` / `purpose` / TTL / not consumed) but **not consumed** at `/telegram/link` when a 2FA challenge is required; it is consumed at `/telegram/verify-2fa`.

### 3.6 No session from `verify`
`/telegram/verify` **never** returns a token for any role. This prevents a Telegram-only path from bypassing 2FA for already-linked supporters/admins.

### 3.7 2FA is never bypassed
- A supporter `/telegram/link` runs the same SMS challenge as the normal login.
- The link row exists only after a successful challenge.
- The SMS cap and attempt cap apply (see §5).

### 3.8 Check order (link / verify-2fa)
1. Throttle.
2. Ticket validity.
3. Credentials / 2FA.
4. Role gate.
5. **Then** `ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED`.
6. Create link.

Before successful authentication, all failures are the generic `AUTH_ERROR` / `2FA_ERROR`; no link state is revealed.

### 3.9 Logging
Never log tickets, widget payloads, hashes, passwords, or 2FA codes. The ticket is never placed in a URL; it travels in the request body only.

---

## 4. API contract

All endpoints are public (no JWT, no bot service key) and live under `/api/v1/auth`. All responses use the standard envelope `{success,data,pagination,error}`.

### 4.1 `POST /api/v1/auth/telegram/verify`
**Request** (widget payload): `id`, `auth_date`, `hash`, and optional `first_name`, `last_name`, `username`, `photo_url`.

**Response 200, not linked:**
```json
{ "linked": false, "ticket": "<signed ticket>",
  "telegram": { "id": 880000001, "username": "u", "first_name": "...", "last_name": "...", "photo_url": "..." } }
```

**Response 200, already linked:**
```json
{ "linked": true, "bot_redirect_url": "https://t.me/<bot>?start=linked" }
```

**Errors:** `TELEGRAM_AUTH_INVALID` (401), `TELEGRAM_REPLAY` (409), `RATE_LIMITED` (429), `VALIDATION_ERROR` (422).

### 4.2 `POST /api/v1/auth/telegram/register`
**Request:** `ticket` + existing register fields `phone, password, nationalId, grade, field, name`. Validation identical to `POST /auth/register`.

**Response 201:**
```json
{ "token": "<jwt>", "user": { "id": 1, "username": "09...", "role": "student", "student_id": 5 },
  "bot_redirect_url": "https://t.me/<bot>?start=linked" }
```

**Errors:** `VALIDATION_ERROR` (422), `REGISTRATION_ERROR` (409 duplicate phone), `TELEGRAM_TICKET_INVALID` (401), `ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED` (409, only after successful ticket validation), `RATE_LIMITED` (429).

### 4.3 `POST /api/v1/auth/telegram/link`
**Request:** `ticket`, `username`, `password`.

**Response 200 (student):**
```json
{ "token": "<jwt>", "user": { "id": 1, "username": "09...", "role": "student", "student_id": 5 },
  "bot_redirect_url": "https://t.me/<bot>?start=linked" }
```

**Response 202 (supporter, 2FA required):**
```json
{ "requires_2fa": true, "challenge_id": "<nonce>", "phone_mask": "0912***89" }
```

**Errors:** `AUTH_ERROR` (401 generic), `TELEGRAM_TICKET_INVALID` (401), `TELEGRAM_LINK_ROLE_UNSUPPORTED` (403, role not student/supporter), `ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED` (409, only after authentication), `RATE_LIMITED` (429).

### 4.4 `POST /api/v1/auth/telegram/verify-2fa`
**Request:** `ticket`, `challenge_id`, `code`.

`user_id` is **not** accepted as authority. If a client sends `user_id`, it must match the server-bound challenge user or the request is rejected.

**Response 200:**
```json
{ "token": "<jwt>", "user": { "id": 2, "username": "09...", "role": "supporter" },
  "bot_redirect_url": "https://t.me/<bot>?start=linked" }
```

**Errors:** `2FA_ERROR` (401 invalid/expired), `TELEGRAM_TICKET_INVALID` (401), `ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED` (409, after successful 2FA), `RATE_LIMITED` (429).

### 4.5 Shared fields
- `bot_redirect_url = TELEGRAM_BOT_URL . '?start=linked'` (null if `TELEGRAM_BOT_URL` is unset; the page then falls back to the role panel).
- `chat_id = telegram_user_id` (private-chat assumption). Documented explicitly.

### 4.6 Error codes added
| Code | HTTP | Meaning |
|------|------|---------|
| `TELEGRAM_AUTH_INVALID` | 401 | Widget hash invalid, or `auth_date` stale |
| `TELEGRAM_REPLAY` | 409 | Verified payload already consumed |
| `TELEGRAM_TICKET_INVALID` | 401 | Ticket signature/audience/purpose/TTL/consumption failed |
| `TELEGRAM_LINK_ROLE_UNSUPPORTED` | 403 | Role is not `student`/`supporter` |
| `RATE_LIMITED` | 429 | Throttled; includes `Retry-After` |

---

## 5. Throttling and client IP (approved scope change)

This is a **known pre-existing gap**: normal login currently has no throttling. Condition requires that `/auth/login` and `/auth/verify-2fa` also be throttled. Normal success responses must stay byte-identical; only throttled requests gain `429 RATE_LIMITED` + `Retry-After`.

### 5.1 Rate limiter
- New `app/Core/RateLimiter.php`, DB-backed on `auth_throttle` (fixed window + lockout).
- API: `hit(key, max, windowSeconds): bool` / `availableIn(key): int` / `clear(key)`.
- On limit: `429 RATE_LIMITED`, `Retry-After: <seconds>`, generic Persian message.

### 5.2 Keys (no victim-lockout DoS)
- `login:user_ip:{sha1(username|ip)}` — hard lockout, `AUTH_LOGIN_MAX_ATTEMPTS` (default 5) / `AUTH_LOGIN_WINDOW_SECONDS` (900), lockout `AUTH_LOGIN_LOCKOUT_SECONDS` (900). **A successful login clears this key.**
- `login:ip:{ip}` — higher limit, `AUTH_LOGIN_IP_MAX_ATTEMPTS` (default 30) / window.
- `login:user:{sha1(username)}` — **soft** high limit, `AUTH_LOGIN_USER_SOFT_MAX` (default 20) / window; returns 429 only, never a permanent lock.
- Telegram endpoints: `tg:verify:ip:{ip}`, `tg:verify:tg:{tgUserId}`, `tg:register:ip:{ip}`, `tg:register:tg:{tgUserId}`, `tg:link:ip:{ip}` (and the login keys on `tg:link`).

### 5.3 Client IP
- New `app/Core/ClientIp.php`.
- Use `REMOTE_ADDR` by default.
- Only when `REMOTE_ADDR` is within `TRUSTED_PROXIES` (comma-separated CIDRs) read `TRUSTED_PROXY_HEADER` (e.g. `CF-Connecting-IP`, `X-Forwarded-For`, `X-Real-IP`); take the first non-trusted hop.
- **Fail open:** if the IP cannot be determined, log once and skip IP-keyed throttling (never lock everyone).
- Deployment doc: how to verify the detected IP (compare against known CDN ranges).

### 5.4 2FA hardening
- `2fa:chal:{challengeId}` — attempts cap `AUTH_2FA_MAX_ATTEMPTS` (default 5); challenge expires after `AUTH_2FA_CHALLENGE_TTL` (default 300s).
- `sms:phone:{sha1(phone)}` — `AUTH_SMS_PHONE_HOURLY_MAX` (default 5) per hour.
- `sms:ip:{ip}` — `AUTH_SMS_IP_HOURLY_MAX` (default 20) per hour.
- Applies to both the normal login 2FA send path and the Telegram flow.

### 5.5 Challenge binding
`telegram_2fa_challenges(challenge_nonce UNIQUE, ticket_jti, user_id, attempts, expires_at, consumed_at, created_at)`.

- Created at `/telegram/link` for supporters; `challenge_id` returned (never `user_id`).
- `/telegram/verify-2fa` loads by `challenge_id`, requires `ticket_jti` equality with the presented ticket, enforces expiry + attempt cap + single use, and derives the target user **server-side**.

---

## 6. Data model

New migration `database/migrations/013_telegram_login_auth.sql`:

```sql
CREATE TABLE IF NOT EXISTS telegram_auth_nonces (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  kind ENUM('payload','ticket') NOT NULL,
  nonce VARCHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_kind_nonce (kind, nonce),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS auth_throttle (
  throttle_key VARCHAR(191) NOT NULL PRIMARY KEY,
  attempts INT NOT NULL DEFAULT 0,
  window_started_at DATETIME NOT NULL,
  locked_until DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;

CREATE TABLE IF NOT EXISTS telegram_2fa_challenges (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  challenge_nonce VARCHAR(64) NOT NULL,
  ticket_jti VARCHAR(64) NOT NULL,
  user_id INT NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_challenge_nonce (challenge_nonce),
  KEY idx_ticket_jti (ticket_jti),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci;
```

`telegram_links` is unchanged. Its role-scoped unique keys already allow one Telegram account to hold both a student and a supporter link.

---

## 7. Environment variables

### API
| Var | Default | Purpose |
|-----|---------|---------|
| `TELEGRAM_LOGIN_SECRET_KEY` | — | hex `SHA256(bot_token)`; required |
| `TELEGRAM_LOGIN_MAX_AGE` | `300` | max `auth_date` age (s) |
| `TELEGRAM_AUTH_TICKET_TTL` | `600` | ticket lifetime (s) |
| `TELEGRAM_BOT_URL` | — | e.g. `https://t.me/<bot>`; builds `bot_redirect_url` |
| `TRUSTED_PROXIES` | — | comma-separated CIDRs |
| `TRUSTED_PROXY_HEADER` | — | header to read behind a trusted proxy |
| `AUTH_LOGIN_MAX_ATTEMPTS` | `5` | (username+IP) attempts |
| `AUTH_LOGIN_WINDOW_SECONDS` | `900` | window |
| `AUTH_LOGIN_LOCKOUT_SECONDS` | `900` | lockout |
| `AUTH_LOGIN_IP_MAX_ATTEMPTS` | `30` | per-IP attempts |
| `AUTH_LOGIN_USER_SOFT_MAX` | `20` | per-username soft cap |
| `AUTH_2FA_MAX_ATTEMPTS` | `5` | per challenge |
| `AUTH_2FA_CHALLENGE_TTL` | `300` | challenge lifetime (s) |
| `AUTH_SMS_PHONE_HOURLY_MAX` | `5` | SMS sends per phone/hour |
| `AUTH_SMS_IP_HOURLY_MAX` | `20` | SMS sends per IP/hour |
| `AUTH_TELEGRAM_VERIFY_PER_IP` | `30` | verify per IP/min |
| `AUTH_TELEGRAM_VERIFY_PER_TG` | `10` | verify per tg id/min |
| `AUTH_TELEGRAM_REGISTER_PER_IP` | `10` | register per IP/hour |
| `AUTH_TELEGRAM_REGISTER_PER_TG` | `10` | register per tg id/hour |

### Login page
| Var | Purpose |
|-----|---------|
| `TELEGRAM_BOT_USERNAME` | widget `data-telegram-login`; exposed as `APP_CONFIG.telegramBotUsername` |

---

## 8. API code changes

### New
- `app/Core/ClientIp.php`
- `app/Core/RateLimiter.php`
- `app/Modules/Auth/TelegramTicket.php` (issue/validate/consume; separate key + `aud`/`purpose`/`jti`)
- `app/Modules/Auth/TelegramLoginVerifier.php` (widget data-check-string + HMAC)
- `app/Modules/Auth/TelegramAuthService.php` (orchestration)
- `app/Modules/Auth/TelegramAuthController.php` (verify/register/link/verify-2fa)

### Changed
- `app/Core/Auth.php` — add derived-key encode/decode helpers for tickets (session path unchanged).
- `app/Modules/Auth/AuthService.php` — extract `createStudentAccount(array): array{userId,studentId}`, `sessionForUser(array): array`, and `consume2faCode(int $userId, string $code): array` (used by both normal and Telegram flows).
- `app/Modules/Linking/LinkService.php` — extract `linkVerified(role, accountId, tgUserId, chatId): array` reused by the Telegram flow.
- `app/Modules/Auth/AuthController.php` — apply throttling to login/verify-2fa; add `429` + `Retry-After`; SMS caps. Success responses unchanged.
- `routes/api.php` — register the four new public routes.
- `.env`, `.env.example` — new vars.
- `openapi.yaml` — Telegram Login section, error codes, env notes.
- `docs/bot-host-api-contract.md` — see §9.

---

## 9. Bot contract documentation (no bot code)

`docs/bot-host-api-contract.md` gains a Telegram Login section documenting:
- A fixed URL button to the site login page (`BOT_LOGIN_URL`), replacing any bot-generated link.
- Handling `start=linked` to confirm the binding.
- Outbox sends to a bound account fail with the equivalent of `403` until the user opens the bot; the bot treats it like blocked and unblocks on `/start`.
- BotFather `/setdomain` and HTTPS are required for the widget.
- `oauth.telegram.org` may be unreachable in Iran without a VPN (desktop especially).
- The phone-based `identity/lookup` and `identity/link` (`contact_verified`) endpoints are marked **not used** by the bot going forward and are not extended.
- The supporter `phone` column/backfill from migration 008 is now unnecessary for this flow; it is left untouched (no reversal).

---

## 10. Website login page changes

- `index.php`: widget container + `telegram-widget.js` (`data-telegram-login` from config, `data-onauth`).
- `assets/js/login.js`: verify handler; ticket-bound chooser ("login existing" vs "register"); 2FA form reuse; redirect to `bot_redirect_url`; friendly Persian `RATE_LIMITED` message; widget-load-failure fallback message (VPN/filter check + retry).
- `assets/js/api.js`: `telegramVerify/telegramRegister/telegramLink/telegramVerify2fa`.
- `config.php` + `.env.example`: `TELEGRAM_BOT_USERNAME` in `APP_CONFIG`.
- Minimal CSS for the widget/chooser.
- Any CSP must allow `telegram.org` (`script-src`).

---

## 11. Testing

`tests/Modules/Auth/TelegramAuthTest.php` (plus existing suite green):
- widget hash valid / invalid; stale `auth_date`.
- payload replay rejected.
- `verify` issues no token for any role.
- unlinked verify returns a ticket; linked verify returns `linked:true` + redirect.
- register success creates `students` + `users` + `telegram_links`, consumes ticket; duplicate phone.
- student link success; supporter requires 2FA then links; unsupported role rejected.
- ticket reuse rejected.
- ticket not accepted as session JWT; session JWT not accepted as ticket.
- throttle by (username+IP) does not lock a different IP; success resets the counter.
- IP detection behind a trusted proxy header.
- 2FA attempt cap; SMS send cap.
- `verify-2fa` rejects a mismatched `user_id`.
- conflict errors (`ALREADY_LINKED_ROLE` / `ACCOUNT_ALREADY_LINKED`) are **not** returned before successful authentication.
- normal login success responses unchanged.

Run `vendor/bin/phpunit` (full suite) and syntax checks.

---

## 12. Risks and notes

- Private-chat assumption `chat_id = telegram_user_id`; if a chat id ever differs, outbox delivery fails until corrected.
- The widget requires HTTPS and a BotFather-registered domain; not testable on plain localhost.
- SMS cost abuse is bounded by the per-phone and per-IP caps.
- If `TRUSTED_PROXIES` is misconfigured, IP keys degrade to the proxy address; the limiter fails open by design to avoid a site-wide lockout, and deployment docs describe verification.
