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

- **Brief summary**: Upgraded PHPMailer from 7.0.2 to 7.1.1 (dependabot PR #151), merged main into dev via fast-forward, updated copilot instructions to reflect current project state.
- **Changes made**:
	- Switched to `dev` branch, fast-forward merged `main` (commit `701ce3d` — CSV mass import feature PR #152).
	- Upgraded `phpmailer/phpmailer` from `^7.0` to `^7.1.1` via composer; verified with `php -l` and runtime version check.
	- Updated `.github/copilot-instructions.md`: PHPMailer 7.1.1, pragmarx/google2fa ^9.0, added config table, new func files (navbar, session_config, get_config), new admin pages (reservaemmassa, emailnotification), new API endpoints (requisitor_lookup, sala_lookup, tempo_lookup), expanded email_helper function list, added coding rules for get_app_config and dev mode, removed outdated batch scripts section, updated session summary.
- **Rationale / notes**: PHPMailer 7.1.1 includes minor security fixes (strip breaks from properties, strict encoding validation, MessageDate validation) — no breaking changes for ClassLink since all Encoding/CharSet values use lowercase/constants. Dependenabot PR #151 is mergeable-clean and can be closed after this upgrade is confirmed working via manual email test.
- **Touched files**:
	- composer.json (phpmailer constraint ^7.0 → ^7.1.1)
	- composer.lock (phpmailer v7.0.2 → v7.1.1)
	- vendor/phpmailer/phpmailer/ (upgraded package)
	- .github/copilot-instructions.md
- **Verification**:
	- `php -l` on func/email_helper.php, admin/emailnotification.php, src/config.php, src/config.sample.php — all pass.
	- Runtime: `PHPMailer\PHPMailer\PHPMailer::VERSION` returns `7.1.1`.
- **Git info**:
	- Branch: `dev`. Pending: user mass-email test to confirm PHPMailer 7.1.1 works end-to-end before closing dependabot PR #151.