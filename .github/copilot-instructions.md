# ClassLink - Copilot Instructions

Room & material reservation system (Reserva de Salas e Materiais). PHP 8.2+ / MariaDB, Bootstrap 5.3.3, Font Awesome 4.7. OAuth2 (league/oauth2-client), PHPMailer 7.1.1, TCPDF.

## Project Structure
```
src/              # Core: db.php (schema migration + connection), config.php, config.sample.php
func/             # Helpers: session_config, validation, csrf, email_helper, logaction, genuuid, get_config, navbar
login/            # Single-file auth (OAuth2 + OTP + TOTP + multi-DB selector)
reservar/         # Create reservations (index.php, manage.php)
reservas/         # View user's reservations
admin/            # Dashboard, users, salas, materiais, tempos, pedidos, config, registos, reservaemmassa, emailnotification
admin/api/        # AJAX: dashboard_stats, api_registos, salas_search, users_search, tempos_search, recipients_preview, requisitor_lookup, sala_lookup, tempo_lookup
admin/scripts/    # Batch scripts moved to admin/; only example.php and custom/ remain
assets/           # theme.css, navbar.css, index.css, reservar.css, docs.css, banner.css, theme-switcher.js, tooltips.js, disable-double-submit.js, manuals (PDF), CSV samples
```

## DB Schema
| Table | Key columns |
|-------|-------------|
| `cache` | id, nome, email, admin, totp_secret, otp_code_hash, otp_expires |
| `salas` | tipo_sala (1=approval/2=auto), bloqueado (0/1), post_reservation_content |
| `tempos` | id, horashumanos |
| `reservas` | sala, tempo, data, requisitor, aprovado (NULL=pending/1=approved/0=rejected/-1=cancelled), motivo, extra |
| `materiais` | id, nome, descricao, sala_id |
| `reservas_materiais` | junction: reserva_sala, reserva_tempo, reserva_data, material_id |
| `logs` | loginfo, userid, ip_address, timestamp |
| `config` | config_key, config_value (runtime app settings, JSON-encoded for complex types) |

## Key Constants (src/db.php)
- `PRE_REGISTERED_PREFIX = 'pre_'` — prefix for pre-registered user IDs
- `IS_FIRST_RUN` — true when cache table empty (skip certain checks)

## Session Variables
- `$_SESSION['id']`, `['nome']`, `['email']`, `['admin']`, `['validity']` (expiry timestamp)
- `$_SESSION['selected_db']` — multi-DB picker
- `$_SESSION['pending_user_setup']`, `['pending_totp_user']`, `['pending_totp_secret']` — incomplete auth state
- Session timeout: 30min (checked on every page: `$_SESSION['validity'] < time()` redirects to login)

## Helper Functions
| File | Functions |
|------|-----------|
| `func/validation.php` | `validate_uuid($uuid)`, `validate_date($date)`, `validate_action($action, $allowed_actions)`, `sanitize_input($input, $max_length)` |
| `func/csrf.php` | `generate_csrf_token()`, `verify_csrf_token($token)`, `csrf_token_field()` |
| `func/logaction.php` | `logaction($loginfo, $userid)`, `get_client_ip()` |
| `func/genuuid.php` | `uuid4()` |
| `func/email_helper.php` | `sendStyledEmail($to, $subj, $heading, $body, $type, $btnUrl, $btnText)` — types: success/warning/danger/info/primary; `getBaseUrl()`; `buildReservationDetailsHtml()`; `sendReservationCreatedEmail()`, `sendReservationApprovedEmail()`, `sendReservationRejectedEmail()`, `sendReservationDeletedEmail()`, `sendBulkReservationsEmail()`, `sendBulkReservationApprovedEmail()`, `sendBulkReservationRejectedEmail()`, `sendRecurringWeeklyReservationsEmail()` |
| `func/get_config.php` | `get_app_config($key, $default)` — loads from `config` DB table with static cache |
| `func/session_config.php` | Secure session ini_set calls (strict mode, httponly, secure, SameSite=Lax) |
| `func/navbar.php` | Renders navbar HTML; shows dev-mode banner if `app_mode=development` in DB config |

## Reservation Workflow
1. User selects room+time on `/reservar`
2. `tipo_sala=1` → pending admin approval (aprovado=NULL); `tipo_sala=2` → auto-approved (aprovado=1)
3. `bloqueado=1` → admin-only reservations
4. Materials attached via `reservas_materiais` junction
5. Email sent via `sendStyledEmail()` on completion
6. Bulk CSV import available via `admin/reservaemmassa.php` with ID lookup modals
7. Bulk email notifications via `admin/emailnotification.php` (week-based, BCC, admin or reservation-user modes)

## Dependencies
```
phpmailer/phpmailer: ^7.1.1, league/oauth2-client: ^2.8, tecnickcom/tcpdf: ^6.7, pragmarx/google2fa: ^9.0
```

## Coding Rules
- Use `logaction()` for auditable admin actions (IP tracking built-in)
- Check `$_SESSION['admin']` before sensitive ops; prepared statements (`$db->prepare()`) for all queries
- Return JSON with `Content-Type: application/json` for API endpoints
- Validate with `sanitize_input()`, `validate_uuid()`, `validate_date()`; CSRF via `csrf_token_field()`
- XSS: `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` for all user output
- Email: check `$mail['ativado']` before sending
- DB migrations: add backwards-compatible `ALTER TABLE` checks in `src/db.php`
- Guard `session_start()` with `if (session_status() === PHP_SESSION_NONE)`
- Run `php -l` on modified PHP files before considering complete
- Prefer page-local CSS/JS fixes over global theme overrides for admin-only UI issues
- Validate `$_SERVER['HTTP_HOST']` format in URL generation to prevent host header injection
- Error handling: check `$db->connect_error`, use HTTP status codes (403, 404, 500)
- Config values: prefer `get_app_config($key)` over hardcoded strings for runtime-managed settings (brand_name, email_account_name, app_mode, etc.)
- Dev mode: `get_app_config('app_mode') === 'development'` triggers dev-mode banners and alternate DB handling

## Session Summary (2026-05-22)

- **Brief summary**: Upgraded PHPMailer 7.0.2→7.1.1 (dependabot PR #151, closed manually — upgrade done separately), merged main into dev via fast-forward, added pending-auth guards to all pages/APIs (#153), set LIMIT 10 default + search filters on all API endpoints (#155), added skeleton loading UX to registos (logs) page, updated copilot instructions.
- **Changes made**:
	- Switched to `dev` branch, fast-forward merged `main` (commit `701ce3d` — CSV mass import feature PR #152).
	- Upgraded `phpmailer/phpmailer` from `^7.0` to `^7.1.1` via composer; verified with `php -l` and runtime version check (`7.1.1`). Dependabot PR #151 was closed without merging since the upgrade was applied directly.
	- **#153 — Security**: Invalidated authenticated session vars on TOTP entry so valid sessions can't bypass TOTP; added `pending_totp_user`/`pending_user_setup` and session validity checks to all page auth guards and admin API endpoints (16 files).
	- **#155 — API**: Changed default LIMIT from 50→10 on `api_registos`, 20→10 on `salas_search`/`tempos_search`/`users_search`; added `q` search filter to `api_registos` (searches loginfo, nome, email, ip_address); added `total` count and `hasMore` fields for client-side pagination awareness; added `LIKE … ESCAPE` for safe wildcard handling.
	- Updated `admin/registos.php`: limit 50→10 inline with API default, added `renderSkeletonRows()` for skeleton loading placeholders on initial load.
	- Added skeleton shimmer CSS (`assets/theme.css`): `.skeleton`, `.skeleton-text`, `.skeleton-card`, `.skeleton-row`, `.skeleton-avatar` classes with animated shimmer gradient and dark-mode support.
	- Updated `.github/copilot-instructions.md` to reflect current project state (PHPMailer 7.1.1, new func files, new admin pages/APIs, coding rules, session summaries).
- **Rationale / notes**: PHPMailer 7.1.1 includes minor security fixes (strip breaks from properties, strict encoding validation, MessageDate validation) — no breaking changes for ClassLink. Pending-auth guards close a gap where an authenticated session could skip TOTP by navigating directly. API LIMIT defaults reduce payload sizes for better performance; search filters enable type-ahead in admin UIs.
- **Touched files**:
	- composer.json / composer.lock / vendor/phpmailer/ (PHPMailer upgrade)
	- 16 files across all page directories + admin/api/ (pending-auth guards, #153)
	- 6 admin/api/ files (LIMIT defaults + search filters, #155)
	- admin/registos.php (skeleton loading + limit align)
	- assets/theme.css (skeleton CSS)
	- .github/copilot-instructions.md
- **Verification**:
	- `php -l` on all modified PHP files — all pass.
	- Runtime: `PHPMailer\PHPMailer\PHPMailer::VERSION` returns `7.1.1`.
- **Git info**:
	- Branch: `dev` (2 commits ahead of origin/dev: `128952b` and `bd62fb4`).
	- Pending: user mass-email test to confirm PHPMailer 7.1.1 works end-to-end before pushing to origin.
	- Dependabot PR #151: closed (not merged).