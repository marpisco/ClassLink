<?php
    require_once(__DIR__ . '/../func/session_config.php');
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once(__DIR__ . '/../func/csrf.php');
    require_once(__DIR__ . '/../func/rate_limit.php');

    // Handle database selection
    if (isset($_POST['action']) && $_POST['action'] === 'select_db') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Pedido inválido. Atualize a página e tente novamente.');
        }
        $selectedDb = $_POST['db_selection'] ?? null;
        if ($selectedDb) {
            $_SESSION['selected_db'] = $selectedDb;
        }
        header('Location: /login');
        exit();
    }

    // Include config and db normally
    require_once(__DIR__ . '/../src/config.php');

    // Store db config before it's overwritten by mysqli
    $dbConfig = $db['db'];
    $showDbPicker = is_array($dbConfig) && count($dbConfig) > 1;
    $dbOptions = is_array($dbConfig) ? $dbConfig : [];

    require_once(__DIR__ . '/../src/db.php');
    require_once(__DIR__ . '/../func/get_config.php');
    require_once(__DIR__ . '/../func/logaction.php');
    require_once(__DIR__ . '/../func/email_helper.php');

    $localAuthError = null;
    $localAuthInfo = null;
    $localAuthStage = 'email';
    $emailValue = '';
    $csrfTokenField = csrf_token_field();

    // --- Helper Functions for OTP/TOTP ---

    function is_admin_totp_required() {
        return filter_var(get_app_config('admin_requires_totp', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
    }

    // --- Login Page Template Helper ---
    function render_login_template($title, $content) {
        $particlesConfig = json_encode([
            "particles" => ["number" => ["value" => 60, "density" => ["enable" => true, "value_area" => 800]], "color" => ["value" => "#ffffff"], "shape" => ["type" => "circle"], "opacity" => ["value" => 0.6, "random" => false], "size" => ["value" => 3, "random" => true], "line_linked" => ["enable" => true, "distance" => 150, "color" => "#ffffff", "opacity" => 0.4, "width" => 1], "move" => ["enable" => true, "speed" => 2, "direction" => "none", "random" => false, "straight" => false, "out_mode" => "out", "bounce" => false]],
            "interactivity" => ["detect_on" => "canvas", "events" => ["onhover" => ["enable" => true, "mode" => "grab"], "onclick" => ["enable" => true, "mode" => "push"], "resize" => true], "modes" => ["grab" => ["distance" => 140, "line_linked" => ["opacity" => 1]], "push" => ["particles_nb" => 4]]],
            "retina_detect" => true
        ]);
        
        $appName = get_app_config("brand_name", "ClassLink");

        // Insert the app name footer once inside the login-box (before its closing </div>)
        if (strpos($content, 'app-name-footer') === false && strpos($content, '<div class="login-box"') !== false) {
            $pos = strrpos($content, '</div>');
            if ($pos !== false) {
                $footer = '<div class="app-name-footer">' . htmlspecialchars($appName) . '</div>';
                $content = substr_replace($content, $footer, $pos, 0);
            }
        }
        
        echo '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . $title . ' - ClassLink</title><link rel="stylesheet" href="/assets/theme.css"><script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script><style>body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background: var(--bg-gradient); display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; } #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; } .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); } .login-box h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: var(--text-color); } .login-box .small { color: var(--text-color); } .login-btn { display: inline-block; background-color: #24a1da; color: white; text-decoration: none; padding: 0.8rem 1.2rem; border-radius: 8px; font-size: 1rem; font-weight: 500; transition: background 0.2s; } .login-btn:hover { opacity: 0.9; } .form-group { margin-bottom: 1rem; } input { padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); width: 100%; box-sizing: border-box; } button { background-color: #24a1da; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; } button:hover { opacity: 0.9; } .divider { display: flex; align-items: center; margin: 1.5rem 0; color: var(--text-color); opacity: 0.6; } .divider::before, .divider::after { content: ""; flex: 1; border-bottom: 1px solid currentColor; } .divider:not(:empty)::before { margin-right: .5em; } .divider:not(:empty)::after { margin-left: .5em; } .info-msg { color: var(--text-color); background: rgba(255,255,255,0.1); padding: 0.75rem; margin-bottom: 1rem; border-radius: 8px; text-align: left; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.2); } .error-msg { color: #ff3333; font-size: 0.9rem; margin-bottom: 1rem; background: rgba(255,50,50,0.1); padding: 0.5rem; border-radius: 5px; } .ms-logo { vertical-align: middle; margin-right: 8px; } .notice { position: absolute; bottom: 20px; background: var(--white-overlay-light); padding: 1rem 1.5rem; border-radius: 10px; font-size: 0.9rem; box-shadow: 0 2px 8px var(--shadow-color); text-align: center; max-width: 400px; line-height: 1.4; color: var(--text-color); } .notice strong { display: block; margin-bottom: 4px; } .app-name-footer { margin-top: 1.5rem; color: var(--text-color); opacity: 0.6; font-size: 0.85rem; }</style></head><body><div id="particles-js"></div>' . $content . '<script>particlesJS("particles-js", ' . $particlesConfig . ');</script></body></html>';
    }

    // Note: Email domain restrictions are now handled via blocked_emails_regex

    function get_user_by_email($email) {
        global $db;
        $stmt = $db->prepare("SELECT id, nome, email, admin, totp_secret, otp_code_hash, otp_expires FROM cache WHERE email = ?");
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $user;
    }

    function create_pending_user($email) {
        global $db;

        // The first-user admin claim is only valid on a brand-new
        // installation. On an upgraded deployment, the cache table may
        // already contain users (with their own admin status) while the
        // first_user_admin_id config row has never been written. If we
        // race-conditioned purely on the config row, the first
        // previously-unknown email submitted through local auth would
        // win the claim and be silently promoted to admin — a privilege
        // escalation on the first day of the upgrade. The atomic claim
        // is only meaningful when there are no users yet.
        $userCountRow = $db->query("SELECT COUNT(*) AS c FROM cache")->fetch_assoc();
        $cacheIsEmpty = ((int)($userCountRow['c'] ?? 0)) === 0;

        $isFirstUser = false;
        if ($cacheIsEmpty) {
            // Race-safe first-user admin assignment. The first thread to
            // atomically claim the `first_user_admin_id` config row gets
            // admin; concurrent threads will see the existing value and
            // lose. This is enforced by MySQL's INSERT ... ON DUPLICATE
            // KEY UPDATE semantics — only one row can be inserted, all
            // other attempts update the existing row.
            $claimId = 'first_user_claim_' . bin2hex(random_bytes(8));
            $stmt = $db->prepare("INSERT INTO config (config_key, config_value) VALUES ('first_user_admin_id', ?) ON DUPLICATE KEY UPDATE config_value = config_value");
            if ($stmt) {
                $stmt->bind_param("s", $claimId);
                $stmt->execute();
                $isFirstUser = $stmt->affected_rows === 1; // 1 = inserted (we won the race), 0 = updated (someone else got there first)
                $stmt->close();
            }
        }

        // Generate ID based on whether this is first user
        if ($isFirstUser) {
            $tempId = 'admin_first_' . bin2hex(random_bytes(8));
        } else {
            $tempId = 'pending_' . bin2hex(random_bytes(16));
        }

        $stmt = $db->prepare("INSERT INTO cache (id, email, nome, admin) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $pendingName = 'Pendente'; // Placeholder name
            $adminFlag = $isFirstUser ? 1 : 0;
            $stmt->bind_param("sssi", $tempId, $email, $pendingName, $adminFlag);
            $stmt->execute();
            $stmt->close();
            return $tempId;
        }
        return null;
    }

    function update_pending_user_name($userId, $nome) {
        global $db;
        $stmt = $db->prepare("UPDATE cache SET nome = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $nome, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    function clear_user_otp($userId) {
        global $db;
        $stmt = $db->prepare("UPDATE cache SET otp_code_hash = NULL, otp_expires = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("s", $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    function generate_email_code() {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    function send_login_code_email($to, $name, $code) {
        $bodyContent = "<p>Olá " . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ",</p>" .
            "<p>Recebemos um pedido de acesso ao ClassLink para este endereço de e-mail.</p>" .
            "<p style='font-size: 1.5rem; font-weight: bold; text-align: center; margin: 20px 0;'>" . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . "</p>" .
            "<p>O código expira em 10 minutos. Se não solicitou este código, ignore este email.</p>";

        return sendStyledEmail($to, 'Código de acesso ClassLink', 'Código de acesso ClassLink', $bodyContent, 'info');
    }

    function start_authenticated_session($userId, $userName, $userEmail, $isAdmin) {
        $_SESSION['id'] = $userId;
        $_SESSION['nome'] = $userName;
        $_SESSION['email'] = $userEmail;
        $_SESSION['admin'] = (bool)$isAdmin;
        // Session validity matches the GC maxlifetime configured in
        // func/session_config.php (1800 seconds / 30 minutes). Keep these
        // values in sync to avoid users being logged out unexpectedly.
        $_SESSION['validity'] = time() + 1800;
        session_regenerate_id(true);
        // Rotate the CSRF token on every successful authentication so that a
        // token captured before login can't be reused.
        regenerate_csrf_token();
    }

    // --- POST Request Handling ---

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            die('Pedido inválido. Atualize a página e tente novamente.');
        }

        $action = $_POST['action'] ?? null;

        if ($action === 'send_code' && isset($_POST['email'])) {
            $emailValue = trim($_POST['email']);

            // Issue 14: server-side email validation
            if (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $localAuthError = 'Endereço de email inválido.';
                $localAuthStage = 'email';
            } elseif (!reserve_rate_limit_attempt('send_code', 10, 3600)) {
                // Issue 2: rate limit send_code per IP (10/hour). The reserve
                // is atomic so concurrent bursts cannot exceed the limit;
                // the rejected request is also declined before we spend
                // any SMTP budget.
                $localAuthError = 'Demasiados pedidos. Por favor aguarde antes de tentar novamente.';
                $localAuthStage = 'email';
            } else {
                $localAuthStage = 'code';
                $user = get_user_by_email($emailValue);

                // If user doesn't exist, create a pending user
                if (!$user) {
                    $userId = create_pending_user($emailValue);
                    if ($userId) {
                        $user = [
                            'id' => $userId,
                            'nome' => 'Pendente',
                            'email' => $emailValue,
                            'admin' => 0,
                            'totp_secret' => null
                        ];
                        $localAuthInfo = 'Bem-vindo ao ClassLink pela primeira vez! Valide o código que recebeu no email para criar a sua conta.';
                    } else {
                        $localAuthError = 'Erro ao criar utilizador. Tente novamente.';
                        $localAuthStage = 'email';
                    }
                } else {
                    $localAuthInfo = 'Introduza o código que recebeu no email para validar-se.';
                }

                // Issue 1 fix: send a code for BOTH new and existing users.
                // Previously the code generation was inside the else branch only,
                // so newly created users never received a code and were stuck.
                if ($user && isset($user['email'])) {
                    $code = generate_email_code();
                    $otpHash = password_hash($code, PASSWORD_DEFAULT);
                    $expiresAt = date('Y-m-d H:i:s', time() + 600);

                    $stmt = $db->prepare("UPDATE cache SET otp_code_hash = ?, otp_expires = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sss", $otpHash, $expiresAt, $user['id']);
                        $stmt->execute();
                        $stmt->close();
                    }

                    // Surface SMTP failures to the user. Without this, the
                    // user is told "check your email" but no email was ever
                    // sent — they'd be stuck on the verification page.
                    $emailResult = send_login_code_email($user['email'], $user['nome'], $code);
                    if (is_array($emailResult) && empty($emailResult['success'])) {
                        $localAuthError = 'Não foi possível enviar o email com o código. Tente novamente mais tarde.';
                        $localAuthStage = 'email';
                    }
                }

                // The reserve already counted this attempt. We do NOT
                // release it on SMTP/DB failure: a release would either
                // need to delete the whole rate-limit row (which lets an
                // attacker submit undeliverable addresses to reset the
                // shared counter) or decrement by one (which races with
                // other concurrent reservations and can drop a legitimate
                // attempt). The reservation stands, so transient upstream
                // failures correctly rate-limit the IP, the same as if
                // the email had been delivered. The lockout expires in
                // one hour.
            }
        } elseif ($action === 'verify_code' && isset($_POST['email'], $_POST['otp_code'])) {
            $emailValue = trim($_POST['email']);
            $localAuthStage = 'code';
            $user = get_user_by_email($emailValue);

            if ($user && !empty($user['otp_code_hash']) && !empty($user['otp_expires']) && strtotime($user['otp_expires']) >= time() && password_verify(trim($_POST['otp_code']), $user['otp_code_hash'])) {
                // Issue 2: clear rate-limit state on success and reset the OTP
                clear_attempts(verify_code_attempt_action($user['id']), clientIp: '0.0.0.0');
                clear_user_otp($user['id']);

                // Check if this is a pending or first-admin user (needs to
                // set their name). Both `pending_*` and `admin_first_*`
                // IDs are created with the placeholder name 'Pendente'
                // by create_pending_user(); both need to land on the
                // profile-setup step before the authenticated session
                // is started, otherwise the first admin would log in
                // permanently named 'Pendente'.
                if (str_starts_with($user['id'], 'pending_') || str_starts_with($user['id'], 'admin_first_')) {
                    // Store user info in session and redirect to name entry
                    $_SESSION['pending_user_setup'] = [
                        'id' => $user['id'],
                        'email' => $user['email']
                    ];
                    header('Location: /login?step=setup');
                    exit();
                }

                // Admin TOTP check
                if ($user['admin'] == 1 && is_admin_totp_required()) {
                    // Invalidate existing authenticated session before entering TOTP flow
                    unset($_SESSION['id'], $_SESSION['nome'], $_SESSION['email'], $_SESSION['admin'], $_SESSION['validity']);

                    if (empty($user['totp_secret'])) {
                        // Admin without TOTP - redirect to setup
                        $_SESSION['pending_totp_user'] = [
                            'id' => $user['id'],
                            'nome' => $user['nome'],
                            'email' => $user['email'],
                            'admin' => true
                        ];
                        header('Location: /login?step=totp_setup');
                        exit();
                    } else {
                        $_SESSION['pending_totp_user'] = [
                            'id' => $user['id'],
                            'nome' => $user['nome'],
                            'email' => $user['email'],
                            'admin' => true
                        ];
                        header('Location: /login?step=totp');
                        exit();
                    }
                } else {
                    start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);

                    // Blocked email check
                    if (!$_SESSION['admin']) {
                        $alunoRegex = get_app_config('blocked_emails_regex', '');
                        if (!empty($alunoRegex) && @preg_match($alunoRegex, $_SESSION['email']) === 1) {
                            session_destroy();
                            $content = '<div class="login-box"><h1>Acesso Bloqueado</h1><p>Não tem permissão para aceder a esta plataforma. Contacte o administrador do sistema.</p><a href="/login" class="login-btn">Voltar atrás</a></div>';
                            render_login_template('Acesso Bloqueado', $content);
                            die();
                        }
                    }

                    logaction('Início de sessão via Código por Email', $user['id']);
                    header('Location: /');
                    exit();
                }
            } else {
                // Wrong code: count it and invalidate the OTP if the
                // budget is exhausted. The 5th attempt is always checked,
                // so a correct 5th code is accepted; only after the 5th
                // wrong code does the next request see the invalidation.
                // The pre-reserve atomicity is dropped for this path
                // because the limit is on wrong codes, not on attempts,
                // and the small TOCTOU window (concurrent wrong guesses
                // from the same session) is acceptable.
                if ($user) {
                    $verifyCodeAction = verify_code_attempt_action($user['id']);
                    record_attempt($verifyCodeAction, 3600, '0.0.0.0');
                    if (!check_rate_limit($verifyCodeAction, 5, 3600, '0.0.0.0')) {
                        invalidate_user_otp($user['id']);
                        clear_attempts($verifyCodeAction, '0.0.0.0');
                        $localAuthError = 'Demasiadas tentativas inválidas. O código atual foi invalidado; por favor solicite um novo código.';
                    } else {
                        $localAuthError = 'Código inválido ou expirado. Peça um novo código.';
                    }
                } else {
                    $localAuthError = 'Código inválido ou expirado. Peça um novo código.';
                }
            }
        } elseif ($action === 'setup_name' && isset($_POST['nome'])) {
            if (!isset($_SESSION['pending_user_setup'])) {
                $localAuthError = 'Sessão expirada. Por favor tente novamente.';
                $localAuthStage = 'email';
            } else {
                $nome = trim($_POST['nome']);
                if (empty($nome)) {
                    $localAuthError = 'Por favor introduza o seu nome.';
                    $localAuthStage = 'setup';
                } else {
                    $pendingUser = $_SESSION['pending_user_setup'];
                    update_pending_user_name($pendingUser['id'], $nome);

                    // Get the updated user
                    $user = get_user_by_email($pendingUser['email']);

                    // Clear pending session
                    unset($_SESSION['pending_user_setup']);

                    // Admin TOTP check. The first administrator reaches
                    // this point from the `admin_first_*` routing branch
                    // in verify_code above, which redirects to profile
                    // setup before the regular admin TOTP check runs.
                    // Without this, the first admin would receive a fully
                    // authenticated admin session without configuring
                    // or verifying TOTP, bypassing the admin_requires_totp
                    // policy.
                    if ($user['admin'] == 1 && is_admin_totp_required()) {
                        if (empty($user['totp_secret'])) {
                            $_SESSION['pending_totp_user'] = [
                                'id' => $user['id'],
                                'nome' => $user['nome'],
                                'email' => $user['email'],
                                'admin' => true
                            ];
                            header('Location: /login?step=totp_setup');
                            exit();
                        }
                        $_SESSION['pending_totp_user'] = [
                            'id' => $user['id'],
                            'nome' => $user['nome'],
                            'email' => $user['email'],
                            'admin' => true
                        ];
                        header('Location: /login?step=totp');
                        exit();
                    }

                    // Log the user in
                    start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                    logaction('Conta criada com sucesso via Código por Email', $user['id']);

                    // Issue (P1): the blocked-emails policy must apply to
                    // newly created accounts too. Without this, a user
                    // whose address matches blocked_emails_regex can
                    // complete the local-auth flow (request a code,
                    // verify it, set a name) and bypass the policy that
                    // applies to existing local and OAuth users in the
                    // verify_code success path below. Admins are
                    // exempt from the policy, same as in verify_code.
                    if (!$_SESSION['admin']) {
                        $alunoRegex = get_app_config('blocked_emails_regex', '');
                        if (!empty($alunoRegex) && @preg_match($alunoRegex, $_SESSION['email']) === 1) {
                            session_destroy();
                            $content = '<div class="login-box"><h1>Acesso Bloqueado</h1><p>Não tem permissão para aceder a esta plataforma. Contacte o administrador do sistema.</p><a href="/login" class="login-btn">Voltar atrás</a></div>';
                            render_login_template('Acesso Bloqueado', $content);
                            die();
                        }
                    }

                    header('Location: /');
                    exit();
                }
            }
        } elseif ($action === 'verify_totp' && isset($_POST['totp_code'])) {
            if (!isset($_SESSION['pending_totp_user'])) {
                $localAuthError = 'A sessão expirou. Por favor inicie sessão de novo.';
            } elseif (is_blocked('verify_totp')) {
                // TOTP IP-based lockout: 5 wrong attempts in 15 min.
                $localAuthError = 'Demasiadas tentativas inválidas. Por favor aguarde 15 minutos antes de tentar novamente.';
            } else {
                $pendingUser = $_SESSION['pending_totp_user'];
                $user = get_user_by_email($pendingUser['email']);

                if (!$user || empty($user['totp_secret'])) {
                    $localAuthError = 'Não foi possível validar o TOTP. Contacte o administrador do sistema.';
                } else {
                    $google2fa = new \PragmaRX\Google2FA\Google2FA();
                    if ($google2fa->verifyKey($user['totp_secret'], trim($_POST['totp_code']))) {
                        clear_attempts('verify_totp');
                        unset($_SESSION['pending_totp_user']);
                        start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                        logaction('Início de sessão via TOTP de administrador', $user['id']);
                        header('Location: /');
                        exit();
                    }
                    // Wrong code: count it and lock if the budget is
                    // exhausted. The 5th attempt is always checked, so
                    // a correct 5th code is accepted; only after the 5th
                    // wrong code does the next request see the lock.
                    record_attempt('verify_totp', 900);
                    if (!check_rate_limit('verify_totp', 5, 900)) {
                        block('verify_totp', 900);
                        $localAuthError = 'Demasiadas tentativas inválidas. Por favor aguarde 15 minutos antes de tentar novamente.';
                    } else {
                        $localAuthError = 'Código TOTP inválido. Por favor tente novamente.';
                    }
                }
            }
        } elseif ($action === 'verify_totp_setup' && isset($_POST['totp_code'])) {
            if (!isset($_SESSION['pending_totp_user']) || !isset($_SESSION['pending_totp_secret'])) {
                $localAuthError = 'Sessão expirada. Por favor tente novamente.';
            } elseif (is_blocked('verify_totp_setup')) {
                $localAuthError = 'Demasiadas tentativas inválidas. Por favor aguarde 15 minutos antes de tentar novamente.';
            } else {
                $pendingUser = $_SESSION['pending_totp_user'];
                $secret = $_SESSION['pending_totp_secret'];

                $google2fa = new \PragmaRX\Google2FA\Google2FA();
                if ($google2fa->verifyKey($secret, trim($_POST['totp_code']))) {
                    // Save the secret to the database
                    global $db;
                    $stmt = $db->prepare("UPDATE cache SET totp_secret = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ss", $secret, $pendingUser['id']);
                        $stmt->execute();
                        $stmt->close();
                    }

                    clear_attempts('verify_totp_setup');
                    // Clear temp session and log in
                    unset($_SESSION['pending_totp_user']);
                    unset($_SESSION['pending_totp_secret']);

                    $user = get_user_by_email($pendingUser['email']);
                    start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                    logaction('TOTP configurado com sucesso', $user['id']);
                    header('Location: /');
                    exit();
                }
                // Wrong code: count it and lock if the budget is
                // exhausted, same logic as verify_totp.
                record_attempt('verify_totp_setup', 900);
                if (!check_rate_limit('verify_totp_setup', 5, 900)) {
                    block('verify_totp_setup', 900);
                    $localAuthError = 'Demasiadas tentativas inválidas. Por favor aguarde 15 minutos antes de tentar novamente.';
                } else {
                    $localAuthError = 'Código TOTP inválido. Por favor tente novamente.';
                }
            }
        }
    }

    // --- TOTP Step Handler ---
    if (isset($_GET['step']) && $_GET['step'] == 'totp') {
        if (!isset($_SESSION['pending_totp_user'])) {
            header('Location: /login');
            exit();
        }
        $devModeBanner = is_development_mode() ? '<div style="background-color: #dc3545; color: white; padding: 4px; font-size: 12px; font-weight: bold; text-align: center; position: absolute; top: 0; width: 100%; z-index: 100;">⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento</div>' : '';
        $content = $devModeBanner . '<div class="login-box"><h1>Verificação de Segurança</h1><p class="small">Introduza o código do seu autenticador para prosseguir.</p>' . (!empty($localAuthError) ? '<div class="error-msg">' . htmlspecialchars($localAuthError) . '</div>' : '') . '<form method="POST" action="/login/index.php">' . $csrfTokenField . '<input type="hidden" name="action" value="verify_totp"><div class="form-group"><input type="text" name="totp_code" placeholder="Código do autenticador" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required></div><button type="submit">Autenticar</button></form></div>';
        render_login_template('Verificação de Segurança', $content);
        die();
    }

    // --- User Setup Step Handler (Name Entry) ---
    if (isset($_GET['step']) && $_GET['step'] == 'setup') {
        if (!isset($_SESSION['pending_user_setup'])) {
            header('Location: /login');
            exit();
        }
        $pendingUser = $_SESSION['pending_user_setup'];

        $content = '<div class="login-box">';
        $content .= '<h1>Complete o seu perfil</h1>';
        $content .= '<p class="small">Por favor, introduza o seu nome completo.</p>';
        if (!empty($localAuthError)) { $content .= '<div class="error-msg">' . htmlspecialchars($localAuthError) . '</div>'; }
        $content .= '<form method="POST" action="/login/index.php">';
        $content .= $csrfTokenField;
        $content .= '<input type="hidden" name="action" value="setup_name">';
        $content .= '<div class="form-group"><input type="text" name="nome" placeholder="Nome completo" required></div>';
        $content .= '<button type="submit">Continuar</button>';
        $content .= '</form>';
        $content .= '</div>';

        render_login_template('Complete o seu perfil', $content);
        die();
    }

    // --- TOTP Setup Step Handler (QR Code for admins without TOTP) ---
    if (isset($_GET['step']) && $_GET['step'] == 'totp_setup') {
        if (!isset($_SESSION['pending_totp_user'])) {
            header('Location: /login');
            exit();
        }
        $pendingUser = $_SESSION['pending_totp_user'];
        
        // Generate TOTP secret
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $secret = $google2fa->generateSecretKey();
        
        // Store secret temporarily in session (should be saved to DB after verification)
        $_SESSION['pending_totp_secret'] = $secret;

        // Generate QR code URL
        $qrCodeUrl = $google2fa->getQRCodeUrl(
            get_app_config('brand_name', 'ClassLink'),
            $pendingUser['email'],
            $secret
        );

        // Issue 12 fix: render the QR code locally with the chillerlan/php-qrcode
        // Composer library. The previous implementation sent the otpauth:// URL
        // (which contains the TOTP secret) to a third-party service that could
        // go offline, log the secret, or be MITM'd by a corporate firewall.
        $qrCodeSvg = '';
        try {
            $qrOptions = new \chillerlan\QRCode\QROptions([
                'outputType'   => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::H,
                'scale'        => 8,
                'outputBase64' => false,
            ]);
            $qrCode = new \chillerlan\QRCode\QRCode($qrOptions);
            $qrCodeSvg = $qrCode->render($qrCodeUrl);
        } catch (\Throwable $qrErr) {
            // If QR generation fails for any reason, fall back to the manual code entry.
            error_log('ClassLink QR generation failed: ' . $qrErr->getMessage());
        }

        $content = '<div class="login-box">';
        $content .= '<h1>Configurar Autenticador</h1>';
        $content .= '<p class="small">Escaneie o código QR com a sua aplicação de autenticação ou introduza o código manualmente.</p>';
        if (!empty($localAuthError)) { $content .= '<div class="error-msg">' . htmlspecialchars($localAuthError) . '</div>'; }
        if ($qrCodeSvg !== '') {
            $content .= '<div class="qr-container">' . $qrCodeSvg . '</div>';
        }
        $content .= '<div class="info-msg"><strong>Código manual:</strong><br><span class="manual-code">' . htmlspecialchars($secret) . '</span></div>';
        $content .= '<form method="POST" action="/login/index.php">';
        $content .= $csrfTokenField;
        $content .= '<input type="hidden" name="action" value="verify_totp_setup">';
        $content .= '<div class="form-group"><input type="text" name="totp_code" placeholder="Código do autenticador" pattern="\\d{6}" maxlength="6" autocomplete="one-time-code" required></div>';
        $content .= '<button type="submit">Validar e Ativar</button>';
        $content .= '</form>';
        $content .= '</div>';

        render_login_template('Configurar Autenticador', $content);
        die();
    }

    // --- Local Auth Form Page (Initial) ---
    if (!isset($_GET['code']) && !isset($_GET['error']) && !isset($_GET['action']) && !isset($_GET['redirecttoflow'])) {
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Iniciar Sessão - ClassLink</title>
            <link rel="stylesheet" href="/assets/theme.css">
            <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
            <style>
                body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background: var(--bg-gradient); display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; }
                #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; }
                .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); }
                .login-box h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: var(--text-color); }
                .login-box .small { color: var(--text-color); }
                .login-btn { display: inline-block; background-color: #24a1da; color: white; text-decoration: none; padding: 0.8rem 1.2rem; border-radius: 8px; font-size: 1rem; font-weight: 500; transition: background 0.2s; }
                .login-btn:hover { opacity: 0.9; }
                .form-group { margin-bottom: 1rem; }
                input { padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); width: 100%; box-sizing: border-box; }
                button { background-color: #24a1da; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; margin-top: 0.5rem; }
                button:hover { opacity: 0.9; }
                .divider { display: flex; align-items: center; margin: 1.5rem 0; color: var(--text-color); opacity: 0.6; }
                .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid currentColor; }
                .divider:not(:empty)::before { margin-right: .5em; }
                .divider:not(:empty)::after { margin-left: .5em; }
                .info-msg { color: var(--text-color); background: rgba(255,255,255,0.1); padding: 0.75rem; margin-bottom: 1rem; border-radius: 8px; text-align: left; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.2); }
                .error-msg { color: #ff3333; font-size: 0.9rem; margin-bottom: 1rem; background: rgba(255,50,50,0.1); padding: 0.5rem; border-radius: 5px; }
                .ms-logo { vertical-align: middle; margin-right: 8px; }
                .login-btn { display: block; background-color: #2F2F2F; color: white; text-decoration: none; padding: 0.8rem 1.2rem; border-radius: 8px; font-size: 1rem; font-weight: 500; transition: background 0.2s; margin-top: 1rem; }
                .login-btn:hover { background-color: #1b1b1b; }
                .login-btn:hover .ms-rect1 { fill: #f25022; }
                .login-btn:hover .ms-rect2 { fill: #7fba00; }
                .login-btn:hover .ms-rect3 { fill: #00a4ef; }
                .login-btn:hover .ms-rect4 { fill: #ffb900; }
            </style>
        </head>
        <body>
            <div id="particles-js"></div>
            <div class="login-box">
                <img src="/assets/logo.png" alt="Logotipo ClassLink" style="max-width:35%; margin-bottom:1rem;">
                <h1>Iniciar Sessão no ClassLink</h1>
                <?php if ($showDbPicker): ?>
                <form method="POST" action="/login/index.php" style="margin-bottom: 1rem;">
                    <?= $csrfTokenField ?>
                    <input type="hidden" name="action" value="select_db">
                    <select name="db_selection" onchange="this.form.submit()" class="form-select" style="max-width: 200px; margin: 0 auto;">
                        <option value="">Selecionar Base de Dados...</option>
                        <?php
                        foreach ($dbOptions as $key => $dbName):
                            $selected = ($_SESSION['selected_db'] ?? '') === $dbName ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($dbName) ?>" <?= $selected ?>>
                            <?= htmlspecialchars(ucfirst($key)) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php endif; ?>
                <p class="small">Para aceder à plataforma, deve autenticar-se com a sua conta institucional.</p>
                <?php if (!empty($localAuthError)): ?>
                    <div class="error-msg"><?= htmlspecialchars($localAuthError) ?></div>
                <?php endif; ?>
                <?php if (!empty($localAuthInfo)): ?>
                    <div class="info-msg"><?= htmlspecialchars($localAuthInfo) ?></div>
                <?php endif; ?>

                <?php if ($localAuthStage === 'email'): ?>
                <form method="POST" action="/login/index.php">
                    <?= $csrfTokenField ?>
                    <input type="hidden" name="action" value="send_code">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Endereço Eletrónico" value="<?= htmlspecialchars($emailValue) ?>" required>
                    </div>
                    <button type="submit">Enviar código</button>
                </form>
                <?php else: ?>
                <form method="POST" action="/login/index.php">
                    <?= $csrfTokenField ?>
                    <input type="hidden" name="action" value="verify_code">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Endereço Eletrónico" value="<?= htmlspecialchars($emailValue) ?>" readonly style="opacity: 0.7;">
                    </div>
                    <div class="form-group">
                        <input type="text" name="otp_code" placeholder="Código de 6 dígitos" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit">Validar código</button>
                </form>
                <?php endif; ?>

                <div class="divider">ou</div>

                <?php
                $isMicrosoft = str_starts_with($provider->getBaseAuthorizationUrl(), 'https://login.microsoftonline.com/');
                ?>
                <a href="/login?redirecttoflow=1" class="login-btn">
                    <?php if ($isMicrosoft): ?>
                    <svg class="ms-logo" width="21" height="21" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg">
                        <rect class="ms-rect1" x="1" y="1" width="9" height="9" fill="white"/>
                        <rect class="ms-rect2" x="11" y="1" width="9" height="9" fill="white"/>
                        <rect class="ms-rect3" x="1" y="11" width="9" height="9" fill="white"/>
                        <rect class="ms-rect4" x="11" y="11" width="9" height="9" fill="white"/>
                    </svg>
                    <?php endif; ?>
                    <?= $isMicrosoft ? 'Iniciar Sessão com Microsoft' : 'Iniciar Sessão com Fornecedor de Identidade' ?>
                </a>
            </div>
            <script>
                particlesJS("particles-js", {
                    "particles": { "number": { "value": 60, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#ffffff" }, "shape": { "type": "circle" }, "opacity": { "value": 0.6, "random": false }, "size": { "value": 3, "random": true }, "line_linked": { "enable": true, "distance": 150, "color": "#ffffff", "opacity": 0.4, "width": 1 }, "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false } },
                    "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true }, "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } }, "push": { "particles_nb": 4 } } },
                    "retina_detect": true
                });
            </script>
        </body>
        </html>
        <?php
        die();
    }

    // --- GET Routing ---

    if (isset($_GET['action']) && $_GET['action'] == "logout"){
        session_destroy();
        
        $content = '<div class="login-box"><img src="/assets/logo.png" alt="Logotipo ClassLink" style="max-width:25%;"><h1>Terminou sessão</h1><p class="small">Caso pretenda voltar a iniciar sessão, carregue no botão em baixo.</p><a href="/login" class="login-btn">Iniciar Sessão</a></div>';
        
        render_login_template('Iniciar Sessão', $content);
        die();
    } else if (isset($_GET['error'])) {
	?>
<?php
    }else if (isset($_GET['code'])){        $now = time();
        if (!isset($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] === '' || $_SESSION['oauth2state'] === '' || !hash_equals($_SESSION['oauth2state'], $_GET['state'])) {
            $clientIp = get_client_ip();
            $sessionHash = substr(hash('sha256', session_id()), 0, 8);
            error_log("ClassLink OAuth state validation failed for callback. ip={$clientIp}; session_hash={$sessionHash}");
            unset($_SESSION['oauth2state']);
            header('Location: /login/');
            exit();
        }
        unset($_SESSION['oauth2state']);
        try {
            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $_GET['code']
            ]);
            $resourceOwner = $provider->getResourceOwner($accessToken);
            // Atribuir valores desta sessão OAuth2
            $_SESSION['validity'] = $now + $accessToken->getExpires();
            $_SESSION['resourceOwner'] = $resourceOwner->toArray();
            $_SESSION['nome'] = $_SESSION['resourceOwner']['name'];
            $_SESSION['email'] = $_SESSION['resourceOwner']['email'];
            $_SESSION['id'] = $_SESSION['resourceOwner']['sub'];

            // Check if there's any existing user with this email (pre_, pending_, admin_first_, etc.)
            // SECURITY: only merge if the source row has a pre-registered
            // prefix. Otherwise any existing user (including another admin
            // or a real OAuth user whose tenant changed) would be silently
            // absorbed, and the new account would inherit the original's
            // admin flag — a privilege-escalation vector.
            $stmt = $db->prepare("SELECT id, admin FROM cache WHERE email = ? AND id != ? AND (id LIKE 'pre\\_%' OR id LIKE 'pending\\_%' OR id LIKE 'admin\\_first\\_%')");
            $stmt->bind_param("ss", $_SESSION['email'], $_SESSION['id']);
            $stmt->execute();
            $preRegisteredUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($preRegisteredUser) {
                // User was pre-registered, migrate to the real OAuth2 ID
                // Use a transaction to ensure atomicity
                $db->begin_transaction();
                try {
                    $adminStatus = $preRegisteredUser['admin'] ?? 0;

                    // First, insert a new record with the real OAuth2 ID
                    // This ensures the foreign key target exists before we update references
                    $stmt = $db->prepare("INSERT INTO cache (id, nome, email, admin) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param("sssi", $_SESSION['id'], $_SESSION['nome'], $_SESSION['email'], $adminStatus);
                    $stmt->execute();
                    $stmt->close();

                    // Now update foreign key references in reservas table
                    $stmt = $db->prepare("UPDATE reservas SET requisitor = ? WHERE requisitor = ?");
                    $stmt->bind_param("ss", $_SESSION['id'], $preRegisteredUser['id']);
                    $stmt->execute();
                    $stmt->close();

                    // Update foreign key references in logs table
                    $stmt = $db->prepare("UPDATE logs SET userid = ? WHERE userid = ?");
                    $stmt->bind_param("ss", $_SESSION['id'], $preRegisteredUser['id']);
                    $stmt->execute();
                    $stmt->close();

                    // Finally, delete the old pre-registered user record
                    $stmt = $db->prepare("DELETE FROM cache WHERE id = ?");
                    $stmt->bind_param("s", $preRegisteredUser['id']);
                    $stmt->execute();
                    $stmt->close();

                    $db->commit();

                    // Log first sign-on for pre-registered user
                    require_once(__DIR__ . '/../func/logaction.php');
                    logaction("Primeiro início de sessão realizado com sucesso (utilizador pré-registado migrado para conta OAuth2)", $_SESSION['id']);
                } catch (Exception $e) {
                    $db->rollback();
                    throw $e;
                }
            } else {
                // No pre-registered user found, check if this is truly a new user
                $stmt = $db->prepare("SELECT id FROM cache WHERE id = ?");
                $stmt->bind_param("s", $_SESSION['id']);
                $stmt->execute();
                $existingUser = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $isNewUser = !$existingUser;

                // Insert new record (existing behavior)
                $stmt = $db->prepare("INSERT IGNORE INTO cache (id, nome, email) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $_SESSION['id'], $_SESSION['nome'], $_SESSION['email']);
                $stmt->execute();
                $stmt->close();

                // Log first sign-on for new users
                if ($isNewUser) {
                    require_once(__DIR__ . '/../func/logaction.php');
                    logaction("Primeiro início de sessão realizado com sucesso (nova conta criada)", $_SESSION['id']);
                }
            }

            // Determinar se é Administrador
            $stmt = $db->prepare("SELECT admin FROM cache WHERE id = ?");
            $stmt->bind_param("s", $_SESSION['id']);
            $stmt->execute();
            $isadmin = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($isadmin['admin'] == 1){
                $_SESSION['admin'] = true;
            } else {
                $_SESSION['admin'] = false;
            }
            // Regenerate session ID for security
            session_regenerate_id(true);

            // MPISCO 11/12: Verificar se o email cai dentro de um regex (se não for considerado Administrador), e não permitir login caso o regex bata.
            // Check blocked emails regex
            if (!$_SESSION['admin']) {
                $alunoRegex = get_app_config('blocked_emails_regex', '');
                if (!empty($alunoRegex) && preg_match($alunoRegex, $_SESSION['email'])) {
                    // Email corresponde ao regex de alunos - negar acesso
                    session_destroy();
                    // Devolver página de Login ClassLink
                    echo "<!DOCTYPE html>";
                    echo "<html lang=\"pt\">";
                    echo "<head>";
                    echo "<meta charset=\"UTF-8\">";
                    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
                    echo "<title>Iniciar Sessão - ClassLink</title>";
                    $content = '<div class="login-box"><img src="/assets/logo.png" alt="Logotipo ClassLink" style="max-width:25%;"><h1>Sem permissão</h1><p class="small">Não tem autorização para entrar nesta página.</p><a href="/login" class="login-btn">Voltar atrás</a></div>';
                    
                    render_login_template('Iniciar Sessão', $content);
                    die();
                }
            }

            // --- Admin TOTP Check for OAuth Flow ---
            $adminRequiresTotp = get_app_config('admin_requires_totp', true);
            if ($adminRequiresTotp && $_SESSION['admin']) {
                $stmt = $db->prepare("SELECT totp_secret FROM cache WHERE id = ? AND admin = 1");
                if ($stmt) {
                    $stmt->bind_param("s", $_SESSION['id']);
                    $stmt->execute();
                    $adminData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (empty($adminData['totp_secret'])) {
                        // Admin has no TOTP configured - redirect to setup.
                        // Defense in depth: clear the authenticated identity
                        // before redirecting so a partially-authenticated
                        // admin (no TOTP yet verified) cannot be used by
                        // any future page that forgets to check
                        // pending_totp_user.
                        $pendingTotpId = $_SESSION['id'];
                        $pendingTotpNome = $_SESSION['nome'];
                        $pendingTotpEmail = $_SESSION['email'];
                        unset($_SESSION['id'], $_SESSION['nome'], $_SESSION['email'], $_SESSION['admin'], $_SESSION['validity']);
                        $_SESSION['pending_totp_user'] = [
                            'id' => $pendingTotpId,
                            'nome' => $pendingTotpNome,
                            'email' => $pendingTotpEmail,
                            'admin' => true
                        ];
                        header('Location: /login?step=totp_setup');
                        exit();
                    }

                    // Valid admin with TOTP: redirect to TOTP verification step.
                    // Same defense in depth as above.
                    $pendingTotpId = $_SESSION['id'];
                    $pendingTotpNome = $_SESSION['nome'];
                    $pendingTotpEmail = $_SESSION['email'];
                    unset($_SESSION['id'], $_SESSION['nome'], $_SESSION['email'], $_SESSION['admin'], $_SESSION['validity']);
                    $_SESSION['pending_totp_user'] = [
                        'id' => $pendingTotpId,
                        'nome' => $pendingTotpNome,
                        'email' => $pendingTotpEmail,
                        'admin' => true
                    ];
                    header('Location: /login?step=totp');
                    exit();
                }
            }

            header('Location: /');
            exit();
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Issue 7 fix: never expose the raw exception message to users.
            // Log the full message server-side and render a generic page with
            // a collapsible "Debug" section that the user can open to see the
            // full error text. This keeps the login page safe to share (e.g.
            // for support tickets) while still letting curious users
            // troubleshoot.
            error_log('ClassLink OAuth callback failed: ' . $e->getMessage());
            if ($e->getMessage() == 'invalid_grant') {
                session_destroy();
                header('Location: /login/');
                exit();
            }
            $safeMessage = 'Ocorreu um problema a concluir a autenticação. Por favor tente iniciar sessão novamente.';
            $debugDetails = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $content = '<div class="login-box">';
            $content .= '<img src="/assets/logo.png" alt="Logotipo ClassLink" style="max-width:25%;">';
            $content .= '<h1>Autenticação Falhou</h1>';
            $content .= '<p>' . htmlspecialchars($safeMessage) . '</p>';
            $content .= '<details style="margin-top: 1rem; text-align: left; font-size: 0.85rem;">';
            $content .= '<summary style="cursor: pointer; opacity: 0.7;">Debug</summary>';
            $content .= '<pre style="margin-top: 0.5rem; padding: 0.5rem; background: rgba(0,0,0,0.05); border-radius: 6px; white-space: pre-wrap; word-break: break-word;">' . $debugDetails . '</pre>';
            $content .= '</details>';
            $content .= '<a href="/login" class="login-btn" style="margin-top: 1rem; display: inline-block;">Voltar ao Início de Sessão</a>';
            $content .= '</div>';
            render_login_template('Autenticação Falhou', $content);
            exit();
        }
    } else if (str_starts_with($_SERVER['REQUEST_URI'], "/login") && $_GET['redirecttoflow']) {
	    $scopes = [
    		'scope' => ['openid profile email']
    	];
        $authorizationUrl = $provider->getAuthorizationUrl($scopes);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authorizationUrl);
    } else {
        // Render login page via the template helper (avoid echoing large HTML blocks)
        $content = '<div class="login-box">';
        $content .= '<img src="/assets/logo.png" alt="Logotipo ClassLink" style="max-width:25%;">';
        $content .= '<h1>Iniciar Sessão no ClassLink</h1>';
        $content .= '<p class="small">Para aceder à plataforma, deve autenticar-se com a sua conta institucional.</p>';

        $isMicrosoft = str_starts_with($provider->getBaseAuthorizationUrl(), 'https://login.microsoftonline.com/');
        if ($isMicrosoft) {
            $content .= '<a href="/login?redirecttoflow=1" class="login-btn"><svg class="ms-logo" width="21" height="21" viewBox="0 0 21 21" xmlns="http://www.w3.org/2000/svg" style="vertical-align: middle; margin-right: 8px;"><rect class="ms-rect1" x="1" y="1" width="9" height="9" fill="white"/><rect class="ms-rect2" x="11" y="1" width="9" height="9" fill="white"/><rect class="ms-rect3" x="1" y="11" width="9" height="9" fill="white"/><rect class="ms-rect4" x="11" y="11" width="9" height="9" fill="white"/></svg>Iniciar Sessão com Microsoft</a>';
        } else {
            $content .= '<a href="/login?redirecttoflow=1" class="login-btn">Iniciar Sessão com Fornecedor de Identidade</a>';
        }

        $content .= '</div>';

        render_login_template('Iniciar Sessão', $content);
        die();
    }
?>
