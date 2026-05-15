<?php
    require_once(__DIR__ . '/../vendor/autoload.php');
    require_once(__DIR__ . '/config.php');
    
    // Start session to check if user has selected a different database
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user has selected a different database in this session
    $selectedDb = $_SESSION['selected_db'] ?? $db['db'];
    $db = new mysqli($db['servidor'], $db['user'], $db['password'], $selectedDb, $db['porta']);
    if ($db->connect_error) {
        die("Ligação ao servidor falhou: " . $db->connect_error);
    }
    $db->set_charset("utf8mb4");

    // Criar bases de dados. Todas.
    $db->query("CREATE TABLE IF NOT EXISTS cache (id VARCHAR(99) UNIQUE, nome VARCHAR(99), email VARCHAR(99), admin BOOL, PRIMARY KEY (id));");
    
    // Add TOTP and OTP columns to cache table for existing installations
    $result = $db->query("SHOW COLUMNS FROM cache LIKE 'totp_secret'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE cache ADD COLUMN totp_secret VARCHAR(255);");
    }
    $result = $db->query("SHOW COLUMNS FROM cache LIKE 'otp_code_hash'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE cache ADD COLUMN otp_code_hash VARCHAR(255);");
    }
    $result = $db->query("SHOW COLUMNS FROM cache LIKE 'otp_expires'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE cache ADD COLUMN otp_expires DATETIME;");
    }
    
    // Create salas table with post_reservation_content, tipo_sala, and bloqueado columns for new installations
    // tipo_sala: 1 = normal (requires approval), 2 = autonomous (auto-approved)
    // bloqueado: 0 = not locked (default), 1 = locked (only admins can create reservations)
    $db->query("CREATE TABLE IF NOT EXISTS salas (id VARCHAR(99) UNIQUE, nome VARCHAR(99), post_reservation_content TEXT, tipo_sala INT DEFAULT 1, bloqueado INT DEFAULT 0, PRIMARY KEY (id));");
    
    // For existing installations, add the column if it doesn't exist
    // This check is safe because CREATE TABLE IF NOT EXISTS ensures the table exists
    $result = $db->query("SHOW COLUMNS FROM salas LIKE 'post_reservation_content'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE salas ADD COLUMN post_reservation_content TEXT;");
    }
    
    // Add tipo_sala column for existing installations
    $result = $db->query("SHOW COLUMNS FROM salas LIKE 'tipo_sala'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE salas ADD COLUMN tipo_sala INT DEFAULT 1;");
    }
    
    // Add bloqueado column for existing installations
    // bloqueado: 0 = not locked (default), 1 = locked (only admins can create reservations)
    $result = $db->query("SHOW COLUMNS FROM salas LIKE 'bloqueado'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE salas ADD COLUMN bloqueado INT DEFAULT 0;");
    }
    
    $db->query("CREATE TABLE IF NOT EXISTS tempos (id VARCHAR(99) UNIQUE, horashumanos VARCHAR(99), PRIMARY KEY (id));");
    $db->query("CREATE TABLE IF NOT EXISTS reservas (sala VARCHAR(99) NOT NULL, tempo VARCHAR(99) NOT NULL, requisitor VARCHAR(99) NOT NULL, data DATE NOT NULL, aprovado BOOLEAN, motivo VARCHAR(99), extra VARCHAR(9999), UNIQUE (sala, tempo, data), FOREIGN KEY (tempo) REFERENCES tempos(id), FOREIGN KEY (sala) REFERENCES salas(id), FOREIGN KEY (requisitor) REFERENCES cache(id));");
    $db->query("CREATE TABLE IF NOT EXISTS logs (id VARCHAR(99), loginfo VARCHAR(9999), userid VARCHAR(99), ip_address VARCHAR(45), timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), FOREIGN KEY (userid) REFERENCES cache(id));");
    
    // Add ip_address column to logs table for existing installations
    $result = $db->query("SHOW COLUMNS FROM logs LIKE 'ip_address'");
    if ($result && $result->num_rows == 0) {
        $db->query("ALTER TABLE logs ADD COLUMN ip_address VARCHAR(45);");
    }
    
    // Create materials table
    $db->query("CREATE TABLE IF NOT EXISTS materiais (id VARCHAR(99) UNIQUE, nome VARCHAR(255), descricao TEXT, sala_id VARCHAR(99), PRIMARY KEY (id), FOREIGN KEY (sala_id) REFERENCES salas(id) ON DELETE CASCADE);");
    
    // Create junction table for reservations and materials
    $db->query("CREATE TABLE IF NOT EXISTS reservas_materiais (reserva_sala VARCHAR(99) NOT NULL, reserva_tempo VARCHAR(99) NOT NULL, reserva_data DATE NOT NULL, material_id VARCHAR(99) NOT NULL, PRIMARY KEY (reserva_sala, reserva_tempo, reserva_data, material_id), FOREIGN KEY (reserva_sala, reserva_tempo, reserva_data) REFERENCES reservas(sala, tempo, data) ON DELETE CASCADE, FOREIGN KEY (material_id) REFERENCES materiais(id) ON DELETE CASCADE);");

    // Create config table for application settings
    $db->query("CREATE TABLE IF NOT EXISTS config (config_key VARCHAR(99) UNIQUE, config_value TEXT, PRIMARY KEY (config_key));");
    
    // Insert default config values if not exist
    $defaultConfigs = [
        'brand_name' => 'ClassLink',
        'internal_email_domain' => '',
        'admin_requires_totp' => 'true',
        'blocked_emails_regex' => '/^a\d+@.+$/i',
        'email_account_name' => 'ClassLink',
        'initial_setup_complete' => 'false',
        'app_mode' => 'production',
        'db_configs' => json_encode([
            'production' => ['name' => 'Produção', 'db' => 'reservasalas', 'user' => 'reservasalas'],
            'development' => ['name' => 'Desenvolvimento', 'db' => 'reservasalas_dev', 'user' => 'reservasalas']
        ])
    ];
    
    foreach ($defaultConfigs as $key => $value) {
        $stmt = $db->prepare("INSERT IGNORE INTO config (config_key, config_value) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("ss", $key, $value);
            $stmt->execute();
            $stmt->close();
        }
    }
    
    // Check if this is first run (no users in cache)
    $userCountResult = $db->query("SELECT COUNT(*) as count FROM cache");
    $userCount = $userCountResult->fetch_assoc()['count'];
    
    // Store whether this is first run in a constant for use elsewhere
    define('IS_FIRST_RUN', $userCount == 0);
    
    // Constant for pre-registered user ID prefix
    define('PRE_REGISTERED_PREFIX', 'pre_');
?>