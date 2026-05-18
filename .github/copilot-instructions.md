# ClassLink - Copilot Instructions

Room & material reservation system (Reserva de Salas e Materiais). PHP 8.2+ / MariaDB, Bootstrap 5.3.3, Font Awesome 4.7. OAuth2 (league/oauth2-client), PHPMailer 7.0, TCPDF.

## Project Structure
```
src/              # Core: db.php (schema migration + connection), config.php
func/             # Helpers: session_config, validation, csrf, email_helper, logaction, genuuid, showbanner
login/            # Single-file auth (OAuth2 + OTP + TOTP + multi-DB selector)
reservar/         # Create reservations (index.php, manage.php)
reservas/         # View user's reservations
admin/            # Dashboard, users, salas, materiais, tempos, pedidos, config, registos
admin/api/        # AJAX: dashboard_stats, api_registos, salas_search, users_search, tempos_search, recipients_preview
admin/scripts/    # Batch: relatoriosaladiario (PDF), notifyemail, semanasrepetidas
assets/           # theme.css, navbar.css, index.css, reservar.css, docs.css, banner.css
```

## DB Schema
| Table | Key columns |
|-------|-------------|
| `cache` | id, nome, email, admin |
| `salas` | tipo_sala (1=approval/2=auto), bloqueado (0/1), post_reservation_content |
| `tempos` | id, horashumanos |
| `reservas` | sala, tempo, data, requisitor, aprovado (NULL=pending/1=approved/0=rejected/-1=cancelled), motivo, extra |
| `materiais` | id, nome, descricao, sala_id |
| `reservas_materiais` | junction: reserva_id, material_id |
| `logs` | loginfo, userid, ip_address, timestamp |

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
| `func/validation.php` | `validate_uuid($uuid)`, `validate_date($date)`, `validate_action($action, $array)`, `sanitize_input($input, $length)` |
| `func/csrf.php` | `generate_csrf_token()`, `verify_csrf_token($token)`, `csrf_token_field()` |
| `func/logaction.php` | `logaction($loginfo, $userid)`, `get_client_ip()` |
| `func/genuuid.php` | `uuid4()` |
| `func/email_helper.php` | `sendStyledEmail($to, $subj, $heading, $body, $type, $btnUrl, $btnText)` — types: success/warning/danger/info/primary; `getBaseUrl()` |
| `func/get_config.php` | Configuration loader |

## Reservation Workflow
1. User selects room+time on `/reservar`
2. `tipo_sala=1` → pending admin approval (aprovado=NULL); `tipo_sala=2` → auto-approved (aprovado=1)
3. `bloqueado=1` → admin-only reservations
4. Materials attached via `reservas_materiais` junction
5. Email sent via `sendStyledEmail()` on completion

## Dependencies
```
phpmailer/phpmailer: ^7.0, league/oauth2-client: ^2.8, tecnickcom/tcpdf: ^6.7, sonata-project/google-authenticator: ^2.3
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

## Session Summary (2026-05-17)

- **Brief summary**: Implemented Issue #135 to use DB-configured sender name for outgoing emails and updated related config sample.
- **Changes made**:
	- Updated `func/email_helper.php` to prefer `email_account_name` from DB for `From` name.
	- Updated `admin/scripts/notifyemail.php` to use DB-configured sender name.
	- Removed `fromname` from `src/config.sample.php`.
- **Rationale / notes**: Sender identity should be managed in the application DB (`config.email_account_name`) to allow runtime updates by admins; code falls back to legacy `src/config.php` value when DB key is absent for backward compatibility.
- **Touched files**:
	- func/email_helper.php
	- admin/scripts/notifyemail.php
	- src/config.sample.php
- **Verification**:
	- Ran `php -l` against modified PHP files (`func/email_helper.php`, `admin/scripts/notifyemail.php`) to ensure no syntax errors.
- **Git info**:
	- Branch: `dev` (changes prepared on feature branch `fix/email-sender-db-name` and pushed). Commit message used when adding this note: "docs: add 2026-05-17 session summary to copilot instructions"
