<?php require 'index.php'; ?>
<?php require_once(__DIR__ . '/../func/genuuid.php'); ?>
<?php require_once(__DIR__ . '/../func/get_config.php'); ?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão de Utilizadores</h3>
<?php
switch (isset($_GET['action']) ? $_GET['action'] : null){
    // caso execute a ação apagar:
    case "apagar":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        try {
            $stmt = $db->prepare("SELECT * FROM reservas WHERE requisitor = ? AND aprovado != -1");
            $stmt->bind_param("s", $_GET['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Existem reservas associadas a este utilizador. Por segurança, é necessária uma intervenção manual.");
            }
            $stmt->close();
        } catch (Exception $e) {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            break;
        }
        $stmt = $db->prepare("DELETE FROM cache WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Eliminação de Utilizador");
        break;
    // caso execute a ação editar:
    case "edit":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        $stmt = $db->prepare("SELECT * FROM cache WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$d) {
            echo "<div class='alert alert-danger fade show' role='alert'>Utilizador não encontrado.</div>";
            break;
        }
        echo "<div class='alert alert-warning fade show' role='alert'>A editar o Utilizador <b>" . htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') . "</b>.</div>";
        formulario("users.php?action=update&id=" . urlencode($d['id']), [
            ["type" => "text", "id" => "nome", "placeholder" => "Nome", "label" => "Nome", "value" => $d['nome']],
            ["type" => "email", "id" => "email", "placeholder" => "Email", "label" => "Email", "value" => $d['email']],
            ["type" => "checkbox", "id" => "administrador", "placeholder" => "Admin", "label" => "Administrador", "value" => $d['admin'] ? "1" : "0"]
        ]);
        break;
    // caso seja submetida a edição:
    case "update":
        if (!isset($_GET['id']) || !isset($_POST['nome']) || !isset($_POST['email'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        
        // Get current admin status before update
        $checkStmt = $db->prepare("SELECT admin, totp_secret FROM cache WHERE id = ?");
        $checkStmt->bind_param("s", $_GET['id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result()->fetch_assoc();
        $checkStmt->close();
        
        $wasAdmin = $checkResult['admin'] ?? 0;
        $adminValue = isset($_POST["administrador"]) ? 1 : 0;
        
        // If demoting from admin to non-admin, remove TOTP
        if ($wasAdmin == 1 && $adminValue == 0 && !empty($checkResult['totp_secret'])) {
            $stmt = $db->prepare("UPDATE cache SET nome = ?, email = ?, admin = ?, totp_secret = NULL WHERE id = ?");
            $stmt->bind_param("sssi", $_POST['nome'], $_POST['email'], $adminValue, $_GET['id']);
            $stmt->execute();
            $stmt->close();
            acaoexecutada("Atualização de Utilizador - Removido TOTP por perda de privilégios admin");
        } else {
            $stmt = $db->prepare("UPDATE cache SET nome = ?, email = ?, admin = ? WHERE id = ?");
            $stmt->bind_param("sssi", $_POST['nome'], $_POST['email'], $adminValue, $_GET['id']);
            $stmt->execute();
            $stmt->close();
            acaoexecutada("Atualização de Utilizador");
        }
        break;
    // caso execute a ação remover TOTP:
    case "removetotp":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        $stmt = $db->prepare("UPDATE cache SET totp_secret = NULL WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Remoção de TOTP de utilizador");
        echo "<div class='alert alert-success fade show' role='alert'>TOTP removido com sucesso.</div>";
        break;
    // caso execute a ação pré-adicionar:
    case "preadd":
        if (!isset($_POST['nome']) || !isset($_POST['email']) || empty($_POST['nome']) || empty($_POST['email'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Nome e Email são obrigatórios.</div>";
            break;
        }
        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            echo "<div class='alert alert-danger fade show' role='alert'>Formato de email inválido.</div>";
            break;
        }
        // Check if email already exists
        $stmt = $db->prepare("SELECT id FROM cache WHERE email = ?");
        $stmt->bind_param("s", $_POST['email']);
        $stmt->execute();
        $existingUser = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existingUser) {
            echo "<div class='alert alert-danger fade show' role='alert'>Já existe um utilizador com este email.</div>";
            break;
        }
        // Generate a temporary ID with pre-registered prefix for pre-registered users
        $tempId = PRE_REGISTERED_PREFIX . uuid4();
        $stmt = $db->prepare("INSERT INTO cache (id, nome, email, admin) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("sss", $tempId, $_POST['nome'], $_POST['email']);
        if ($stmt->execute()) {
            echo "<div class='alert alert-success fade show' role='alert'>Utilizador pré-adicionado com sucesso.</div>";
            acaoexecutada("Pré-adição de Utilizador");
        } else {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro ao pré-adicionar utilizador.</div>";
        }
        $stmt->close();
        break;
}

// Get total count for display
$totalResult = $db->query("SELECT COUNT(*) as total FROM cache");
$numUtilizadores = $totalResult->fetch_assoc()['total'];

// Get first 20 users
$utilizadores = $db->query("SELECT * FROM cache ORDER BY nome ASC LIMIT 20;");
?>
<div class="alert alert-danger">Não deve efetuar nenhuma ação presente nesta página sem <strong>consultar o manual do Administrador</strong>. Caso contrário, arrisca-se a danificar a integridade dos dados de reserva!</div>
<div class="card mb-3">
    <div class="card-header">
        <h5 class="mb-0">Pré-adicionar Utilizador</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small">Não deve fazer esta ação sem consultar o manual. Consulte o manual antes de proceder.</p>
        <form action="users.php?action=preadd" method="POST" class="d-flex align-items-center flex-wrap gap-2">
            <div class="form-floating" style="flex: 1; min-width: 200px;">
                <input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>
                <label for="nome">Nome</label>
            </div>
            <div class="form-floating" style="flex: 1; min-width: 200px;">
                <input type="email" class="form-control" id="email" name="email" placeholder="Email" required>
                <label for="email">Email</label>
            </div>
            <button type="submit" class="btn btn-success" style="height: 58px;">Pré-adicionar</button>
        </form>
    </div>
</div>

<div class="mb-3">
    <input type="text" class="form-control" id="userSearchInput" placeholder="Pesquisar por nome ou email..." style="max-width: 400px; margin: 0 auto;">
</div>

<p class="text-muted" id="userCountInfo">A mostrar <span id="userShownCount"><?php echo min(20, $numUtilizadores); ?></span> de <?php echo $numUtilizadores; ?> utilizadores</p>

<div id="userListContainer">
    <?php if ($numUtilizadores == 0): ?>
        <div class='alert alert-warning'>Não existem utilizadores.</div>
    <?php else: ?>
        <div class="row" id="userList">
            <?php while ($row = $utilizadores->fetch_assoc()): 
                $idEnc = urlencode($row['id']);
                $adminStatus = $row['admin'] ? "<span class='badge bg-success'>Admin</span>" : "<span class='badge bg-secondary'>Utilizador</span>";
                $isPreRegistered = str_starts_with($row['id'], PRE_REGISTERED_PREFIX);
                $preRegBadge = $isPreRegistered ? " <span class='badge bg-warning text-dark'>Pré-registado</span>" : "";
                $internalDomain = get_app_config('internal_email_domain', '');
                $isExternal = !empty($internalDomain) && !str_ends_with($row['email'], '@' . $internalDomain);
                $externalBadge = !empty($internalDomain) && $isExternal ? " <span class='badge bg-info'>Externo</span>" : "";
                
                // TOTP status
                $hasTotp = !empty($row['totp_secret']);
                $totpStatus = $hasTotp ? "<span class='badge bg-success'>TOTP Ativado</span>" : "<span class='badge bg-secondary'>Sem TOTP</span>";
                
                $userName = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
                $userEmail = htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8');
            ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $userName; ?></h5>
                            <p class="card-text text-muted"><?php echo $userEmail; ?></p>
                            <p class="card-text"><?php echo $adminStatus . $preRegBadge . $externalBadge; ?></p>
                            <p class="card-text"><?php echo $totpStatus; ?></p>
                        </div>
                        <div class="card-footer bg-transparent">
                            <a href='/admin/users.php?action=edit&id=<?php echo $idEnc; ?>' class='btn btn-sm btn-primary'>EDITAR</a>
                            <?php if ($hasTotp): ?>
                                <a href='/admin/users.php?action=removetotp&id=<?php echo $idEnc; ?>' class='btn btn-sm btn-warning' onclick='return confirm("Tem a certeza que pretende remover o TOTP deste utilizador?");'>Remover TOTP</a>
                            <?php endif; ?>
                            <a href='/admin/users.php?action=apagar&id=<?php echo $idEnc; ?>' class='btn btn-sm btn-danger' onclick='return confirm("Tem a certeza que pretende apagar o utilizador? Isto irá causar problemas se o utilizador tiver reservas passadas.");'>APAGAR</a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
        <div id="loadMoreContainer" class="text-center mb-3" style="<?php echo $numUtilizadores > 20 ? '' : 'display: none;'; ?>">
            <button type="button" class="btn btn-outline-primary" id="loadMoreBtn">Carregar mais</button>
        </div>
    <?php endif; ?>
</div>

<script>
(function() {
    let currentOffset = 20;
    let currentSearch = '';
    let searchTimeout = null;
    const limit = 20;
    
    const searchInput = document.getElementById('userSearchInput');
    const userList = document.getElementById('userList');
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    const userCountInfo = document.getElementById('userCountInfo');
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function createUserCard(user) {
        const adminBadge = user.admin 
            ? "<span class='badge bg-success'>Admin</span>" 
            : "<span class='badge bg-secondary'>Utilizador</span>";
        const preRegBadge = user.isPreRegistered 
            ? " <span class='badge bg-warning text-dark'>Pré-registado</span>" 
            : "";
        const internalDomain = '<?php echo addslashes(get_app_config("internal_email_domain", "")); ?>';
        const isExternal = internalDomain && !user.email.endsWith('@' + internalDomain);
        const externalBadge = internalDomain && isExternal 
            ? " <span class='badge bg-info'>Externo</span>" 
            : "";
        const idEnc = encodeURIComponent(user.id);
        
        return `
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">${escapeHtml(user.nome)}</h5>
                        <p class="card-text text-muted">${escapeHtml(user.email)}</p>
                        <p class="card-text">${adminBadge}${preRegBadge}${externalBadge}</p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <a href='/admin/users.php?action=edit&id=${idEnc}' class='btn btn-sm btn-primary'>EDITAR</a>
                        <a href='/admin/users.php?action=apagar&id=${idEnc}' class='btn btn-sm btn-danger' onclick='return confirm("Tem a certeza que pretende apagar o utilizador? Isto irá causar problemas se o utilizador tiver reservas passadas.");'>APAGAR</a>
                    </div>
                </div>
            </div>
        `;
    }
    
    function fetchUsers(search, offset, append) {
        const url = `/admin/api/users_search.php?action=search&search=${encodeURIComponent(search)}&offset=${offset}`;
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    return;
                }
                
                if (!append) {
                    userList.innerHTML = '';
                }
                
                if (data.users.length === 0 && !append) {
                    userList.innerHTML = "<div class='col-12'><div class='alert alert-warning'>Nenhum utilizador encontrado.</div></div>";
                    loadMoreContainer.style.display = 'none';
                } else {
                    data.users.forEach(user => {
                        userList.insertAdjacentHTML('beforeend', createUserCard(user));
                    });
                    
                    loadMoreContainer.style.display = data.hasMore ? '' : 'none';
                }
                
                const shownCount = offset + data.users.length;
                userCountInfo.textContent = `A mostrar ${shownCount} de ${data.total} utilizadores`;
                
                currentOffset = offset + data.users.length;
            })
            .catch(error => {
                console.error('Erro ao pesquisar utilizadores:', error);
            });
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const search = this.value.trim();
            
            if (searchTimeout) {
                clearTimeout(searchTimeout);
            }
            
            searchTimeout = setTimeout(function() {
                currentSearch = search;
                currentOffset = 0;
                fetchUsers(search, 0, false);
            }, 300);
        });
    }
    
    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            fetchUsers(currentSearch, currentOffset, true);
        });
    }
})();
</script>

<?php
$db->close();
?>
</div>
