<?php
    session_start();

    // Handle database selection
    if (isset($_POST['action']) && $_POST['action'] === 'select_db') {
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

    // --- Helper Functions for OTP/TOTP ---

    function is_admin_totp_required() {
        return filter_var(get_app_config('admin_requires_totp', true), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== false;
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
        
        // Check if this is the first user - make them admin
        $userCountResult = $db->query("SELECT COUNT(*) as count FROM cache");
        $userCount = $userCountResult->fetch_assoc()['count'];
        $isFirstUser = $userCount == 0;
        
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
        $_SESSION['validity'] = time() + 3600;
        session_regenerate_id(true);
    }

    // --- POST Request Handling ---

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;

        if ($action === 'send_code' && isset($_POST['email'])) {
            $emailValue = trim($_POST['email']);
            
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
                
                if ($user && $localAuthStage === 'code') {
                    $code = generate_email_code();
                    $otpHash = password_hash($code, PASSWORD_DEFAULT);
                    $expiresAt = date('Y-m-d H:i:s', time() + 600);

                    $stmt = $db->prepare("UPDATE cache SET otp_code_hash = ?, otp_expires = ? WHERE id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sss", $otpHash, $expiresAt, $user['id']);
                        $stmt->execute();
                        $stmt->close();
                    }

                    send_login_code_email($user['email'], $user['nome'], $code);
                }
            }
        } elseif ($action === 'verify_code' && isset($_POST['email'], $_POST['otp_code'])) {
            $emailValue = trim($_POST['email']);
            $localAuthStage = 'code';
            $user = get_user_by_email($emailValue);

            if ($user && !empty($user['otp_code_hash']) && !empty($user['otp_expires']) && strtotime($user['otp_expires']) >= time() && password_verify(trim($_POST['otp_code']), $user['otp_code_hash'])) {
                clear_user_otp($user['id']);

                // Check if this is a pending user (needs to set their name)
                if (str_starts_with($user['id'], 'pending_')) {
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
                        if (preg_match($alunoRegex, $_SESSION['email'])) {
                            session_destroy();
                            echo "<!DOCTYPE html><html lang=\"pt\"><head><meta charset=\"UTF-8\"><meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\"><title>Acesso Bloqueado - ClassLink</title><link rel=\"stylesheet\" href=\"/assets/theme.css\"></head><body style=\"display:flex;justify-content:center;align-items:center;height:100vh;flex-direction:column;color:var(--text-color);background-color: #0b132b; position: relative;\"><div style=\"background:var(--white-overlay);padding:2rem;border-radius:16px;text-align:center;\"><h1>Acesso Bloqueado</h1><p>Não tem permissão para aceder a esta plataforma. Contacte o administrador do sistema.</p><a href=\"/login\" style=\"background:#24a1da;color:white;padding:0.8rem 1.2rem;border-radius:8px;text-decoration:none;\">Voltar atrás</a></div></body></html>";
                            die();
                        }
                    }

                    logaction('Início de sessão via Código por Email', $user['id']);
                    header('Location: /');
                    exit();
                }
            } else {
                $localAuthError = 'Código inválido ou expirado. Peça um novo código.';
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
                    
                    // Log the user in
                    start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                    logaction('Conta criada com sucesso via Código por Email', $user['id']);
                    
                    header('Location: /');
                    exit();
                }
            }
        } elseif ($action === 'verify_totp' && isset($_POST['totp_code'])) {
            if (!isset($_SESSION['pending_totp_user'])) {
                $localAuthError = 'A sessão expirou. Por favor inicie sessão de novo.';
            } else {
                $pendingUser = $_SESSION['pending_totp_user'];
                $user = get_user_by_email($pendingUser['email']);

                if (!$user || empty($user['totp_secret'])) {
                    $localAuthError = 'Não foi possível validar o TOTP. Contacte o administrador do sistema.';
                } else {
                    $google2fa = new \PragmaRX\Google2FA\Google2FA();
                    if ($google2fa->verifyKey($user['totp_secret'], trim($_POST['totp_code']))) {
                        unset($_SESSION['pending_totp_user']);
                        start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                        logaction('Início de sessão via TOTP de administrador', $user['id']);
                        header('Location: /');
                        exit();
                    }
                    $localAuthError = 'Código TOTP inválido. Por favor tente novamente.';
                }
            }
        } elseif ($action === 'verify_totp_setup' && isset($_POST['totp_code'])) {
            if (!isset($_SESSION['pending_totp_user']) || !isset($_SESSION['pending_totp_secret'])) {
                $localAuthError = 'Sessão expirada. Por favor tente novamente.';
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
                    
                    // Clear temp session and log in
                    unset($_SESSION['pending_totp_user']);
                    unset($_SESSION['pending_totp_secret']);
                    
                    $user = get_user_by_email($pendingUser['email']);
                    start_authenticated_session($user['id'], $user['nome'], $user['email'], $user['admin']);
                    logaction('TOTP configurado com sucesso', $user['id']);
                    header('Location: /');
                    exit();
                }
                $localAuthError = 'Código TOTP inválido. Por favor tente novamente.';
            }
        }
    }

    // --- TOTP Step Handler ---
    if (isset($_GET['step']) && $_GET['step'] == 'totp') {
        if (!isset($_SESSION['pending_totp_user'])) {
            header('Location: /login');
            exit();
        }
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verificação de Segurança - ClassLink</title>
            <link rel="stylesheet" href="/assets/theme.css">
            <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
            <style>
                body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background-color: #0b132b; display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; }
                #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; }
                .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); }
                .form-group { margin-bottom: 1rem; }
                input { padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); width: 100%; box-sizing: border-box; }
                button { background-color: #24a1da; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; }
                button:hover { opacity: 0.9; }
                .error-msg { color: #ff3333; font-size: 0.9rem; margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <?php if (is_development_mode()): ?>
            <div style="background-color: #dc3545; color: white; padding: 4px; font-size: 12px; font-weight: bold; text-align: center;">
                ⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento
            </div>
            <?php endif; ?>
            <div id="particles-js"></div>
            <div class="login-box">
                <h1>Verificação de Segurança</h1>
                <p class="small">Sendo administrador, necessita de introduzir o código do seu autenticador para prosseguir.</p>
                <?php if (!empty($localAuthError)): ?>
                    <div class="error-msg"><?= htmlspecialchars($localAuthError) ?></div>
                <?php endif; ?>
                <form method="POST" action="/login/index.php">
                    <input type="hidden" name="action" value="verify_totp">
                    <div class="form-group">
                        <input type="text" name="totp_code" placeholder="Código do autenticador" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit">Autenticar</button>
                </form>
            </div>
            <script>
                particlesJS("particles-js", {
                    "particles": { "number": { "value": 60, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#00e5ff" }, "shape": { "type": "circle" }, "opacity": { "value": 0.5, "random": false }, "size": { "value": 3, "random": true }, "line_linked": { "enable": true, "distance": 150, "color": "#00e5ff", "opacity": 0.3, "width": 1 }, "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false } },
                    "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true }, "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } }, "push": { "particles_nb": 4 } } },
                    "retina_detect": true
                });
            </script>
        </body>
        </html>
        <?php
        die();
    }

    // --- User Setup Step Handler (Name Entry) ---
    if (isset($_GET['step']) && $_GET['step'] == 'setup') {
        if (!isset($_SESSION['pending_user_setup'])) {
            header('Location: /login');
            exit();
        }
        $pendingUser = $_SESSION['pending_user_setup'];
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Complete o seu perfil - ClassLink</title>
            <link rel="stylesheet" href="/assets/theme.css">
            <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
            <style>
                body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background-color: #0b132b; display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; }
                #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; }
                .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); }
                .form-group { margin-bottom: 1rem; }
                input { padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); width: 100%; box-sizing: border-box; }
                button { background-color: #24a1da; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; }
                button:hover { opacity: 0.9; }
                .error-msg { color: #ff3333; font-size: 0.9rem; margin-bottom: 1rem; }
            </style>
        </head>
        <body>
            <div id="particles-js"></div>
            <div class="login-box">
                <h1>Complete o seu perfil</h1>
                <p class="small">Por favor, introduza o seu nome completo.</p>
                <?php if (!empty($localAuthError)): ?>
                    <div class="error-msg"><?= htmlspecialchars($localAuthError) ?></div>
                <?php endif; ?>
                <form method="POST" action="/login/index.php">
                    <input type="hidden" name="action" value="setup_name">
                    <div class="form-group">
                        <input type="text" name="nome" placeholder="Nome completo" required>
                    </div>
                    <button type="submit">Continuar</button>
                </form>
            </div>
            <script>
                particlesJS("particles-js", {
                    "particles": { "number": { "value": 60, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#00e5ff" }, "shape": { "type": "circle" }, "opacity": { "value": 0.5, "random": false }, "size": { "value": 3, "random": true }, "line_linked": { "enable": true, "distance": 150, "color": "#00e5ff", "opacity": 0.3, "width": 1 }, "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false } },
                    "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true }, "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } }, "push": { "particles_nb": 4 } } },
                    "retina_detect": true
                });
            </script>
        </body>
        </html>
        <?php
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
        
        // Generate QR code as data URL
        $qrCodeImage = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrCodeUrl);
        ?>
        <!DOCTYPE html>
        <html lang="pt">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Configurar Autenticador - ClassLink</title>
            <link rel="stylesheet" href="/assets/theme.css">
            <script src="https://cdn.jsdelivr.net/npm/particles.js@2.0.0/particles.min.js"></script>
            <style>
                body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background-color: #0b132b; display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; }
                #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; }
                .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); }
                .qr-container { margin: 1rem auto; display: inline-block; }
                .qr-container img { border: 8px solid white; border-radius: 8px; }
                .form-group { margin-bottom: 1rem; }
                input { padding: 0.8rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-color); color: var(--text-color); width: 100%; box-sizing: border-box; }
                button { background-color: #24a1da; color: white; border: none; padding: 0.8rem; border-radius: 8px; font-size: 1rem; cursor: pointer; width: 100%; }
                button:hover { opacity: 0.9; }
                .error-msg { color: #ff3333; font-size: 0.9rem; margin-bottom: 1rem; }
                .info-msg { color: var(--text-color); background: rgba(255,255,255,0.1); padding: 0.75rem; margin-bottom: 1rem; border-radius: 8px; text-align: left; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.2); }
                .manual-code { font-family: monospace; font-size: 1.1rem; letter-spacing: 2px; background: rgba(255,255,255,0.1); padding: 0.5rem; border-radius: 4px; word-break: break-all; }
            </style>
        </head>
        <body>
            <div id="particles-js"></div>
            <div class="login-box">
                <h1>Configurar Autenticador</h1>
                <p class="small">Escaneie o código QR com a sua aplicação de autenticação ou introduza o código manualmente.</p>
                <?php if (!empty($localAuthError)): ?>
                    <div class="error-msg"><?= htmlspecialchars($localAuthError) ?></div>
                <?php endif; ?>
                
                <div class="qr-container">
                    <img src="<?= htmlspecialchars($qrCodeImage) ?>" alt="QR Code">
                </div>
                
                <div class="info-msg">
                    <strong>Código manual:</strong><br>
                    <span class="manual-code"><?= htmlspecialchars($secret) ?></span>
                </div>
                
                <form method="POST" action="/login/index.php">
                    <input type="hidden" name="action" value="verify_totp_setup">
                    <div class="form-group">
                        <input type="text" name="totp_code" placeholder="Código do autenticador" pattern="\d{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button type="submit">Validar e Ativar</button>
                </form>
            </div>
            <script>
                particlesJS("particles-js", {
                    "particles": { "number": { "value": 60, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#00e5ff" }, "shape": { "type": "circle" }, "opacity": { "value": 0.5, "random": false }, "size": { "value": 3, "random": true }, "line_linked": { "enable": true, "distance": 150, "color": "#00e5ff", "opacity": 0.3, "width": 1 }, "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false } },
                    "interactivity": { "detect_on": "canvas", "events": { "onhover": { "enable": true, "mode": "grab" }, "onclick": { "enable": true, "mode": "push" }, "resize": true }, "modes": { "grab": { "distance": 140, "line_linked": { "opacity": 1 } }, "push": { "particles_nb": 4 } } },
                    "retina_detect": true
                });
            </script>
        </body>
        </html>
        <?php
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
                body { margin: 0; height: 100vh; font-family: "Segoe UI", sans-serif; background-color: #0b132b; display: flex; justify-content: center; align-items: center; flex-direction: column; color: var(--text-color); overflow: hidden; position: relative; }
                #particles-js { position: absolute; width: 100%; height: 100%; top: 0; left: 0; z-index: 1; }
                .login-box { background: var(--white-overlay); padding: 2rem 3rem; border-radius: 16px; box-shadow: 0 4px 20px var(--shadow-color); text-align: center; max-width: 350px; width: 100%; z-index: 2; position: relative; backdrop-filter: blur(10px); }
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
                    <input type="hidden" name="action" value="send_code">
                    <div class="form-group">
                        <input type="email" name="email" placeholder="Endereço Eletrónico" value="<?= htmlspecialchars($emailValue) ?>" required>
                    </div>
                    <button type="submit">Enviar código</button>
                </form>
                <?php else: ?>
                <form method="POST" action="/login/index.php">
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
                    "particles": { "number": { "value": 60, "density": { "enable": true, "value_area": 800 } }, "color": { "value": "#00e5ff" }, "shape": { "type": "circle" }, "opacity": { "value": 0.5, "random": false }, "size": { "value": 3, "random": true }, "line_linked": { "enable": true, "distance": 150, "color": "#00e5ff", "opacity": 0.3, "width": 1 }, "move": { "enable": true, "speed": 2, "direction": "none", "random": false, "straight": false, "out_mode": "out", "bounce": false } },
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
        // Devolver página de Login ClassLink
        echo "<!DOCTYPE html>";
        echo "<html lang=\"pt\">";
        echo "<head>";
        echo "<meta charset=\"UTF-8\">";
        echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
        echo "<title>Iniciar Sessão - ClassLink</title>";
        echo "<link rel=\"stylesheet\" href=\"/assets/theme.css\">";
        echo "<style>";
        echo "body {";
        echo "margin: 0;";
        echo "height: 100vh;";
        echo "font-family: \"Segoe UI\", sans-serif;";
        echo "background: var(--bg-gradient);";
        echo "display: flex;";
        echo "justify-content: center;";
        echo "align-items: center;";
        echo "flex-direction: column;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-box {";
        echo "background: var(--white-overlay);";
        echo "padding: 2rem 3rem;";
        echo "border-radius: 16px;";
        echo "box-shadow: 0 4px 20px var(--shadow-color);";
        echo "text-align: center;";
        echo "max-width: 350px;";
        echo "width: 100%;";
        echo "}";
        echo "";
        echo ".login-box h1 {";
        echo "font-size: 1.4rem;";
        echo "margin-bottom: 1.5rem;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-box .small {";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-btn {";
        echo "display: inline-block;";
        echo "background-color: #2F2F2F;";
        echo "color: white;";
        echo "text-decoration: none;";
        echo "padding: 0.8rem 1.2rem;";
        echo "border-radius: 8px;";
        echo "font-size: 1rem;";
        echo "font-weight: 500;";
        echo "transition: background 0.2s;";
        echo "}";
        echo "";
        echo ".login-btn:hover {";
        echo "background-color: #1b1b1b;";
        echo "}";
        echo "";
        echo ".notice {";
        echo "position: absolute;";
        echo "bottom: 20px;";
        echo "background: var(--white-overlay-light);";
        echo "padding: 1rem 1.5rem;";
        echo "border-radius: 10px;";
        echo "font-size: 0.9rem;";
        echo "box-shadow: 0 2px 8px var(--shadow-color);";
        echo "text-align: center;";
        echo "max-width: 400px;";
        echo "line-height: 1.4;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".notice strong {";
        echo "display: block;";
        echo "margin-bottom: 4px;";
        echo "}";
        echo "";
        echo ".tooltip {";
        echo "position: relative;";
        echo "display: inline-block;";
        echo "cursor: help;";
        echo "color: var(--input-focus-color);";
        echo "text-decoration: underline;";
        echo "}";
        echo "";
        echo ".tooltip .tooltip-text {";
        echo "visibility: hidden;";
        echo "opacity: 0;";
        echo "width: 280px;";
        echo "background-color: #333;";
        echo "color: #fff;";
        echo "text-align: center;";
        echo "border-radius: 8px;";
        echo "padding: 0.6rem;";
        echo "position: absolute;";
        echo "bottom: 125%;";
        echo "left: 50%;";
        echo "transform: translateX(-50%);";
        echo "transition: opacity 0.3s;";
        echo "font-size: 0.85rem;";
        echo "line-height: 1.3;";
        echo "z-index: 10;";
        echo "}";
        echo "";
        echo ".tooltip .tooltip-text::after {";
        echo "content: \"\";";
        echo "position: absolute;";
        echo "top: 100%;";
        echo "left: 50%;";
        echo "margin-left: -5px;";
        echo "border-width: 5px;";
        echo "border-style: solid;";
        echo "border-color: #333 transparent transparent transparent;";
        echo "}";
        echo "";
        echo ".tooltip:hover .tooltip-text {";
        echo "visibility: visible;";
        echo "opacity: 1;";
        echo "}";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "";
        echo "<div class=\"login-box\">";
        echo "<img src=\"/assets/logo.png\" alt=\"Logotipo ClassLink\" style=\"max-width:25%;\">";
        echo "<h1>Terminou sessão</h1>";
        echo "<p class=\"small\">Caso pretenda voltar a iniciar sessão, carregue no botão em baixo.</p>";
        echo "<a href=\"/login\" class=\"login-btn\">Iniciar Sessão</a>";
        echo "</div>";
        echo "";
        
        echo "<span class=\"tooltip\">Como é que sei?";
        echo "<span class=\"tooltip-text\">";
        echo "";
        echo "</span>";
        echo "</span>";
        echo "</div>";
        echo "";
        echo "<div style=\"text-align:center;margin-top:1rem;color:var(--text-color);opacity:0.6;font-size:0.8rem;\">";
        echo get_app_config("brand_name", "ClassLink");
        echo "</div>";
        echo "";
        echo "</body>";
        echo "</html>";
        echo "";
        die();
    } else if (isset($_GET['error'])) {
	?>
<?php
    }else if (isset($_GET['code'])){        $now = time();        try {
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
            // Skip the current Microsoft ID since it was just created
            $stmt = $db->prepare("SELECT id FROM cache WHERE email = ? AND id != ?");
            $stmt->bind_param("ss", $_SESSION['email'], $_SESSION['id']);
            $stmt->execute();
            $preRegisteredUser = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($preRegisteredUser) {
                // User was pre-registered, migrate to the real OAuth2 ID
                // Use a transaction to ensure atomicity
                $db->begin_transaction();
                try {
                    // Get the admin status from the pre-registered user
                    $stmt = $db->prepare("SELECT admin FROM cache WHERE id = ?");
                    $stmt->bind_param("s", $preRegisteredUser['id']);
                    $stmt->execute();
                    $preRegData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $adminStatus = $preRegData['admin'] ?? 0;
                    
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
                    echo "<link rel=\"stylesheet\" href=\"/assets/theme.css\">";
                    echo "<style>";
                    echo "body {";
                    echo "margin: 0;";
                    echo "height: 100vh;";
                    echo "font-family: \"Segoe UI\", sans-serif;";
                    echo "background: var(--bg-gradient);";
                    echo "display: flex;";
                    echo "justify-content: center;";
                    echo "align-items: center;";
                    echo "flex-direction: column;";
                    echo "color: var(--text-color);";
                    echo "}";
                    echo "";
                    echo ".login-box {";
                    echo "background: var(--white-overlay);";
                    echo "padding: 2rem 3rem;";
                    echo "border-radius: 16px;";
                    echo "box-shadow: 0 4px 20px var(--shadow-color);";
                    echo "text-align: center;";
                    echo "max-width: 350px;";
                    echo "width: 100%;";
                    echo "}";
                    echo "";
                    echo ".login-box h1 {";
                    echo "font-size: 1.4rem;";
                    echo "margin-bottom: 1.5rem;";
                    echo "color: var(--text-color);";
                    echo "}";
                    echo "";
                    echo ".login-box .small {";
                    echo "color: var(--text-color);";
                    echo "}";
                    echo "";
                    echo ".login-btn {";
                    echo "display: inline-block;";
                    echo "background-color: #2F2F2F;";
                    echo "color: white;";
                    echo "text-decoration: none;";
                    echo "padding: 0.8rem 1.2rem;";
                    echo "border-radius: 8px;";
                    echo "font-size: 1rem;";
                    echo "font-weight: 500;";
                    echo "transition: background 0.2s;";
                    echo "}";
                    echo "";
                    echo ".login-btn:hover {";
                    echo "background-color: #1b1b1b;";
                    echo "}";
                    echo "";
                    echo ".notice {";
                    echo "position: absolute;";
                    echo "bottom: 20px;";
                    echo "background: var(--white-overlay-light);";
                    echo "padding: 1rem 1.5rem;";
                    echo "border-radius: 10px;";
                    echo "font-size: 0.9rem;";
                    echo "box-shadow: 0 2px 8px var(--shadow-color);";
                    echo "text-align: center;";
                    echo "max-width: 400px;";
                    echo "line-height: 1.4;";
                    echo "color: var(--text-color);";
                    echo "}";
                    echo "";
                    echo ".notice strong {";
                    echo "display: block;";
                    echo "margin-bottom: 4px;";
                    echo "}";
                    echo "";
                    echo ".tooltip {";
                    echo "position: relative;";
                    echo "display: inline-block;";
                    echo "cursor: help;";
                    echo "color: var(--input-focus-color);";
                    echo "text-decoration: underline;";
                    echo "}";
                    echo "";
                    echo ".tooltip .tooltip-text {";
                    echo "visibility: hidden;";
                    echo "opacity: 0;";
                    echo "width: 280px;";
                    echo "background-color: #333;";
                    echo "color: #fff;";
                    echo "text-align: center;";
                    echo "border-radius: 8px;";
                    echo "padding: 0.6rem;";
                    echo "position: absolute;";
                    echo "bottom: 125%;";
                    echo "left: 50%;";
                    echo "transform: translateX(-50%);";
                    echo "transition: opacity 0.3s;";
                    echo "font-size: 0.85rem;";
                    echo "line-height: 1.3;";
                    echo "z-index: 10;";
                    echo "}";
                    echo "";
                    echo ".tooltip .tooltip-text::after {";
                    echo "content: \"\";";
                    echo "position: absolute;";
                    echo "top: 100%;";
                    echo "left: 50%;";
                    echo "margin-left: -5px;";
                    echo "border-width: 5px;";
                    echo "border-style: solid;";
                    echo "border-color: #333 transparent transparent transparent;";
                    echo "}";
                    echo "";
                    echo ".tooltip:hover .tooltip-text {";
                    echo "visibility: visible;";
                    echo "opacity: 1;";
                    echo "}";
                    echo "</style>";
                    echo "</head>";
                    echo "<body>";
                    echo "";
                    echo "<div class=\"login-box\">";
                    echo "<img src=\"/assets/logo.png\" alt=\"Logotipo ClassLink\" style=\"max-width:25%;\">";
                    echo "<h1>Sem permissão</h1>";
                    echo "<p class=\"small\">Iniciou sessão com uma conta de aluno (detectado automaticamente por um filtro). Contacte o administrador do sistema.</p>";
                    echo "<a href=\"/login\" class=\"login-btn\">Voltar atrás</a>";
                    echo "</div>";
                    echo "";
                    echo "<div class=\"notice\">";
                    echo "";
                    echo "<span class=\"tooltip\">Como é que sei?";
                    echo "<span class=\"tooltip-text\">";
                    echo "";
                    echo "</span>";
                    echo "</span>";
                    echo "</div>";
                    echo "";
                    echo "</body>";
                    echo "</html>";
                    echo "";
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
                        // Admin has no TOTP configured - redirect to setup
                        $_SESSION['pending_totp_user'] = [
                            'id' => $_SESSION['id'],
                            'nome' => $_SESSION['nome'],
                            'email' => $_SESSION['email'],
                            'admin' => true
                        ];
                        header('Location: /login?step=totp_setup');
                        exit();
                    }

                    // Valid admin with TOTP: redirect to TOTP verification step
                    $_SESSION['pending_totp_user'] = [
                        'id' => $_SESSION['id'],
                        'nome' => $_SESSION['nome'],
                        'email' => $_SESSION['email'],
                        'admin' => true
                    ];
                    header('Location: /login?step=totp');
                    exit();
                }
            }

            header('Location: /');
            exit();
        } catch (\League\OAuth2\Client\Provider\Exception\IdentityProviderException $e) {
            // Failed to get the access token or user details.
            if ($e->getMessage() == 'invalid_grant') {
                session_destroy();
                header('Location: /login/');
            }
            exit($e->getMessage());
        }
    } else if (str_starts_with($_SERVER['REQUEST_URI'], "/login") && $_GET['redirecttoflow']) {
	    $scopes = [
    		'scope' => ['openid profile email']
    	];
        $authorizationUrl = $provider->getAuthorizationUrl($scopes);
        $_SESSION['oauth2state'] = $provider->getState();
        header('Location: ' . $authorizationUrl);
    } else {
        // Devolver página de Login ClassLink
        echo "<!DOCTYPE html>";
        echo "<html lang=\"pt\">";
        echo "<head>";
        echo "<meta charset=\"UTF-8\">";
        echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">";
        echo "<title>Iniciar Sessão - ClassLink</title>";
        echo "<link rel=\"stylesheet\" href=\"/assets/theme.css\">";
        echo "<style>";
        echo "body {";
        echo "margin: 0;";
        echo "height: 100vh;";
        echo "font-family: \"Segoe UI\", sans-serif;";
        echo "background: var(--bg-gradient);";
        echo "display: flex;";
        echo "justify-content: center;";
        echo "align-items: center;";
        echo "flex-direction: column;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-box {";
        echo "background: var(--white-overlay);";
        echo "padding: 2rem 3rem;";
        echo "border-radius: 16px;";
        echo "box-shadow: 0 4px 20px var(--shadow-color);";
        echo "text-align: center;";
        echo "max-width: 350px;";
        echo "width: 100%;";
        echo "}";
        echo "";
        echo ".login-box h1 {";
        echo "font-size: 1.4rem;";
        echo "margin-bottom: 1.5rem;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-box .small {";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".login-btn {";
        echo "display: inline-block;";
        echo "background-color: #2F2F2F;";
        echo "color: white;";
        echo "text-decoration: none;";
        echo "padding: 0.8rem 1.2rem;";
        echo "border-radius: 8px;";
        echo "font-size: 1rem;";
        echo "font-weight: 500;";
        echo "transition: background 0.2s;";
        echo "}";
        echo "";
        echo ".login-btn:hover {";
        echo "background-color: #1b1b1b;";
        echo "}";
        echo "";
        echo ".notice {";
        echo "position: absolute;";
        echo "bottom: 20px;";
        echo "background: var(--white-overlay-light);";
        echo "padding: 1rem 1.5rem;";
        echo "border-radius: 10px;";
        echo "font-size: 0.9rem;";
        echo "box-shadow: 0 2px 8px var(--shadow-color);";
        echo "text-align: center;";
        echo "max-width: 400px;";
        echo "line-height: 1.4;";
        echo "color: var(--text-color);";
        echo "}";
        echo "";
        echo ".notice strong {";
        echo "display: block;";
        echo "margin-bottom: 4px;";
        echo "}";
        echo "";
        echo ".tooltip {";
        echo "position: relative;";
        echo "display: inline-block;";
        echo "cursor: help;";
        echo "color: var(--input-focus-color);";
        echo "text-decoration: underline;";
        echo "}";
        echo "";
        echo ".tooltip .tooltip-text {";
        echo "visibility: hidden;";
        echo "opacity: 0;";
        echo "width: 280px;";
        echo "background-color: #333;";
        echo "color: #fff;";
        echo "text-align: center;";
        echo "border-radius: 8px;";
        echo "padding: 0.6rem;";
        echo "position: absolute;";
        echo "bottom: 125%;";
        echo "left: 50%;";
        echo "transform: translateX(-50%);";
        echo "transition: opacity 0.3s;";
        echo "font-size: 0.85rem;";
        echo "line-height: 1.3;";
        echo "z-index: 10;";
        echo "}";
        echo "";
        echo ".tooltip .tooltip-text::after {";
        echo "content: \"\";";
        echo "position: absolute;";
        echo "top: 100%;";
        echo "left: 50%;";
        echo "margin-left: -5px;";
        echo "border-width: 5px;";
        echo "border-style: solid;";
        echo "border-color: #333 transparent transparent transparent;";
        echo "}";
        echo "";
        echo ".tooltip:hover .tooltip-text {";
        echo "visibility: visible;";
        echo "opacity: 1;";
        echo "}";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "";
        echo "<div class=\"login-box\">";
        echo "<img src=\"/assets/logo.png\" alt=\"Logotipo ClassLink\" style=\"max-width:25%;\">";
        echo "<h1>Iniciar Sessão no ClassLink</h1>";
        echo "<p class=\"small\">Para aceder à plataforma, deve autenticar-se com a sua conta institucional.</p>";
        echo "<a href=\"/login?redirecttoflow=1\" class=\"login-btn\">";
        $isMicrosoft = str_starts_with($provider->getBaseAuthorizationUrl(), 'https://login.microsoftonline.com/');
        if ($isMicrosoft) {
            echo "<svg class=\"ms-logo\" width=\"21\" height=\"21\" viewBox=\"0 0 21 21\" xmlns=\"http://www.w3.org/2000/svg\" style=\"vertical-align: middle; margin-right: 8px;\">";
            echo "<rect class=\"ms-rect1\" x=\"1\" y=\"1\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect2\" x=\"11\" y=\"1\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect3\" x=\"1\" y=\"11\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect4\" x=\"11\" y=\"11\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "</svg>";
            echo "Iniciar Sessão com Microsoft";
        } else {
            echo "Iniciar Sessão com Fornecedor de Identidade";
        }
        echo "</a>";
        if ($isMicrosoft) {
            echo "<style>";
            echo ".login-btn:hover .ms-rect1 { fill: #f25022; }";
            echo ".login-btn:hover .ms-rect2 { fill: #7fba00; }";
            echo ".login-btn:hover .ms-rect3 { fill: #00a4ef; }";
            echo ".login-btn:hover .ms-rect4 { fill: #ffb900; }";
            echo "</style>";
        }
        echo "</div>";
        echo "";
        
        echo "<span class=\"tooltip\">Como é que sei?";
        echo "<span class=\"tooltip-text\">";
        echo "";
        echo "</span>";
        echo "</span>";
        echo "</div>";
        echo "";
        echo "<div style=\"text-align:center;margin-top:1rem;color:var(--text-color);opacity:0.6;font-size:0.8rem;\">";
        echo get_app_config("brand_name", "ClassLink");
        echo "</div>";
        echo "";
        echo "</body>";
        echo "</html>";
        echo "";
        die();
    }
?>
