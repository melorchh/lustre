# MedExpert v6 → Vercel Deployment-Ready Migration Plan

**Decisions locked (user):** Supabase (PostgreSQL) as the database · rotate both leaked secrets (Gmail App Password, Groq API key) · reminder-cron plan TBD → build a secret-protected HTTP endpoint that works on Hobby *and* Pro, document both triggers.

## ✅ Migration status (2026-09-23)

| Phase | Status | Artifacts |
|---|---|---|
| 1 — Vercel scaffolding | ✅ Done | `api/*.php` (66 files), `vercel.json`, `api/php.ini`, `.gitignore`, `.vercelignore`, `.env.example` |
| 2a — Schema | ✅ Done | `supabase/schema.sql` (14 tables, triggers, seeds) |
| 2b — DB layer | ✅ Done | `toddcare-backend/src/db.php` (PDO/pgsql, env + gitignored `env.local.php` fallback, Asia/Manila, generic 500) |
| 2c — mysqli shim | ✅ Done | `toddcare-backend/src/mysqli_compat.php` (`DbConn`/`DbResult`/`DbStmt`, sequence-aware `insert_id`) |
| 2d — Dialect pass | ✅ Done | `CURRENT_DATE`, `to_char`, `array_position`, `::int` casts, PG DDL, `GROUP BY` fixes, `DELETE` without `LIMIT` |
| 3 — DB sessions | ✅ Done | `toddcare-backend/src/session.php` + `session_start()` replaced in 61 files |
| 4 — Secrets to env | ✅ Done | `SMTPConfig.php` env-ized; `api/medbot.php` Groq proxy; Groq key removed from `landing.php` |
| 5 — Reminders HTTP | ✅ Done | `api/send_reminders.php` (Bearer `CRON_SECRET` or CLI), `(date + time)` comparison |
| 6 — filemtime | ✅ Done | All 77 `filemtime()` refs → `__DIR__ . '/../'` (root assets) |
| 7 — Hardening | ⏳ Final verify | `php -l` clean (72/72), no leaked keys, no MySQL-isms; deploy + smoke still pending (user env) |

Remaining before go-live (needs your environment): Supabase project + `supabase/schema.sql` run, Vercel env vars (§5), secret rotation, deployment + functional smoke (§6).

---

## 1. Current state (findings)

| Area | State | Vercel impact |
|---|---|---|
| Stack | ~70 flat PHP files at repo root, file-per-page routing, XAMPP/MySQL (`mysqli`, hardcoded `localhost/root/''` in `toddcare-backend/src/db.php`) | Needs function-per-file layout + external DB |
| Sessions | `session_start()` on line 2 of 61 files, default file-backed save handler | Incompatible with serverless → move to DB-backed handler |
| DB schema | `toddcare_database.sql`: 13 tables, MySQL `ENUM`/`AUTO_INCREMENT`/`ON UPDATE` idioms, seed data (4 doctors, admin, schedules) | Must be converted to Postgres DDL for Supabase |
| SQL dialect | `CURDATE()`, `DATE_FORMAT`, `TIME_FORMAT`, `FIELD(...)`, `DATE_ADD`, `DELETE … LIMIT 1`, `ON DUPLICATE KEY`, runtime `CREATE TABLE … AUTO_INCREMENT` in 5 files | Must be rewritten to Postgres equivalents |
| mysqli API surface (used, all OO) | `$conn->query/prepare/insert_id/error/close`; stmt `bind_param/execute/get_result/num_rows/affected_rows/insert_id/error/close`; result `fetch_assoc/num_rows/data_seek` | Covered by a small PDO-backed compatibility shim (no call-site edits) |
| Email | Raw-socket SMTP, **Gmail app password committed** in `SMTPConfig.php` | Move to env + rotate |
| AI chat | **Groq API key committed client-side** in `landing.php:1240` (visible to every visitor) | Move behind a server-side proxy + env + rotate |
| PDFs | 5 hand-rolled pure-PHP PDF writers, no libraries | Works as-is on serverless ✔ |
| Cron | `send_reminders.php` is CLI-only (Windows Task Scheduler/crontab), 45–75-min reminder window | Convert to HTTP endpoint w/ `CRON_SECRET` |
| Assets | CSS/JS at root, `images/`, committed React build in `client/dist/`; `filemtime('x')` cache-busting in ~77 places (unguarded in most) | Static files stay at root; `filemtime` paths must be fixed for `api/` layout |
| Composer | None — zero third-party PHP deps | No vendor step ✔ |
| Security | `.sql` dumps + `toddcare-backend/` source would be publicly served from repo root; `die(connect_error)` leaks credentials | `.vercelignore` + 404 route + generic errors |
| Runtime | PHP on Vercel = community `vercel-php` runtime (PHP 8.4/8.5), **includes `mysqli`, `pdo_pgsql`, `curl`, `openssl`, `sockets`, `session`** | Confirmed feasible |

---

## 2. Target architecture

```
Browser ── /login.php, /admin_*.php … (URLs unchanged)
   │
   ▼ (vercel.json route: /([^/]+\.php) → /api/$1)
Vercel  [vercel-php runtime]
   ├── api/*.php            ← all pages/endpoints (moved from root)
   ├── style.css, admin.css, images/, client/dist/…  ← static, served by CDN
   └── toddcare-backend/src/ ← shared includes (uploaded, blocked from HTTP by 404 route)
   │
   ├── PDO(pdo_pgsql) ──► Supabase Postgres (pooler :6543, sslmode=require)
   ├── DB-backed sessions (app_sessions table, same PDO connection)
   ├── SMTP (raw sockets) ──► Gmail SMTP (env credentials)
   ├── api/medbot.php ──► Groq API (env key, server-side only)
   └── api/send_reminders.php ◄── Vercel Cron (Pro) or cron-job.org (Hobby)
```

Local XAMPP dev keeps working with: `extension=pdo_pgsql` enabled + env vars pointing at the Supabase project (or any Postgres). MySQL is dropped as a target — the converted SQL is Postgres-only.

---

## 3. Workstreams

### Phase 1 — Vercel scaffolding (mechanical)

1. **Move all root `*.php` → `api/`** (~65 files). Leave at root: CSS/JS files, `images/`, `client/`, `toddcare-backend/`, `*.sql`, docs.
2. **Fix includes** in every moved file:
   `include __DIR__ . '/toddcare-backend/…'` → `include __DIR__ . '/../toddcare-backend/…'` (62 occurrences; same for the 3 `require` SMTP includes + 2 `schedule_sync.php` includes).
3. **Create `vercel.json`**:
   ```jsonc
   {
     "functions": { "api/*.php": { "runtime": "vercel-php@0.8.0", "memory": 1024, "maxDuration": 60 } },
     "regions": ["sin1"],   // match Supabase project region (recommend Singapore)
     "routes": [
       { "src": "/toddcare-backend/(.*)", "status": 404 },   // never serve shared-include source
       { "handle": "filesystem" },                            // css/js/images/client-dist first
       { "src": "/([^/]+\\.php)", "dest": "/api/$1" },        // all page URLs keep their shape
       { "src": "/", "dest": "/api/index.php" }
     ]
   }
   ```
   (PHP 8.4 / `vercel-php@0.8.0` for maturity; `0.9.0` = PHP 8.5 if preferred.)
4. **Create `api/php.ini`**: `memory_limit=512M`, `date.timezone=Asia/Manila`, `display_errors=Off`.
5. **Create `.vercelignore`**: `client/node_modules`, `*.sql`, `*.md`, `system_flowchart*`, `SYSTEM_FLOWCHART*`, `system_scope*`, `plans/`, `.vercel` — keeps DB dumps and docs out of the public deployment.
6. **Create `.gitignore`** (repo has no git yet): `node_modules/`, `.vercel/`, `toddcare-backend/src/env.local.php`.
7. **Routing sanity**: every internal link already uses explicit `*.php` URLs (relative) — verified, no clean-URL rewrites needed. Redirects like `Location: landing.php` resolve correctly.

### Phase 2 — Supabase Postgres migration (the core of the work)

**2a. Schema — create `supabase/schema.sql`** (run once in Supabase SQL editor), converted from `toddcare_database.sql`:

- Drop `CREATE DATABASE` / `USE`.
- `INT AUTO_INCREMENT PRIMARY KEY` → `INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY` (explicit-id seed inserts still work).
- **`ENUM(...)` → `TEXT` + `CHECK (col IN (...))`** for: `patient_type`, `gender`, `appointments.status/payment_status`, `lab_tests.priority/status`, `doctor_time_blocks.block_type`, `doctor_schedules.day_of_week`, `walk_ins.status`.
- **All flag columns → `SMALLINT DEFAULT 0/1`** (`is_walk_in`, `is_urgent`, `is_active`, `must_change_password`, `used`, …). *Critical:* keeps PHP seeing `'0'`/`'1'` exactly as MySQL returned — native PG `boolean` would surface `'t'`/`'f'` (truthy bug). Convert seed literals `TRUE/FALSE` → `1/0`.
- `TINYINT(1)`/`DATETIME` → `SMALLINT` / `TIMESTAMP`.
- `updated_at … ON UPDATE CURRENT_TIMESTAMP` → plain `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` + a small `BEFORE UPDATE` trigger for parity (only if code reads `updated_at`; verify during impl).
- Fold the legacy `__add_col__` procedure calls into the CREATE TABLEs + an idempotent `ALTER TABLE … ADD COLUMN IF NOT EXISTS` block (PG supports this natively) — adds missing `doctor_schedules.schedule_date`, `appointments.reschedule_count/last_rescheduled_at`.
- Seeds (doctors, admin `admin123` bcrypt hash, schedules): `ON DUPLICATE KEY UPDATE x = x` → `ON CONFLICT … DO NOTHING`.
- **Add `app_sessions`** table (Phase 3) and `email_tokens` (currently auto-created by OTP scripts).
- All 13 tables + the 12 existing indexes + FKs.

**2b. DB layer — rewrite `toddcare-backend/src/db.php`:**
- Config from env: `DB_HOST` (Supabase pooler host), `DB_PORT` (6543), `DB_USER` (`postgres.<ref>`), `DB_PASSWORD`, `DB_NAME` (Supabase default: `postgres`), `DB_SSLMODE=require`. Optional gitignored `env.local.php` overrides for XAMPP.
- Connect: `new PDO("pgsql:host=…;port=…;dbname=…", user, pass, [ERRMODE_SILENT, EMULATE_PREPARES=>false, default fetch assoc])`.
- Set `Asia/Manila` timezone (keep). Guard so double-include is a no-op (session bootstrap will pre-load it).
- **Failure handling:** replace `die(connect_error)` (credential leak) with `error_log(full detail)` + generic `http_response_code(500)` message.

**2c. mysqli compatibility shim — new `toddcare-backend/src/mysqli_compat.php`:**

Backs the **exact** API surface the 60+ files already use, so no call sites change:

| Class | Members (per grep inventory) |
|---|---|
| `DbConn` (returned as `$conn`) | `query($sql)`, `prepare($sql)`, `insert_id`, `error`, `connect_error`, `close()`, `real_escape_string()` (defensive) |
| `DbResult` | `fetch_assoc()`, `num_rows`, `data_seek($i)` — rows **buffered** with `fetchAll` (lists are small) |
| `DbStmt` | `bind_param($types, …&$vars)` (variadic, by-ref; casts `i`→int, `d`→float), `execute()`, `get_result()`, `num_rows` (buffered at execute), `affected_rows` (PDO `rowCount()`), `insert_id` (`SELECT lastval()` after `INSERT`), `error`, `close()` |

- Error mode: **report-off semantics** (return `false` + populate `->error`) — matches the existing `"Failed: " . $stmt->error` / `die($conn->error)` patterns.
- `db.php` instantiates `DbConn`; `$conn` keeps its name → 60+ files untouched at the API level.

**2d. SQL dialect conversion — edit queries file-by-file (~40 files):**

| MySQL | Postgres | Where |
|---|---|---|
| `CURDATE()` | `CURRENT_DATE` | admin_dashboard:11,68,71, admin_appointments:12, admin_vitals:41, book_appointment:95, doctor_result_action:46,54, doctor_dashboard:18,40, + audit |
| `NOW()` | `NOW()` ✔ (no change) | many |
| `DATE_ADD(NOW(), INTERVAL 15 MINUTE)` | `NOW() + INTERVAL '15 minutes'` | admin_login_process:66, doctor_login_process (same pattern — verify) |
| `TIME_FORMAT(x,'%H:%i')` | `to_char(x,'HH24:MI')` | admin_dashboard:31,40 |
| `DATE_FORMAT(d,'%Y-%m-01')` / `('%Y-%m')` | `to_char(d,'YYYY-MM')`, month-start via `date_trunc('month',d)::date` | admin_reports:75-76 |
| `DATE(DATE_SUB(d, INTERVAL (WEEKDAY(d)) DAY))` (Monday-week bucket) | `date_trunc('week', d)::date` (PG weeks start Monday) | admin_reports:70-71 |
| `ORDER BY FIELD(day_of_week,'Monday',…)` | `ORDER BY array_position(ARRAY['Monday',…]::text[], day_of_week)` | admin_schedules:7, doctor_schedule:9 |
| `DELETE … WHERE id = ? LIMIT 1` (LIMIT redundant on PK) | drop the `LIMIT 1` | admin_schedule_action:126,217, lab_action:123 |
| `CONCAT(date,' ',time)` compare (reminders) | `appointment_date + appointment_time` (timestamp math) | send_reminders:39 — read exact query in impl |
| Runtime `CREATE TABLE … INT AUTO_INCREMENT …` | PG DDL (`SERIAL`/identity, `TIMESTAMP`, `SMALLINT`) | admin_doctor_actions:7, admin_doctors:8, doctor_login_process:6, register_otp_send:6, forgot_password_send_otp:6 |
| Interpolated literals (`WHERE id=$id`) | audit for string interpolation → cast to `(int)` or bind | admin_vitals:27-41, admin_schedule_action:51, admin_login_process:51-78 |
| `GROUP BY p.id` with `p.*` selected | ✔ legal in PG (grouping by PK) | admin_patients:7, admin_doctors:17 — no change |
| `qc($conn, …)` helper | define/verify where it lives; body uses `fetch_assoc` → shim covers | doctor_dashboard:18 |

**Verification greps after this phase (must all return zero):** `mysqli_`, `CURDATE|DATE_FORMAT|TIME_FORMAT|DATE_ADD|WEEKDAY\(|FIELD\(|AUTO_INCREMENT|ON DUPLICATE`, `DELETE FROM \w+ WHERE.*LIMIT`.

### Phase 3 — DB-backed sessions (61 files, one-line change each)

1. New `toddcare-backend/src/session.php`:
   - `require` guarded `db.php` (opens PDO early);
   - `CREATE TABLE IF NOT EXISTS app_sessions (session_id TEXT PRIMARY KEY, payload TEXT, last_activity TIMESTAMPTZ DEFAULT now())` (lazy, mirrors the app’s existing runtime-DDL style);
   - `session_set_save_handler()` over PDO (`read/write/destroy/gc` — gc: probabilistic DELETE + hard purge in the reminders job);
   - cookie params: `samesite=Lax`, `httponly=true`, `secure` when `VERCEL=1` or HTTPS (so XAMPP HTTP still works);
   - `session_start()`.
2. In all 61 moved files, replace `session_start();` (line 2) with `require __DIR__ . '/../toddcare-backend/src/session.php';` (mechanical, scriptable).
3. Logout pages keep their `unset()` behavior (unchanged).

### Phase 4 — Secrets to env + rotation (user rotates, we relocate)

1. **`SMTPConfig.php`** → read `SMTP_HOST/PORT/USERNAME/PASSWORD/FROM_EMAIL/FROM_NAME` via `getenv()`; no credentials in repo. User revokes the exposed app password (`eutxeqhsattumjhy`) and creates a new one → Vercel env.
2. **Groq key in `landing.php`** → delete the client-side key + system prompt. New **`api/medbot.php`**: server-side calls `https://api.groq.com/...` with `GROQ_API_KEY` + `GROQ_MODEL` env; enforces same-origin (`Origin`/`Referer` check) + per-session rate limit (e.g. 20 req/10 min, DB or session counter) so the proxy can’t be farmed. `landing.php` JS changes to `fetch('/medbot.php', …)` and no longer ships any secret.
3. **`.env.example`** at root documenting every variable (see §5).
4. User-side: rotate Gmail app password, rotate/regenerate Groq key (old one is public — must be revoked in the Groq console).

### Phase 5 — Reminders → HTTP endpoint (works on any plan)

1. Move `send_reminders.php` → `api/send_reminders.php`; replace the `php_sapi_name() !== 'cli'` hard-exit with an auth gate:
   - allow CLI (local testing), **or** `Authorization: Bearer ${CRON_SECRET}` (Vercel Cron sends this automatically when `CRON_SECRET` env is set), **or** `x-vercel-cron` header (Vercel’s own cron user-agent marker) — reject everything else with 401.
2. Convert its SQL per Phase 2d; add session-GC purge (`DELETE FROM app_sessions WHERE last_activity < now() - interval '1 day'`) as a second step.
3. **Do not add `crons` to `vercel.json` by default** (an unknown/Hobby plan rejects sub-daily expressions and would fail the deploy). Document both triggers in the runbook (§6):
   - **Pro:** add `"crons": [{ "path": "/api/send_reminders.php", "schedule": "*/5 * * * *" }]` + `CRON_SECRET` env;
   - **Hobby/other:** free external scheduler (e.g. cron-job.org) hitting `https://<app>.vercel.app/api/send_reminders.php` every 5 minutes with the Bearer header.

### Phase 6 — Asset cache-busting (`filemtime`) fixes

- Unguarded `filemtime('admin.css')`-style calls (~30 sites) → guarded root-relative: `is_file(__DIR__ . '/../admin.css') ? filemtime(__DIR__ . '/../admin.css') : 1`.
- Already-guarded pattern `is_file(__DIR__ . '/' . $asset_css)` (client-dist refs, ~15 sites) → change prefix to `__DIR__ . '/../'` so versioning works when files are present, else falls back to `?v=0`.
- All relative HTML asset URLs (`admin.css`, `images/…`, `client/dist/…`) keep working: they’re served by the `handle: filesystem` route from the root.

### Phase 7 — Hardening pass

- 404 route for `/toddcare-backend/*` (Phase 1) + `.vercelignore` for dumps/docs (Phase 1) → no source/schema disclosure.
- Generic DB error page (Phase 2b).
- Grep-audit for any remaining literals: `gsk_`, `eutxeqhsattumjhy`, `localhost`, `root` credentials, `session_start()`.

---

## 4. What does NOT change

- Public URLs (`/login.php`, `/admin_dashboard.php`, …) — rewrites keep them identical.
- All HTML/CSS/JS/UI, the React bundle in `client/dist/` (committed build stays; no build step required on Vercel), hand-rolled PDF generators, raw-socket SMTP transport, bcrypt password hashing, file-per-page PHP structure, `Asia/Manila` timezone.

---

## 5. Environment variables (Vercel Project → Settings → Environment Variables)

| Var | Purpose |
|---|---|
| `DB_HOST` / `DB_PORT` / `DB_USER` / `DB_PASSWORD` / `DB_NAME` / `DB_SSLMODE` | Supabase pooler (`<ref>.pooler.supabase.com`, `6543`, `postgres.<ref>`, `<DB password>`, `postgres`, `require`) |
| `SMTP_HOST` `SMTP_PORT` `SMTP_USERNAME` `SMTP_PASSWORD` `SMTP_FROM` `SMTP_FROM_NAME` | Gmail SMTP (rotated app password) |
| `GROQ_API_KEY` `GROQ_MODEL` | AI chatbot (rotated key, server-side only) |
| `CRON_SECRET` | Bearer token guarding `/api/send_reminders.php` |

Local XAMPP: enable `extension=pdo_pgsql` in `php.ini`, restart Apache, put the same values in gitignored `toddcare-backend/env.local.php` (or point local dev straight at Supabase).

---

## 6. Verification plan

1. **Lint:** `php -l` every file in `api/` + `toddcare-backend/src/` (via `D:\xampp\php\php.exe`).
2. **Static audits:** the zero-match greps listed at the end of Phase 2d + Phase 7.
3. **Local functional smoke (XAMPP + Supabase DB):** landing → register/OTP email → login (patient/admin/doctor) → book / reschedule / cancel appointment → walk-in, vitals, vaccinations → lab request/result → schedules + time blocks → all 5 PDFs → password change → reminders endpoint via CLI.
4. **Deploy preview:** `vercel` (CLI) or push to a GitHub repo connected to Vercel → verify `/` routing, static assets, sessions persisting across requests (login survives multiple page loads — proves DB sessions), outbound DB/SMTP/Groq from the function, cron endpoint 401 without token / 200 with token.
5. **Secret checks:** view `landing.php` source & `SMTPConfig.php` on the deployed URL — no keys present.

## 7. User-run setup (cannot be done from here)

1. Create **Supabase** project (region: Southeast Asia/Singapore) → SQL Editor → run `supabase/schema.sql`.
2. *(Optional)* Move existing XAMPP data: `mysqldump` → `pgloader` (or CSV export/import) into Supabase.
3. Create **Vercel** account/project; connect repo (or `vercel` CLI deploy); set env vars (§5).
4. **Rotate secrets:** new Gmail App Password; revoke + reissue the Groq key.
5. Choose reminder trigger (§5 of Phase 5): Pro → add `crons` entry; otherwise → external scheduler.
6. Smoke-test the production URL (§6.4).

## 8. Risks & notes

- **Biggest change by far:** MySQL→Postgres (schema + ~40 files of SQL). The mysqli shim intentionally absorbs the PHP-API layer so effort goes into dialect correctness, verified by lint + greps + functional smoke.
- `vercel-php` is a community runtime (Vercel-recommended, 1.5k★). Pinned version; PHP 8.4.
- Supabase free tier: pooler connection limits are fine for this traffic; always use the pooler (`:6543`), not the direct `:5432`, from serverless.
- Supabase free projects pause after inactivity — reawaken before demoing.
- Hobby vs Pro only affects the reminder cadence option; nothing else in the codebase depends on the plan.
