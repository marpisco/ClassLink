# ClassLink - Copilot Instructions

## Project Overview
- **Purpose**: Digital platform for room and material reservation (Portuguese: Reserva de Salas e Materiais)
- **Language**: PHP with MariaDB database
- **Framework**: Bootstrap 5.3.3 + Font Awesome 4.7.0
- **Auth**: OAuth2 via League OAuth2 Client
- **Email**: PHPMailer 7.0
- **Main Developer**: Marco Pisco (PAP - Prova de Aptidão Profissional)

## Project Structure
```
ClassLink/
├── src/                    # Core files
│   ├── db.php             # Database initialization & schema
│   └── config.sample.php  # Configuration template
├── func/                  # Helper functions
│   ├── session_config.php # Secure session config (30min timeout)
│   ├── validation.php     # Input validation
│   ├── csrf.php          # CSRF protection
│   ├── email_helper.php  # Email functionality
│   ├── logaction.php     # Audit logging
│   ├── genuuid.php       # UUID generation
│   └── showbanner.php    # UI banner display
├── login/                # Authentication
├── reservar/             # Room reservation creation
├── reservas/             # View/manage reservations
├── admin/                # Admin panel
│   ├── api/              # AJAX endpoints
│   ├── users.php         # User management
│   ├── salas.php         # Room management
│   ├── materiais.php     # Material management
│   ├── tempos.php        # Time slot management
│   ├── pedidos.php       # Approval requests
│   └── scripts/          # Batch/scheduled tasks
├── assets/               # CSS, images, logos
└── index.php             # Main dashboard
```

## Database Schema

| Table | Purpose |
|-------|---------|
| `cache` | User cache (id, nome, email, admin flag) |
| `salas` | Rooms with columns: `tipo_sala` (1=approval, 2=autonomous), `bloqueado` (0/1 lock status), `post_reservation_content` |
| `tempos` | Time slots (id, horashumanos) |
| `reservas` | Reservations (sala, tempo, data, requisitor, aprovado status, motivo, extra) |
| `materiais` | Materials (id, nome, descricao, sala_id) |
| `reservas_materiais` | Junction table linking reservations to materials |
| `logs` | Audit logs (loginfo, userid, ip_address, timestamp) |

## Key Features
✅ Session management with secure headers (HttpOnly, SameSite=Lax, strong IDs)
✅ User approval workflow for room reservations
✅ Material inventory per room
✅ Admin dashboard with search APIs
✅ Audit logging with IP tracking
✅ Pre-registered user support (prefix: `pre_`)
✅ OAuth2 integration (likely for school/GIAE system)
✅ Post-reservation customizable content per room

## Security Features
- Session timeout: 30 minutes inactivity
- CSRF protection implemented
- XSS prevention (HTTPOnly cookies)
- Secure session cookies (HTTPS detection)
- Input validation functions
- Audit trail with IP logging

## Important Constants & Configuration
- `PRE_REGISTERED_PREFIX = 'pre_'` - For pre-registered users
- Session ID length: 48 characters
- Session ID bits per character: 6
- GC max lifetime: 1800 seconds (30 minutes)

## Database Initialization
The `src/db.php` file handles all table creation with automatic migration:
- Creates tables if they don't exist
- Adds new columns to existing tables for backward compatibility
- Uses UTF-8MB4 charset
- Enforces foreign key constraints with CASCADE delete

## Dependencies (Composer)
```json
{
    "require": {
        "php": ">=8.2",
        "phpmailer/phpmailer": "^7.0",
        "league/oauth2-client": "^2.8",
        "league/commonmark": "^2.8",
        "tecnickcom/tcpdf": "^6.7"
    }
}
```

## Deployment Requirements
- PHP + MariaDB
- Composer (for dependencies)
- UTF-8 charset support (mb4)
- HTTPS recommended (sets secure flag on cookies)

## Contributing Guidelines
See `CONTRIBUIDORES.md` for contribution guidelines:
- External contributors: Fork > Branch > Pull Request
- Internal contributors: Branch > Pull Request
- Requires code review before merge
- Credits tracked for PAP documentation

## Helper Functions Available

### Input Validation (`func/validation.php`)
```php
validate_uuid($uuid)              // Validates UUID v4 format
validate_date($date)              // Validates Y-m-d format
validate_action($action, $array)  // Whitelists action against array
sanitize_input($input, $length)   // Sanitizes/truncates input to max length
```

### CSRF Protection (`func/csrf.php`)
```php
generate_csrf_token()   // Create/return session CSRF token
verify_csrf_token($token) // Verify token matches session
csrf_token_field()      // Returns HTML hidden input with token
```

### Logging (`func/logaction.php`)
```php
logaction($loginfo, $userid)  // Log action with IP tracking
get_client_ip()               // Get client IP (handles proxies)
```

### UUID Generation (`func/genuuid.php`)
```php
uuid4()  // Generate UUID v4 cryptographically
```

### Email (`func/email_helper.php`)
```php
sendStyledEmail($to, $subject, $heading, $bodyContent, $type, $buttonUrl, $buttonText)
// Types: 'success', 'warning', 'danger', 'info', 'primary'
// Returns: ['success' => bool, 'error' => string|null]
getBaseUrl()  // Get application base URL with HTTPS detection
```

## Configuration File (`src/config.sample.php`)

Copy this to `src/config.php` and update with real values:

### Email Configuration
```php
$mail = [
    'ativado' => true,              // Enable/disable email
    'servidor' => 'smtp.gmail.com', // SMTP server
    'porta' => 465,                 // SMTP port (465 for SSL, 587 for STARTTLS)
    'autenticacao' => true,         // Enable SMTP auth
    'tipodeseguranca' => 'PHPMailer::ENCRYPTION_SMTPS', // or ENCRYPTION_STARTTLS
    'username' => '',               // SMTP username (usually email)
    'fromname' => 'Reserva de Salas', // From display name
    'mailfrom' => '',               // From email address
    'password' => ''                // SMTP password (use app-specific for Gmail)
];
```

### Database Configuration
```php
$db = [
    'tipo' => 'mysql',
    'servidor' => 'localhost',
    'user' => 'reservasalas',
    'password' => '***',     // STRONG password required
    'db' => 'reservasalas',
    'porta' => 3306
];
```

### OAuth 2.0 Configuration
```php
$provider = new GenericProvider([
    'urlAuthorize'            => 'https://...',
    'urlAccessToken'          => 'https://...',
    'urlResourceOwnerDetails' => 'https://...',
    'clientId'     => '***',      // From OAuth provider
    'clientSecret' => '***',      // KEEP SECRET
    'redirectUri'  => 'https://' . $_SERVER['HTTP_HOST'] . '/login'
]);
```

## Stylesheets
- `assets/theme.css` - Color scheme & theme variables (light/dark mode)
- `assets/index.css` - Main dashboard styles
- `assets/reservar.css` - Reservation page styles
- `assets/banner.css` - Banner component styles

## Admin API Endpoints

All located in `admin/api/`:
- **dashboard_stats.php** - Returns JSON stats (top reservers, reservations per room)
- **api_registos.php** - Logs management API
- **salas_search.php** - Room search/autocomplete
- **users_search.php** - User search/autocomplete
- **tempos_search.php** - Time slot search

## Reservation Workflow
1. User selects room(s) and time slot(s) on `/reservar`
2. If `tipo_sala=1`: Awaits admin approval (aprovado=NULL)
3. If `tipo_sala=2`: Auto-approved (aprovado=1)
4. If `bloqueado=1`: Only admins can create reservations
5. Materials can be attached via `reservas_materiais` junction table
6. Completion triggers email via `sendStyledEmail()`

## Admin Actions Checklist
✅ Always use `logaction()` for auditable changes
✅ Check `$_SESSION['admin']` before sensitive operations
✅ Return JSON with `Content-Type: application/json` for APIs
✅ Validate inputs with `sanitize_input()`, `validate_uuid()`, `validate_date()`
✅ Use prepared statements (`$db->prepare()`) to prevent SQL injection
✅ Include CSRF token in forms via `csrf_token_field()`
✅ Handle exceptions and return appropriate HTTP status codes (403, 404, 500)

## Important Notes for Future Development
1. **Database Migrations**: Always add backwards-compatible column checks in `src/db.php`
2. **Session Management**: Use `$_SESSION['validity']`, `$_SESSION['admin']`, `$_SESSION['nome']`
3. **User Identification**: Check for `PRE_REGISTERED_PREFIX` when handling user IDs
4. **Audit Logging**: All admin actions should use `logaction.php` - includes IP tracking
5. **Room Types**: `tipo_sala=1` (approval required), `tipo_sala=2` (auto-approved)
6. **Room Lock Status**: `bloqueado=0` (open), `bloqueado=1` (admin only)
7. **Reservation Status**: `aprovado=NULL` (pending), `aprovado=1` (approved), `aprovado=0` (rejected), `aprovado=-1` (cancelled)
8. **API Endpoints**: Located in `admin/api/` - return JSON for AJAX requests
9. **Error Handling**: Check `$db->connect_error` and use HTTP status codes
10. **Email Disabled Check**: Always check `$mail['ativado']` before sending emails
11. **XSS Prevention**: Use `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')` for all user output
12. **Host Header Injection**: Validate `$_SERVER['HTTP_HOST']` format in URL generation
13. **Validation Check**: Before finishing something or considering it complete, you should always validate to see if you have any parsing errors via a shell command (e.g. `php -l`).

## Progresso Atual (WIP: Add Markdown parser and web documentation viewer)
- Implementado o novo visualizador de Markdown (`/docs/index.php`) e configurado o parser Markdown (`func/markdown.php`) com suporte para tabelas fundidas via GFM (`||`).
- Adicionado plugin `HeadingPermalinkExtension` para que links com âncoras (ex: `#as-minhas-reservas`) funcionem perfeitamente.
- O componente da _navbar_ foi recriado e desanexado para um só ficheiro (`func/navbar.php`) incluído como `require_once` em todas as vistas principais.
- **Próximos passos (A Rever)**: A reestruturação da navbar em duas linhas ou o layout comprimido não ficou com boa aparência (apesar da cor de fundo da documentação ter sido ajustada e margens editadas). Amanhã será necessário redesenhar a navbar global do site para não parecer tão "bloated" nem desmanchar a interface.
