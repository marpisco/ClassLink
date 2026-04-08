<?php
    require_once(__DIR__ . '/../src/config.php');
    require_once(__DIR__ . '/../src/db.php');
    
    session_start();
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
        echo "background: url(\"/assets/aejicsbg.png\") no-repeat center center/cover;";
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
        echo "<div class=\"notice\">";
        echo "<strong>Este website é oficial do Agrupamento de Escolas Joaquim Inácio da Cruz Sobral.</strong>";
        echo "<span class=\"tooltip\">Como é que sei?";
        echo "<span class=\"tooltip-text\">";
        echo "Este website está no domínio <b>.aejics.org</b>, o domínio oficial do AEJICS.";
        echo "</span>";
        echo "</span>";
        echo "</div>";
        echo "";
        echo "</body>";
        echo "</html>";
        echo "";
        die();
    } else if (isset($_GET['error'])) {
	?>
<?php
    }else if (isset($_GET['code'])){
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

            // Check if there's a pre-registered user with this email (id starts with PRE_REGISTERED_PREFIX)
            $prePattern = PRE_REGISTERED_PREFIX . '%';
            $stmt = $db->prepare("SELECT id FROM cache WHERE email = ? AND id LIKE ?");
            $stmt->bind_param("ss", $_SESSION['email'], $prePattern);
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
            // regex de alunos atualizado (06/12/2025): ^a\d+@aejics\.org$
            if (!$_SESSION['admin']) {
                $alunoRegex = '/^a\d+@aejics\.org$/i';
                if (preg_match($alunoRegex, $_SESSION['email'])) {
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
                    echo "background: url(\"/assets/aejicsbg.png\") no-repeat center center/cover;";
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
                    echo "<strong>Este website é oficial do Agrupamento de Escolas Joaquim Inácio da Cruz Sobral.</strong>";
                    echo "<span class=\"tooltip\">Como é que sei?";
                    echo "<span class=\"tooltip-text\">";
                    echo "Este website está no domínio <b>.aejics.org</b>, o domínio oficial do AEJICS.";
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
        echo "background: url(\"/assets/aejicsbg.png\") no-repeat center center/cover;";
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
        if (isset($authProvider) && $authProvider === 'Microsoft') {
            echo "<svg class=\"ms-logo\" width=\"21\" height=\"21\" viewBox=\"0 0 21 21\" xmlns=\"http://www.w3.org/2000/svg\" style=\"vertical-align: middle; margin-right: 8px;\">";
            echo "<rect class=\"ms-rect1\" x=\"1\" y=\"1\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect2\" x=\"11\" y=\"1\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect3\" x=\"1\" y=\"11\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "<rect class=\"ms-rect4\" x=\"11\" y=\"11\" width=\"9\" height=\"9\" fill=\"white\"/>";
            echo "</svg>";
            echo "Iniciar Sessão com Microsoft";
        } else {
            $providerName = isset($authProvider) ? htmlspecialchars($authProvider, ENT_QUOTES, 'UTF-8') : 'OAuth';
            echo "Iniciar Sessão com Fornecedor de Identidade " . $providerName;
        }
        echo "</a>";
        if (isset($authProvider) && $authProvider === 'Microsoft') {
            echo "<style>";
            echo ".login-btn:hover .ms-rect1 { fill: #f25022; }";
            echo ".login-btn:hover .ms-rect2 { fill: #7fba00; }";
            echo ".login-btn:hover .ms-rect3 { fill: #00a4ef; }";
            echo ".login-btn:hover .ms-rect4 { fill: #ffb900; }";
            echo "</style>";
        }
        echo "</div>";
        echo "";
        echo "<div class=\"notice\">";
        echo "<strong>Este website é oficial do Agrupamento de Escolas Joaquim Inácio da Cruz Sobral.</strong>";
        echo "<span class=\"tooltip\">Como é que sei?";
        echo "<span class=\"tooltip-text\">";
        echo "Este website está no domínio <b>.aejics.org</b>, o domínio oficial do AEJICS.";
        echo "</span>";
        echo "</span>";
        echo "</div>";
        echo "";
        echo "</body>";
        echo "</html>";
        echo "";
        die();
    }
?>
