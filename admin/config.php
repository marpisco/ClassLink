<?php require 'index.php'; ?>
<?php require_once(__DIR__ . '/../func/genuuid.php'); ?>
<?php require_once(__DIR__ . '/../func/get_config.php'); ?>

<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
    <h3>Configurações do Sistema</h3>
    
<?php
// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        // Update multiple settings
        $keys = [
            'brand_name',
            'internal_email_domain', 
            'admin_requires_totp',
            'blocked_emails_regex',
            'email_account_name'
        ];
        
        $successCount = 0;
        foreach ($keys as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                $stmt = $db->prepare("INSERT INTO config (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = ?");
                if ($stmt) {
                    $stmt->bind_param("sss", $key, $value, $value);
                    if ($stmt->execute()) {
                        $successCount++;
                    }
                    $stmt->close();
                }
            }
        }
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success fade show' role='alert'>Configurações guardadas com sucesso!</div>";
            acaoexecutada("Atualização de configurações do sistema");
        } else {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro ao guardar configurações.</div>";
        }
    }
}

// Get current values
$brandName = get_app_config('brand_name', 'ClassLink');
$internalDomain = get_app_config('internal_email_domain', '');
$adminRequiresTotp = get_app_config('admin_requires_totp', 'true');
$blockedEmailsRegex = get_app_config('blocked_emails_regex', '/^a\d+@.+$/i');
$emailAccountName = get_app_config('email_account_name', 'ClassLink');
?>

    <form method="POST" action="/admin/config.php">
        <input type="hidden" name="action" value="update_settings">
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Identidade e Branding</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="brand_name" class="form-label">Nome da Aplicação</label>
                        <input type="text" class="form-control" id="brand_name" name="brand_name" value="<?= htmlspecialchars($brandName) ?>" placeholder="ClassLink">
                        <div class="form-text">Nome apresentado no login e no cabeçalho.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email_account_name" class="form-label">Nome da Conta de Email</label>
                        <input type="text" class="form-control" id="email_account_name" name="email_account_name" value="<?= htmlspecialchars($emailAccountName) ?>" placeholder="ClassLink">
                        <div class="form-text">Nome apresentado nos emails enviados.</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Autenticação e Segurança</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="internal_email_domain" class="form-label">Domínio de Email Interno</label>
                        <input type="text" class="form-control" id="internal_email_domain" name="internal_email_domain" value="<?= htmlspecialchars($internalDomain) ?>" placeholder="exemplo.pt">
                        <div class="form-text">Domínio para reservas autónomas (sem aprovação). Deixe vazio para desativar.</div>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label for="blocked_emails_regex" class="form-label">Regex de Emails Bloqueados</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="blocked_emails_regex" name="blocked_emails_regex" value="<?= htmlspecialchars($blockedEmailsRegex) ?>" placeholder="/^a\d+@exemplo\.org$/i">
                            <button type="button" class="btn btn-outline-secondary" onclick="testRegex()">Testar</button>
                        </div>
                        <div class="form-text">Expressão regular para bloquear emails específicos.</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="admin_requires_totp" name="admin_requires_totp" value="true" <?= filter_var($adminRequiresTotp, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="admin_requires_totp">
                                <strong>Exigir TOTP para Administradores</strong>
                            </label>
                            <div class="form-text">Se ativado, todos os administradores devem configurar e usar TOTP (autenticador) para iniciar sessão.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="submit" class="btn btn-primary">Guardar Alterações</button>
        </div>
    </form>
</div>

<style>
.card {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.card-header {
    font-weight: 500;
}
.regex-test-result {
    margin-top: 8px;
    padding: 8px;
    border-radius: 4px;
    display: none;
}
.regex-test-result.valid {
    display: block;
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.regex-test-result.invalid {
    display: block;
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
.regex-test-modal .modal-body input {
    margin-bottom: 10px;
}
</style>

<script>
function testRegex() {
    const regexInput = document.getElementById('blocked_emails_regex').value;
    if (!regexInput.trim()) {
        alert('Por favor, insira uma regex para testar.');
        return;
    }
    
    // Show modal with test input
    const testEmail = prompt('Introduza um email para testar a regex:\n' + regexInput);
    if (!testEmail) return;
    
    try {
        const regex = new RegExp(regexInput);
        const matches = regex.test(testEmail);
        
        if (matches) {
            alert('✅ O email "' + testEmail + '" CORRESPONDE à regex.\n\nSerÁ BLOQUEADO.');
        } else {
            alert('❌ O email "' + testEmail + '" NÃO corresponde à regex.\n\nSerá PERMITIDO.');
        }
    } catch (e) {
        alert('Erro na regex: ' + e.message);
    }
}
</script>