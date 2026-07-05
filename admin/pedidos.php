<?php
require 'index.php';
require_once(__DIR__ . '/../func/email_helper.php');

// Get statistics for the dashboard
$totalPendentes = $db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 0")->fetch_assoc()['total'];
$totalHoje = $db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 0 AND data = CURDATE()")->fetch_assoc()['total'];
$totalAprovadas = $db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 1")->fetch_assoc()['total'];
?>

<script src="https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js" crossorigin="anonymous"></script>
<style>
    img.emoji {
        height: 1em;
        width: 1em;
        margin: 0 .05em 0 .1em;
        vertical-align: -0.1em;
    }
    .stat-card {
        --stat-card-text: #212529;
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
        border-radius: 12px;
        color: var(--stat-card-text);
    }
    .stat-card-warning {
        --stat-card-text: #5f4700;
        background: linear-gradient(135deg, #fff4cc 0%, #ffd766 100%);
    }
    .stat-card-success {
        --stat-card-text: #0f5132;
        background: linear-gradient(135deg, #d1e7dd 0%, #75b798 100%);
    }
    .stat-card-info {
        --stat-card-text: #084298;
        background: linear-gradient(135deg, #cff4fc 0%, #6edff6 100%);
    }
    [data-bs-theme="dark"] .stat-card-warning {
        --stat-card-text: #fff3cd;
        background: linear-gradient(135deg, #4d3800 0%, #8a6700 100%);
    }
    [data-bs-theme="dark"] .stat-card-success {
        --stat-card-text: #d1e7dd;
        background: linear-gradient(135deg, #0f3324 0%, #146c43 100%);
    }
    [data-bs-theme="dark"] .stat-card-info {
        --stat-card-text: #cff4fc;
        background: linear-gradient(135deg, #073642 0%, #087990 100%);
    }
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1;
    }
    .pedido-card {
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        margin-bottom: 1rem;
    }
    .pedido-card:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .pedido-card.pending {
        border-left-color: #ffc107;
    }
    .pedido-card.approved {
        border-left-color: #28a745;
    }
    .action-btn {
        transition: all 0.2s ease;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 500;
    }
    .action-btn:hover {
        transform: scale(1.05);
    }
    .search-box {
        border-radius: 25px;
        padding-left: 1rem;
        border: 2px solid #e9ecef;
        transition: border-color 0.2s ease;
    }
    .search-box:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13,110,253,.15);
    }
    .empty-state {
        padding: 3rem;
        text-align: center;
        color: #6c757d;
    }
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }
    .fade-in {
        animation: fadeIn 0.5s ease-in-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .pulse {
        animation: pulse 2s infinite;
    }
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    .badge-pending {
        background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    }
    .badge-approved {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    }
    .filter-btn.active {
        background-color: #0d6efd;
        color: white;
    }
    .pedidos-admin-shell {
        margin-left: 10%;
        margin-right: 10%;
        text-align: left;
    }
    .pedidos-admin-header {
        margin-bottom: 1rem;
    }
    .pedidos-admin-header h3 {
        margin-bottom: 0;
    }
    .skeleton-line {
        height: 1rem;
        border-radius: 4px;
        background: linear-gradient(90deg, #e9ecef 25%, #f8f9fa 50%, #e9ecef 75%);
        background-size: 200% 100%;
        animation: skeletonLoading 1.2s infinite;
    }
    @keyframes skeletonLoading {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }
</style>

<div class="pedidos-admin-shell fade-in">
    <div class="pedidos-admin-header d-flex justify-content-between align-items-start gap-3">
        <div>
            <h3>Gestão de Pedidos</h3>
            <p class="text-muted mb-0">Gerir e aprovar pedidos de reserva de salas</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6 px-3 py-2">
                <?php echo date('d/m/Y'); ?>
            </span>
        </div>
    </div>

    <div class="row mb-4 g-3">
        <div class="col-md-4">
            <div class="card stat-card stat-card-warning shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-warning text-white me-3">
                        &#x23F3;
                    </div>
                    <div>
                        <div class="stat-number" id="statPendentes"><?php echo $totalPendentes; ?></div>
                        <div class="small">Pedidos Pendentes</div>
                    </div>
                    <div class="ms-auto <?php echo $totalPendentes > 0 ? '' : 'd-none'; ?>" id="statPendentesBadge">
                        <span class="badge bg-warning text-dark pulse">Requer Atenção</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card stat-card-success shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-success text-white me-3">
                        &#x2705;
                    </div>
                    <div>
                        <div class="stat-number" id="statAprovadas"><?php echo $totalAprovadas; ?></div>
                        <div class="small">Reservas Aprovadas</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card stat-card-info shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="stat-icon bg-info text-white me-3">
                        &#x1F4C5;
                    </div>
                    <div>
                        <div class="stat-number" id="statHoje"><?php echo $totalHoje; ?></div>
                        <div class="small">Pendentes para Hoje</div>
                    </div>
                    <div class="ms-auto <?php echo $totalHoje > 0 ? '' : 'd-none'; ?>" id="statHojeBadge">
                        <span class="badge bg-info">Urgente</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="/admin/pedidos.php" method="POST" id="filterForm">
                <input type="hidden" id="requisitor" name="requisitor" value="<?php echo isset($_POST['requisitor']) ? htmlspecialchars($_POST['requisitor'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="sala" class="form-label fw-bold">Filtrar por Sala</label>
                        <select class="form-select" id="sala" name="sala">
                            <?php
                            $selectedSala = isset($_POST['sala']) ? $_POST['sala'] : (isset($_GET['sala']) ? $_GET['sala'] : "0");
                            if ($selectedSala == "0") {
                                echo "<option value='0' selected>Todas as salas</option>";
                            } else {
                                echo "<option value='0'>Todas as salas</option>";
                            }
                            $salasQuery = $db->query("SELECT * FROM salas ORDER BY nome ASC;");
                            while ($salaItem = $salasQuery->fetch_assoc()) {
                                $selected = ($selectedSala == $salaItem['id']) ? 'selected' : '';
                                echo "<option value='" . htmlspecialchars($salaItem['id'], ENT_QUOTES, 'UTF-8') . "' {$selected}>" . htmlspecialchars($salaItem['nome'], ENT_QUOTES, 'UTF-8') . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Filtrar por Requisitor</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="selectedUserDisplay"
                                   placeholder="Todos os utilizadores" readonly
                                   value="<?php
                                   if (isset($_POST['requisitor']) && !empty($_POST['requisitor'])) {
                                       $userStmt = $db->prepare("SELECT nome FROM cache WHERE id = ?");
                                       $userStmt->bind_param("s", $_POST['requisitor']);
                                       $userStmt->execute();
                                       $userResult = $userStmt->get_result()->fetch_assoc();
                                       $userStmt->close();
                                       echo $userResult ? htmlspecialchars($userResult['nome'], ENT_QUOTES, 'UTF-8') : '';
                                   }
                                   ?>">
                            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#userSelectModal">
                                Procurar
                            </button>
                            <button class="btn btn-outline-danger" type="button" onclick="clearUserSelection()">
                                Limpar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">
                            Pesquisar
                        </button>
                        <a href="/admin/pedidos.php" class="btn btn-outline-secondary">
                            Limpar Tudo
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="userSelectModal" tabindex="-1" aria-labelledby="userSelectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userSelectModalLabel">Selecionar Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="userSearchInput" placeholder="Pesquisar por nome ou email..." oninput="filterUsersModal()">
                    </div>
                    <div class="list-group" id="userList">
                        <?php
                        $usersQuery = $db->query("SELECT id, nome, email FROM cache ORDER BY nome ASC");
                        while ($user = $usersQuery->fetch_assoc()) {
                            $userId = htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8');
                            $userName = htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8');
                            $userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                            echo "<button type='button' class='list-group-item list-group-item-action user-item'
                                data-user-id='{$userId}'
                                data-user-name='{$userName}'
                                data-user-email='{$userEmail}'
                                onclick='selectUser(this)'>
                                <strong>{$userName}</strong><br>
                                <small class='text-muted'>{$userEmail}</small>
                            </button>";
                        }
                        ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <div id="pedidosAlerts"></div>
    <div id="pedidosAsyncHeader" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0" id="pedidosAsyncTitle"></h5>
            <span class="badge bg-secondary fs-6" id="pedidosAsyncCount"></span>
        </div>
        <div class="mb-3">
            <input type="text" class="form-control" id="tableSearch" placeholder="Pesquisar nos resultados..." oninput="filterTable()">
        </div>
    </div>
    <div id="pedidosResults">
    <?php
    if (isset($_GET['subaction'])) {
        // For bulk actions, skip individual parameter validation
        $isBulkAction = in_array($_GET['subaction'], ['bulk_approve', 'bulk_reject']);

        if (!$isBulkAction && (!isset($_GET['sala']) || !isset($_GET['tempo']) || !isset($_GET['data']))) {
            echo "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                    <strong>Erro!</strong> Parâmetros inválidos.
                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                  </div>";
            echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos Pedidos</a>";
        } else {
            switch ($_GET['subaction']) {
                case "aprovar":
                    $stmt = $db->prepare("SELECT requisitor FROM reservas WHERE sala=? AND tempo=? AND data=?");
                    $stmt->bind_param("sss", $_GET['sala'], $_GET['tempo'], $_GET['data']);
                    $stmt->execute();
                    $requisitor = $stmt->get_result()->fetch_assoc()['requisitor'];
                    $stmt->close();

                    $stmt = $db->prepare("UPDATE reservas SET aprovado=1 WHERE sala=? AND tempo=? AND data=?");
                    $stmt->bind_param("sss", $_GET['sala'], $_GET['tempo'], $_GET['data']);
                    $stmt->execute();
                    $stmt->close();

                    // Log the approval
                    require_once(__DIR__ . '/../func/logaction.php');
                    $stmt = $db->prepare("SELECT nome FROM salas WHERE id=?");
                    $stmt->bind_param("s", $_GET['sala']);
                    $stmt->execute();
                    $salaNome = $stmt->get_result()->fetch_assoc()['nome'] ?? $_GET['sala'];
                    $stmt->close();

                    $stmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                    $stmt->bind_param("s", $_GET['tempo']);
                    $stmt->execute();
                    $tempoNome = $stmt->get_result()->fetch_assoc()['horashumanos'] ?? $_GET['tempo'];
                    $stmt->close();

                    $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
                    $stmt->bind_param("s", $requisitor);
                    $stmt->execute();
                    $requisitorNome = $stmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
                    $stmt->close();

                    logaction("Aprovou a reserva do utilizador '{$requisitorNome}': sala '{$salaNome}' no dia {$_GET['data']} às {$tempoNome}", $_SESSION['id']);

                    echo "<div class='card border-success shadow-sm mb-4'>
                            <div class='card-body text-center py-4'>
                                <div class='mb-3' style='font-size: 4rem;'>&#x1F389;</div>
                                <h4 class='text-success mb-3'>Reserva Aprovada com Sucesso!</h4>
                                <p class='text-muted mb-4'>O utilizador será notificado por email sobre a aprovação.</p>
                                <div class='d-flex justify-content-center gap-2'>";
                    $reservaUrl = "/reservar/manage.php?sala=" . urlencode($_GET['sala']) . "&tempo=" . urlencode($_GET['tempo']) . "&data=" . urlencode($_GET['data']);
                    echo "<a href='{$reservaUrl}' class='btn btn-outline-info' target='_blank'>
                            Ver Detalhes
                          </a>
                          <a href='/admin/pedidos.php' class='btn btn-primary'>
                            Voltar aos Pedidos
                          </a>
                        </div>
                      </div>
                    </div>";

                    // Send approval email using the email helper
                    $emailResult = sendReservationApprovedEmail($db, $requisitor, $_GET['sala'], $_GET['tempo'], $_GET['data']);
                    if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
                        echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                                <strong>Aviso:</strong> A reserva foi aprovada, mas o email de notificação não foi enviado. Contacte um administrador.
                                <br><small>Erro: " . htmlspecialchars($emailResult['error'], ENT_QUOTES, 'UTF-8') . "</small>
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                    }
                    break;

                case "rejeitar":
                    $stmt = $db->prepare("SELECT requisitor FROM reservas WHERE sala=? AND tempo=? AND data=?");
                    $stmt->bind_param("sss", $_GET['sala'], $_GET['tempo'], $_GET['data']);
                    $stmt->execute();
                    $requisitor = $stmt->get_result()->fetch_assoc()['requisitor'];
                    $stmt->close();

                    // Get details for log before deletion
                    require_once(__DIR__ . '/../func/logaction.php');
                    $stmt = $db->prepare("SELECT nome FROM salas WHERE id=?");
                    $stmt->bind_param("s", $_GET['sala']);
                    $stmt->execute();
                    $salaNome = $stmt->get_result()->fetch_assoc()['nome'] ?? $_GET['sala'];
                    $stmt->close();

                    $stmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                    $stmt->bind_param("s", $_GET['tempo']);
                    $stmt->execute();
                    $tempoNome = $stmt->get_result()->fetch_assoc()['horashumanos'] ?? $_GET['tempo'];
                    $stmt->close();

                    $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
                    $stmt->bind_param("s", $requisitor);
                    $stmt->execute();
                    $requisitorNome = $stmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
                    $stmt->close();

                    // Send rejection email BEFORE deleting the reservation
                    $emailResult = sendReservationRejectedEmail($db, $requisitor, $_GET['sala'], $_GET['tempo'], $_GET['data']);

                    $stmt = $db->prepare("DELETE FROM reservas WHERE sala=? AND tempo=? AND data=?");
                    $stmt->bind_param("sss", $_GET['sala'], $_GET['tempo'], $_GET['data']);
                    $stmt->execute();
                    $stmt->close();

                    // Log the rejection
                    logaction("Rejeitou a reserva do utilizador '{$requisitorNome}': sala '{$salaNome}' no dia {$_GET['data']} às {$tempoNome}", $_SESSION['id']);

                    echo "<div class='card border-danger shadow-sm mb-4'>
                            <div class='card-body text-center py-4'>
                                <div class='mb-3' style='font-size: 4rem;'>&#x1F6AB;</div>
                                <h4 class='text-danger mb-3'>Reserva Rejeitada</h4>
                                <p class='text-muted mb-4'>O utilizador foi notificado por email sobre a rejeição.</p>
                                <a href='/admin/pedidos.php' class='btn btn-primary'>
                                    Voltar aos Pedidos
                                </a>
                            </div>
                          </div>";

                    if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
                        echo "<div class='alert alert-warning alert-dismissible fade show' role='alert'>
                                <strong>Aviso:</strong> A reserva foi rejeitada, mas o email de notificação não foi enviado. Contacte um administrador.
                                <br><small>Erro: " . htmlspecialchars($emailResult['error'], ENT_QUOTES, 'UTF-8') . "</small>
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                    }
                    break;

                case "detalhes":
                    break;

                case "bulk_approve":
                    if (!isset($_POST['reservations']) || empty($_POST['reservations'])) {
                        echo "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <strong>Erro!</strong> Nenhuma reserva selecionada.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                        echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos Pedidos</a>";
                        break;
                    }

                    $reservations = json_decode($_POST['reservations'], true);
                    if (!is_array($reservations) || json_last_error() !== JSON_ERROR_NONE) {
                        echo "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <strong>Erro!</strong> Dados inválidos.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                        echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos Pedidos</a>";
                        break;
                    }

                    $approved = 0;
                    $failed = 0;
                    $approvedReservations = []; // Track approved reservations for email
                    $requisitorsByUser = []; // Group by requisitor

                    foreach ($reservations as $res) {
                        if (isset($res['sala']) && isset($res['tempo']) && isset($res['data'])) {
                            try {
                                // Get requisitor and reservation details
                                $stmt = $db->prepare("SELECT r.requisitor, s.nome as sala_nome, t.horashumanos as tempo_nome
                                                      FROM reservas r
                                                      LEFT JOIN salas s ON r.sala = s.id
                                                      LEFT JOIN tempos t ON r.tempo = t.id
                                                      WHERE r.sala=? AND r.tempo=? AND r.data=?");
                                $stmt->bind_param("sss", $res['sala'], $res['tempo'], $res['data']);
                                $stmt->execute();
                                $result = $stmt->get_result()->fetch_assoc();
                                $stmt->close();

                                if ($result && $result['requisitor']) {
                                    // Approve reservation
                                    $stmt = $db->prepare("UPDATE reservas SET aprovado=1 WHERE sala=? AND tempo=? AND data=?");
                                    $stmt->bind_param("sss", $res['sala'], $res['tempo'], $res['data']);
                                    $stmt->execute();
                                    $stmt->close();

                                    // Group by requisitor
                                    $reqId = $result['requisitor'];
                                    if (!isset($requisitorsByUser[$reqId])) {
                                        $requisitorsByUser[$reqId] = [];
                                    }
                                    $requisitorsByUser[$reqId][] = [
                                        'requisitor' => $reqId,
                                        'sala_nome' => $result['sala_nome'],
                                        'tempo_nome' => $result['tempo_nome'],
                                        'data' => $res['data']
                                    ];

                                    $approved++;
                                } else {
                                    $failed++;
                                }
                            } catch (Exception $e) {
                                $failed++;
                            }
                        } else {
                            $failed++;
                        }
                    }

                    // Send bulk emails grouped by user
                    $emailErrors = [];
                    foreach ($requisitorsByUser as $reqId => $userReservations) {
                        $emailResult = sendBulkReservationApprovedEmail($db, $userReservations);
                        if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
                            $emailErrors[] = "Utilizador ID: {$reqId}";
                        }
                    }

                    // Log bulk approval
                    require_once(__DIR__ . '/../func/logaction.php');
                    logaction("Aprovou {$approved} reserva(s) em massa através da funcionalidade de aprovação em massa", $_SESSION['id']);

                    echo "<div class='card border-success shadow-sm mb-4'>
                            <div class='card-body text-center py-4'>
                                <div class='mb-3' style='font-size: 4rem;'>&#x1F389;</div>
                                <h4 class='text-success mb-3'>Aprovações em Massa Concluídas!</h4>
                                <p class='text-muted mb-4'><strong>{$approved}</strong> reserva(s) aprovada(s) com sucesso.";
                    if ($failed > 0) {
                        echo " <br><strong>{$failed}</strong> reserva(s) falharam.";
                    }
                    echo "</p>";

                    if (!empty($emailErrors)) {
                        echo "<div class='alert alert-warning text-start'>
                                <strong>Aviso:</strong> Algumas reservas foram aprovadas mas os emails não foram enviados:
                                <ul class='mb-0 mt-2'>";
                        foreach ($emailErrors as $error) {
                            echo "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
                        }
                        echo "</ul></div>";
                    }

                    echo "<a href='/admin/pedidos.php' class='btn btn-primary'>
                            Voltar aos Pedidos
                          </a>
                        </div>
                      </div>";
                    break;

                case "bulk_reject":
                    if (!isset($_POST['reservations']) || empty($_POST['reservations'])) {
                        echo "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <strong>Erro!</strong> Nenhuma reserva selecionada.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                        echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos Pedidos</a>";
                        break;
                    }

                    $reservations = json_decode($_POST['reservations'], true);
                    if (!is_array($reservations) || json_last_error() !== JSON_ERROR_NONE) {
                        echo "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <strong>Erro!</strong> Dados inválidos.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                              </div>";
                        echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos Pedidos</a>";
                        break;
                    }

                    $rejected = 0;
                    $failed = 0;
                    $rejectedReservations = []; // Track rejected reservations for email
                    $requisitorsByUser = []; // Group by requisitor

                    foreach ($reservations as $res) {
                        if (isset($res['sala']) && isset($res['tempo']) && isset($res['data'])) {
                            try {
                                // Get requisitor and reservation details BEFORE deleting
                                $stmt = $db->prepare("SELECT r.requisitor, s.nome as sala_nome, t.horashumanos as tempo_nome
                                                      FROM reservas r
                                                      LEFT JOIN salas s ON r.sala = s.id
                                                      LEFT JOIN tempos t ON r.tempo = t.id
                                                      WHERE r.sala=? AND r.tempo=? AND r.data=?");
                                $stmt->bind_param("sss", $res['sala'], $res['tempo'], $res['data']);
                                $stmt->execute();
                                $result = $stmt->get_result()->fetch_assoc();
                                $stmt->close();

                                if ($result && $result['requisitor']) {
                                    // Group by requisitor for email
                                    $reqId = $result['requisitor'];
                                    if (!isset($requisitorsByUser[$reqId])) {
                                        $requisitorsByUser[$reqId] = [];
                                    }
                                    $requisitorsByUser[$reqId][] = [
                                        'requisitor' => $reqId,
                                        'sala_nome' => $result['sala_nome'],
                                        'tempo_nome' => $result['tempo_nome'],
                                        'data' => $res['data']
                                    ];

                                    // Delete reservation
                                    $stmt = $db->prepare("DELETE FROM reservas WHERE sala=? AND tempo=? AND data=?");
                                    $stmt->bind_param("sss", $res['sala'], $res['tempo'], $res['data']);
                                    $stmt->execute();
                                    $stmt->close();

                                    $rejected++;
                                } else {
                                    $failed++;
                                }
                            } catch (Exception $e) {
                                $failed++;
                            }
                        } else {
                            $failed++;
                        }
                    }

                    // Send bulk rejection emails grouped by user
                    $emailErrors = [];
                    foreach ($requisitorsByUser as $reqId => $userReservations) {
                        $emailResult = sendBulkReservationRejectedEmail($db, $userReservations);
                        if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
                            $emailErrors[] = "Utilizador ID: {$reqId}";
                        }
                    }

                    // Log bulk rejection
                    require_once(__DIR__ . '/../func/logaction.php');
                    logaction("Rejeitou {$rejected} reserva(s) em massa através da funcionalidade de rejeição em massa", $_SESSION['id']);

                    echo "<div class='card border-danger shadow-sm mb-4'>
                            <div class='card-body text-center py-4'>
                                <div class='mb-3' style='font-size: 4rem;'>&#x1F6AB;</div>
                                <h4 class='text-danger mb-3'>Rejeições em Massa Concluídas</h4>
                                <p class='text-muted mb-4'><strong>{$rejected}</strong> reserva(s) rejeitada(s).";
                    if ($failed > 0) {
                        echo " <br><strong>{$failed}</strong> reserva(s) falharam.";
                    }
                    echo "</p>";

                    if (!empty($emailErrors)) {
                        echo "<div class='alert alert-warning text-start'>
                                <strong>Aviso:</strong> Algumas reservas foram rejeitadas mas os emails não foram enviados:
                                <ul class='mb-0 mt-2'>";
                        foreach ($emailErrors as $error) {
                            echo "<li>" . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . "</li>";
                        }
                        echo "</ul></div>";
                    }

                    echo "<a href='/admin/pedidos.php' class='btn btn-primary'>
                            Voltar aos Pedidos
                          </a>
                        </div>
                      </div>";
                    break;
            }
        }
    } elseif (isset($_POST['sala']) || isset($_GET['sala']) || isset($_POST['requisitor'])) {
        // Determine what to show
        $showAllPending = (isset($_POST['sala']) && $_POST['sala'] == "0") || (!isset($_POST['sala']) && !isset($_GET['sala']) && !isset($_POST['requisitor']));

        if (isset($_POST['sala']) && $_POST['sala'] != "0") {
            $sala = $_POST['sala'];
        } elseif (isset($_GET['sala'])) {
            $sala = $_GET['sala'];
        } else {
            $sala = null;
        }

        $pedidosArray = [];

        if (isset($_POST['requisitor']) && !empty($_POST['requisitor'])) {
            // Search by requisitor - only show pending requests
            $stmt = $db->prepare("SELECT * FROM reservas WHERE requisitor=? AND aprovado=0 ORDER BY data ASC");
            $stmt->bind_param("s", $_POST['requisitor']);
            $stmt->execute();
            $pedidos = $stmt->get_result();
            while ($pedido = $pedidos->fetch_assoc()) {
                $pedidosArray[] = $pedido;
            }
            $stmt->close();
            $searchMode = "requisitor";
        } elseif ($showAllPending || $sala === null) {
            // Show all pending requests
            $pedidos = $db->query("SELECT * FROM reservas WHERE aprovado=0 ORDER BY data ASC, tempo ASC");
            while ($pedido = $pedidos->fetch_assoc()) {
                $pedidosArray[] = $pedido;
            }
            $searchMode = "all";
        } else {
            // Filter by room
            $stmt = $db->prepare("SELECT * FROM reservas WHERE aprovado=0 AND sala=? ORDER BY data ASC");
            $stmt->bind_param("s", $sala);
            $stmt->execute();
            $pedidos = $stmt->get_result();
            while ($pedido = $pedidos->fetch_assoc()) {
                $pedidosArray[] = $pedido;
            }
            $stmt->close();
            $searchMode = "room";
        }

        $totalResults = count($pedidosArray);

        // Results Header
        echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
        echo "<h5 class='mb-0'>";
        if ($searchMode == "requisitor") {
            $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
            $stmt->bind_param("s", $_POST['requisitor']);
            $stmt->execute();
            $reqResult = $stmt->get_result()->fetch_assoc();
            $reqName = $reqResult ? $reqResult['nome'] : 'Utilizador';
            $stmt->close();
            echo "Pedidos Pendentes - " . htmlspecialchars($reqName, ENT_QUOTES, 'UTF-8');
        } elseif ($searchMode == "room") {
            $stmt = $db->prepare("SELECT nome FROM salas WHERE id=?");
            $stmt->bind_param("s", $sala);
            $stmt->execute();
            $salaResult = $stmt->get_result()->fetch_assoc();
            $salaName = $salaResult ? $salaResult['nome'] : 'Sala';
            $stmt->close();
            echo "Pedidos Pendentes - " . htmlspecialchars($salaName, ENT_QUOTES, 'UTF-8');
        } else {
            echo "Todos os Pedidos Pendentes";
        }
        echo "</h5>";
        echo "<span class='badge bg-secondary fs-6'>{$totalResults} resultado(s)</span>";
        echo "</div>";

        if ($totalResults == 0) {
            echo "<div class='card shadow-sm'>
                    <div class='card-body empty-state'>
                        <div class='empty-state-icon'>&#x1F4ED;</div>
                        <h5>Nenhum pedido encontrado</h5>
                        <p class='mb-0'>Não existem pedidos pendentes para os filtros selecionados.</p>
                    </div>
                  </div>";
        } else {
            // Search within results
            echo "<div class='mb-3'>
                    <input type='text' class='form-control search-box' id='tableSearch'
                           placeholder='Pesquisar nos resultados...' onkeyup='filterTable()'>
                  </div>";

            // Bulk action buttons
            echo "<div class='mb-3 d-flex gap-2 align-items-center'>
                    <button type='button' class='btn btn-success' onclick='bulkApprove()' id='bulkApproveBtn' disabled>
                        <span>&#x2705;</span> Aprovar Selecionados
                    </button>
                    <button type='button' class='btn btn-danger' onclick='bulkReject()' id='bulkRejectBtn' disabled>
                        <span style='color: white; font-weight: bold;'>✕</span> Rejeitar Selecionados
                    </button>
                    <span class='text-muted ms-2' id='selectedCount'>0 selecionados</span>
                  </div>";

            echo "<div class='table-responsive'>
                    <table class='table table-hover align-middle' id='pedidosTable'>
                        <thead class='table-dark'>
                            <tr>
                                <th scope='col' style='width: 40px;'>
                                    <input type='checkbox' class='form-check-input' id='selectAll' onchange='toggleSelectAll()'>
                                </th>
                                <th scope='col'>Data</th>
                                <th scope='col'>Horário</th>
                                <th scope='col'>Sala</th>
                                <th scope='col'>Requisitor</th>
                                <th scope='col'>Motivo</th>
                                <th scope='col' class='text-center'>Ações</th>
                            </tr>
                        </thead>
                        <tbody>";

            foreach ($pedidosArray as $pedido) {
                // Get room name
                $stmt2 = $db->prepare("SELECT nome FROM salas WHERE id=?");
                $stmt2->bind_param("s", $pedido['sala']);
                $stmt2->execute();
                $salaResult = $stmt2->get_result()->fetch_assoc();
                $salaextenso = $salaResult ? $salaResult['nome'] : 'N/A';
                $stmt2->close();

                // Get requisitor name
                $stmt2 = $db->prepare("SELECT nome FROM cache WHERE id=?");
                $stmt2->bind_param("s", $pedido['requisitor']);
                $stmt2->execute();
                $reqResult = $stmt2->get_result()->fetch_assoc();
                $requisitorextenso = $reqResult ? $reqResult['nome'] : 'N/A';
                $stmt2->close();

                // Get time slot
                $stmt2 = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                $stmt2->bind_param("s", $pedido['tempo']);
                $stmt2->execute();
                $tempoResult = $stmt2->get_result()->fetch_assoc();
                $horastempo = $tempoResult ? $tempoResult['horashumanos'] : 'N/A';
                $stmt2->close();

                $tempoEnc = urlencode($pedido['tempo']);
                $dataEnc = urlencode($pedido['data']);
                $salaEnc = urlencode($pedido['sala']);

                // Format date nicely
                $dataFormatted = date('d/m/Y', strtotime($pedido['data']));
                $isToday = ($pedido['data'] == date('Y-m-d'));
                $isPast = (strtotime($pedido['data']) < strtotime(date('Y-m-d')));

                $rowClass = "";
                if ($isPast && $pedido['aprovado'] == 0) {
                    $rowClass = "table-danger";
                } elseif ($isToday) {
                    $rowClass = "table-warning";
                }

                echo "<tr class='{$rowClass}' data-search='" .
                     htmlspecialchars(strtolower($salaextenso . ' ' . $requisitorextenso . ' ' . $pedido['motivo'] . ' ' . $pedido['data']), ENT_QUOTES, 'UTF-8') . "'>";

                // Checkbox column
                echo "<td>";
                echo "<input type='checkbox' class='form-check-input row-checkbox'
                      data-sala='{$salaEnc}' data-tempo='{$tempoEnc}' data-data='{$dataEnc}'
                      data-salaname='" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "'
                      data-dataformatted='" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "'
                      data-horasname='" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "'
                      onchange='updateBulkButtons()'>";
                echo "</td>";

                // Date column
                echo "<td>";
                echo "<strong>" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "</strong>";
                if ($isToday) {
                    echo " <span class='badge bg-warning text-dark'>Hoje</span>";
                } elseif ($isPast) {
                    echo " <span class='badge bg-danger'>Passado</span>";
                }
                echo "</td>";

                // Time column
                echo "<td><span class='badge bg-light text-dark border'>" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "</span></td>";

                // Room column
                echo "<td>" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "</td>";

                // Requisitor column
                echo "<td><span class='d-inline-flex align-items-center'>";
                echo "<span class='bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center me-2' style='width: 30px; height: 30px; font-size: 0.8rem;'>";
                echo strtoupper(substr($requisitorextenso, 0, 1));
                echo "</span>";
                echo htmlspecialchars($requisitorextenso, ENT_QUOTES, 'UTF-8');
                echo "</span></td>";

                // Motivo column
                $motivoTruncated = strlen($pedido['motivo']) > 50 ? substr($pedido['motivo'], 0, 50) . '...' : $pedido['motivo'];
                echo "<td title='" . htmlspecialchars($pedido['motivo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($motivoTruncated, ENT_QUOTES, 'UTF-8') . "</td>";

                // Actions column
                echo "<td class='text-center'>";
                echo "<div class='btn-group' role='group'>";

                echo "<button type='button' class='btn btn-success btn-sm action-btn'
                      onclick='confirmAction(\"aprovar\", \"{$tempoEnc}\", \"{$dataEnc}\", \"{$salaEnc}\", \"" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "\")'
                      title='Aprovar'>
                    &#x2705;
                </button>";
                echo "<button type='button' class='btn btn-danger btn-sm action-btn'
                      onclick='confirmAction(\"rejeitar\", \"{$tempoEnc}\", \"{$dataEnc}\", \"{$salaEnc}\", \"" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "\")'
                      title='Rejeitar'>
                    <span style='color: white; font-weight: bold;'>✕</span>
                </button>";
                echo "<a href='/reservar/manage.php?tempo={$tempoEnc}&data={$dataEnc}&sala={$salaEnc}'
                      class='btn btn-outline-secondary btn-sm action-btn' title='Ver Detalhes' target='_blank'>
                    &#x1F441;
                  </a>";
                echo "</div>";
                echo "</td>";

                echo "</tr>";
            }

            echo "</tbody></table></div>";
        }
    } else {
        // Default view - Show all pending requests
        $allPending = $db->query("SELECT * FROM reservas WHERE aprovado=0 ORDER BY data ASC, tempo ASC");
        $pendingArray = [];
        while ($p = $allPending->fetch_assoc()) {
            $pendingArray[] = $p;
        }
        $totalPending = count($pendingArray);

        echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
        echo "<h5 class='mb-0'>Todos os Pedidos Pendentes</h5>";
        echo "<span class='badge bg-secondary fs-6'>{$totalPending} pedido(s)</span>";
        echo "</div>";

        if ($totalPending == 0) {
            echo "<div class='card shadow-sm'>
                    <div class='card-body empty-state'>
                        <div class='empty-state-icon'>&#x1F389;</div>
                        <h5>Nenhum pedido pendente!</h5>
                        <p class='mb-0'>Todos os pedidos foram processados. Bom trabalho!</p>
                    </div>
                  </div>";
        } else {
            echo "<div class='mb-3'>
                    <input type='text' class='form-control search-box' id='tableSearch'
                           placeholder='Pesquisar nos resultados...' onkeyup='filterTable()'>
                  </div>";

            // Bulk action buttons
            echo "<div class='mb-3 d-flex gap-2 align-items-center'>
                    <button type='button' class='btn btn-success' onclick='bulkApprove()' id='bulkApproveBtn' disabled>
                        <span>&#x2705;</span> Aprovar Selecionados
                    </button>
                    <button type='button' class='btn btn-danger' onclick='bulkReject()' id='bulkRejectBtn' disabled>
                        <span style='color: white; font-weight: bold;'>✕</span> Rejeitar Selecionados
                    </button>
                    <span class='text-muted ms-2' id='selectedCount'>0 selecionados</span>
                  </div>";

            echo "<div class='table-responsive'>
                    <table class='table table-hover align-middle' id='pedidosTable'>
                        <thead class='table-dark'>
                            <tr>
                                <th scope='col' style='width: 40px;'>
                                    <input type='checkbox' class='form-check-input' id='selectAll' onchange='toggleSelectAll()'>
                                </th>
                                <th scope='col'>Data</th>
                                <th scope='col'>Horário</th>
                                <th scope='col'>Sala</th>
                                <th scope='col'>Requisitor</th>
                                <th scope='col'>Motivo</th>
                                <th scope='col' class='text-center'>Ações</th>
                            </tr>
                        </thead>
                        <tbody>";

            foreach ($pendingArray as $pedido) {
                $stmt2 = $db->prepare("SELECT nome FROM salas WHERE id=?");
                $stmt2->bind_param("s", $pedido['sala']);
                $stmt2->execute();
                $salaResult = $stmt2->get_result()->fetch_assoc();
                $salaextenso = $salaResult ? $salaResult['nome'] : 'N/A';
                $stmt2->close();

                $stmt2 = $db->prepare("SELECT nome FROM cache WHERE id=?");
                $stmt2->bind_param("s", $pedido['requisitor']);
                $stmt2->execute();
                $reqResult = $stmt2->get_result()->fetch_assoc();
                $requisitorextenso = $reqResult ? $reqResult['nome'] : 'N/A';
                $stmt2->close();

                $stmt2 = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                $stmt2->bind_param("s", $pedido['tempo']);
                $stmt2->execute();
                $tempoResult = $stmt2->get_result()->fetch_assoc();
                $horastempo = $tempoResult ? $tempoResult['horashumanos'] : 'N/A';
                $stmt2->close();

                $tempoEnc = urlencode($pedido['tempo']);
                $dataEnc = urlencode($pedido['data']);
                $salaEnc = urlencode($pedido['sala']);

                $dataFormatted = date('d/m/Y', strtotime($pedido['data']));
                $isToday = ($pedido['data'] == date('Y-m-d'));
                $isPast = (strtotime($pedido['data']) < strtotime(date('Y-m-d')));

                $rowClass = "";
                if ($isPast) {
                    $rowClass = "table-danger";
                } elseif ($isToday) {
                    $rowClass = "table-warning";
                }

                echo "<tr class='{$rowClass}' data-search='" .
                     htmlspecialchars(strtolower($salaextenso . ' ' . $requisitorextenso . ' ' . $pedido['motivo'] . ' ' . $pedido['data']), ENT_QUOTES, 'UTF-8') . "'>";

                // Checkbox column
                echo "<td>";
                echo "<input type='checkbox' class='form-check-input row-checkbox'
                      data-sala='{$salaEnc}' data-tempo='{$tempoEnc}' data-data='{$dataEnc}'
                      data-salaname='" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "'
                      data-dataformatted='" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "'
                      data-horasname='" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "'
                      onchange='updateBulkButtons()'>";
                echo "</td>";

                echo "<td><strong>" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "</strong>";
                if ($isToday) {
                    echo " <span class='badge bg-warning text-dark'>Hoje</span>";
                } elseif ($isPast) {
                    echo " <span class='badge bg-danger'>Passado</span>";
                }
                echo "</td>";

                echo "<td><span class='badge bg-light text-dark border'>" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "</span></td>";
                echo "<td>" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "</td>";

                echo "<td><span class='d-inline-flex align-items-center'>";
                echo "<span class='bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center me-2' style='width: 30px; height: 30px; font-size: 0.8rem;'>";
                echo strtoupper(substr($requisitorextenso, 0, 1));
                echo "</span>";
                echo htmlspecialchars($requisitorextenso, ENT_QUOTES, 'UTF-8');
                echo "</span></td>";

                $motivoTruncated = strlen($pedido['motivo']) > 50 ? substr($pedido['motivo'], 0, 50) . '...' : $pedido['motivo'];
                echo "<td title='" . htmlspecialchars($pedido['motivo'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($motivoTruncated, ENT_QUOTES, 'UTF-8') . "</td>";

                echo "<td class='text-center'>";
                echo "<div class='btn-group' role='group'>";
                echo "<button type='button' class='btn btn-success btn-sm action-btn'
                      onclick='confirmAction(\"aprovar\", \"{$tempoEnc}\", \"{$dataEnc}\", \"{$salaEnc}\", \"" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "\")'
                      title='Aprovar'>
                    &#x2705;
                </button>";
                echo "<button type='button' class='btn btn-danger btn-sm action-btn'
                      onclick='confirmAction(\"rejeitar\", \"{$tempoEnc}\", \"{$dataEnc}\", \"{$salaEnc}\", \"" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "\", \"" . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "\")'
                      title='Rejeitar'>
                    <span style='color: white; font-weight: bold;'>✕</span>
                </button>";
                echo "<a href='/reservar/manage.php?tempo={$tempoEnc}&data={$dataEnc}&sala={$salaEnc}'
                      class='btn btn-outline-secondary btn-sm action-btn' title='Ver Detalhes' target='_blank'>
                    &#x1F441;
                  </a>";
                echo "</div>";
                echo "</td>";
                echo "</tr>";
            }

            echo "</tbody></table></div>";
        }
    }
    ?>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header" id="modalHeader">
                <h5 class="modal-title" id="confirmModalLabel">Confirmar Ação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalBody">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="confirmBtn" class="btn btn-primary">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>
function filterTable() {
    const searchInput = document.getElementById('tableSearch');
    if (!searchInput) return;

    pedidosState.search = searchInput.value.trim();
    clearTimeout(pedidosState.searchTimer);
    pedidosState.searchTimer = setTimeout(function() {
        loadPedidos(true);
    }, 250);
}

function confirmAction(action, tempo, data, sala, salaNome, dataFormatted, horasNome) {
    salaNome = decodeURIComponent(salaNome);
    dataFormatted = decodeURIComponent(dataFormatted);
    horasNome = decodeURIComponent(horasNome);
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const modalHeader = document.getElementById('modalHeader');
    const modalBody = document.getElementById('modalBody');
    const confirmBtn = document.getElementById('confirmBtn');

    if (action === 'aprovar') {
        modalHeader.className = 'modal-header bg-success text-white';
        modalBody.innerHTML = `
            <div class="text-center mb-3">
                <div style="font-size: 4rem;">&#x2705;</div>
            </div>
            <h5 class="text-center">Aprovar esta reserva?</h5>
            <div class="alert alert-light border mt-3">
                <p class="mb-1"><strong>Sala:</strong> ${salaNome}</p>
                <p class="mb-1"><strong>Data:</strong> ${dataFormatted}</p>
                <p class="mb-0"><strong>Horário:</strong> ${horasNome}</p>
            </div>
            <p class="text-muted small text-center mb-0">O utilizador será notificado por email.</p>
        `;
        confirmBtn.className = 'btn btn-success';
        confirmBtn.textContent = 'Aprovar Reserva';
        confirmBtn.onclick = async function() {
            try {
                const result = await submitPedidosAction('aprovar', [{ sala: decodeURIComponent(sala), tempo: decodeURIComponent(tempo), data: decodeURIComponent(data) }]);
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                showPedidosActionResult(result);
                loadPedidos(true);
            } catch (error) {
                window.location.href = `/admin/pedidos.php?subaction=aprovar&tempo=${tempo}&data=${data}&sala=${sala}`;
            }
        };
    } else {
        modalHeader.className = 'modal-header bg-danger text-white';
        modalBody.innerHTML = `
            <div class="text-center mb-3">
                <div style="font-size: 4rem; color: #dc3545;">✕</div>
            </div>
            <h5 class="text-center text-danger">Rejeitar esta reserva?</h5>
            <div class="alert alert-light border mt-3">
                <p class="mb-1"><strong>Sala:</strong> ${salaNome}</p>
                <p class="mb-1"><strong>Data:</strong> ${dataFormatted}</p>
                <p class="mb-0"><strong>Horário:</strong> ${horasNome}</p>
            </div>
            <div class="alert alert-warning">
                <strong>Atenção:</strong> Esta ação irá eliminar a reserva permanentemente e notificar o utilizador.
            </div>
        `;
        confirmBtn.className = 'btn btn-danger';
        confirmBtn.textContent = 'Rejeitar Reserva';
        confirmBtn.onclick = async function() {
            try {
                const result = await submitPedidosAction('rejeitar', [{ sala: decodeURIComponent(sala), tempo: decodeURIComponent(tempo), data: decodeURIComponent(data) }]);
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                showPedidosActionResult(result);
                loadPedidos(true);
            } catch (error) {
                window.location.href = `/admin/pedidos.php?subaction=rejeitar&tempo=${tempo}&data=${data}&sala=${sala}`;
            }
        };
    }

    modal.show();
}

function filterUsersModal() {
    const searchInput = document.getElementById('userSearchInput');
    const filter = searchInput.value.toLowerCase();
    const userItems = document.querySelectorAll('.user-item');

    userItems.forEach(function(item) {
        const name = item.getAttribute('data-user-name').toLowerCase();
        const email = item.getAttribute('data-user-email').toLowerCase();
        if (name.includes(filter) || email.includes(filter)) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function selectUser(element) {
    const userId = element.getAttribute('data-user-id');
    const userName = element.getAttribute('data-user-name');

    document.getElementById('requisitor').value = userId;
    document.getElementById('selectedUserDisplay').value = userName;

    // Close the modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('userSelectModal'));
    modal.hide();

    loadPedidos(true);
}

function clearUserSelection() {
    document.getElementById('requisitor').value = '';
    document.getElementById('selectedUserDisplay').value = '';
    loadPedidos(true);
}


const pedidosState = {
    page: 1,
    hasMore: true,
    loading: false,
    initialized: false,
    search: '',
    searchTimer: null,
    requestController: null,
    requestId: 0
};

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#039;',
        '"': '&quot;'
    }[char]));
}

function getFilterParams() {
    const form = document.getElementById('filterForm');
    const params = new URLSearchParams(new FormData(form));
    params.delete('csrf_token');
    if (pedidosState.search !== '') {
        params.set('q', pedidosState.search);
    }
    return params;
}

function updatePedidosStats(stats) {
    if (!stats) return;

    document.getElementById('statPendentes').textContent = stats.pendentes;
    document.getElementById('statAprovadas').textContent = stats.aprovadas;
    document.getElementById('statHoje').textContent = stats.hoje;
    document.getElementById('statPendentesBadge').classList.toggle('d-none', Number(stats.pendentes) <= 0);
    document.getElementById('statHojeBadge').classList.toggle('d-none', Number(stats.hoje) <= 0);
}

function showPedidosActionResult(result) {
    const errors = Array.isArray(result.emailErrors) ? result.emailErrors : [];
    const warning = errors.length
        ? `<div class="mt-2"><strong>Aviso:</strong> Alguns emails não foram enviados.<ul class="mb-0">${errors.map(error => `<li>${escapeHtml(error)}</li>`).join('')}</ul></div>`
        : '';

    document.getElementById('pedidosAlerts').innerHTML = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ${escapeHtml(result.message)} ${Number(result.processed || 0)} processada(s), ${Number(result.failed || 0)} falhada(s).
            ${warning}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
}

function showPedidosSkeleton() {
    const results = document.getElementById('pedidosResults');
    if (!results) return;

    let rows = '';
    for (let i = 0; i < 5; i++) {
        rows += `<tr><td colspan="7"><div class="skeleton-line"></div></td></tr>`;
    }

    results.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">A carregar pedidos...</h5>
            <span class="badge bg-secondary fs-6">...</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle"><tbody>${rows}</tbody></table>
        </div>
    `;
}

function renderPedidoRow(pedido) {
    const rowClass = pedido.is_past ? 'table-danger' : (pedido.is_today ? 'table-warning' : '');
    const sala = encodeURIComponent(pedido.sala);
    const tempo = encodeURIComponent(pedido.tempo);
    const data = encodeURIComponent(pedido.data);
    const salaNome = encodeURIComponent(pedido.sala_nome);
    const dataFormatada = encodeURIComponent(pedido.data_formatada);
    const tempoNome = encodeURIComponent(pedido.tempo_nome);
    const motivo = pedido.motivo || '';
    const motivoCurto = motivo.length > 50 ? motivo.substring(0, 50) + '...' : motivo;
    const inicial = (pedido.requisitor_nome || 'N').substring(0, 1).toUpperCase();
    const search = `${pedido.sala_nome} ${pedido.requisitor_nome} ${motivo} ${pedido.data}`.toLowerCase();

    return `
        <tr class="${rowClass}" data-search="${escapeHtml(search)}">
            <td><input type="checkbox" class="form-check-input row-checkbox" data-sala="${sala}" data-tempo="${tempo}" data-data="${data}" data-salaname="${escapeHtml(pedido.sala_nome)}" data-dataformatted="${escapeHtml(pedido.data_formatada)}" data-horasname="${escapeHtml(pedido.tempo_nome)}" onchange="updateBulkButtons()"></td>
            <td><strong>${escapeHtml(pedido.data_formatada)}</strong> ${pedido.is_today ? '<span class="badge bg-warning text-dark">Hoje</span>' : ''} ${pedido.is_past ? '<span class="badge bg-danger">Passado</span>' : ''}</td>
            <td><span class="badge bg-light text-dark border">${escapeHtml(pedido.tempo_nome)}</span></td>
            <td>${escapeHtml(pedido.sala_nome)}</td>
            <td><span class="d-inline-flex align-items-center"><span class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center me-2" style="width: 30px; height: 30px; font-size: 0.8rem;">${escapeHtml(inicial)}</span>${escapeHtml(pedido.requisitor_nome)}</span></td>
            <td title="${escapeHtml(motivo)}">${escapeHtml(motivoCurto)}</td>
            <td class="text-center"><div class="btn-group" role="group">
                <button type="button" class="btn btn-success btn-sm action-btn pedido-action" data-action="aprovar" data-tempo="${tempo}" data-data="${data}" data-sala="${sala}" data-salaname="${escapeHtml(salaNome)}" data-dataformatted="${escapeHtml(dataFormatada)}" data-horasname="${escapeHtml(tempoNome)}" title="Aprovar">&#x2705;</button>
                <button type="button" class="btn btn-danger btn-sm action-btn pedido-action" data-action="rejeitar" data-tempo="${tempo}" data-data="${data}" data-sala="${sala}" data-salaname="${escapeHtml(salaNome)}" data-dataformatted="${escapeHtml(dataFormatada)}" data-horasname="${escapeHtml(tempoNome)}" title="Rejeitar"><span style="color: white; font-weight: bold;">✕</span></button>
                <a href="/reservar/manage.php?tempo=${tempo}&data=${data}&sala=${sala}" class="btn btn-outline-secondary btn-sm action-btn" title="Ver Detalhes" target="_blank">&#x1F441;</a>
            </div></td>
        </tr>`;
}

function renderPedidos(data, append) {
    const results = document.getElementById('pedidosResults');
    if (!results) return;

    const focusedSearch = document.activeElement && document.activeElement.id === 'tableSearch';
    const searchSelectionStart = focusedSearch ? document.activeElement.selectionStart : null;
    const searchSelectionEnd = focusedSearch ? document.activeElement.selectionEnd : null;
    const showTools = data.total > 0 || pedidosState.search !== '';

    if (!append) {
        updatePedidosAsyncHeader(data);
        results.innerHTML = `
            <div id="pedidosTools" class="${showTools ? '' : 'd-none'}">
                <div class="mb-3 d-flex gap-2 align-items-center">
                    <button type="button" class="btn btn-success" onclick="bulkApprove()" id="bulkApproveBtn" disabled><span>&#x2705;</span> Aprovar Selecionados</button>
                    <button type="button" class="btn btn-danger" onclick="bulkReject()" id="bulkRejectBtn" disabled><span style="color: white; font-weight: bold;">✕</span> Rejeitar Selecionados</button>
                    <span class="text-muted ms-2" id="selectedCount">0 selecionados</span>
                </div>
                <div class="table-responsive"><table class="table table-hover align-middle" id="pedidosTable"><thead class="table-dark"><tr><th scope="col" style="width: 40px;"><input type="checkbox" class="form-check-input" id="selectAll" onchange="toggleSelectAll()"></th><th scope="col">Data</th><th scope="col">Horário</th><th scope="col">Sala</th><th scope="col">Requisitor</th><th scope="col">Motivo</th><th scope="col" class="text-center">Ações</th></tr></thead><tbody></tbody></table></div>
            </div>
            <div id="pedidosEmpty"></div>
            <div id="pedidosLoadingMore" class="text-center my-3 d-none"><div class="spinner-border spinner-border-sm" role="status"></div> A carregar...</div>
        `;
    }

    if (data.total === 0) {
        const emptyDescription = pedidosState.search !== ''
            ? 'Não existem pedidos pendentes que correspondam à pesquisa atual.'
            : 'Não existem pedidos pendentes para os filtros selecionados.';
        document.getElementById('pedidosEmpty').innerHTML = `<div class="card shadow-sm"><div class="card-body empty-state"><div class="empty-state-icon">&#x1F4ED;</div><h5>Nenhum pedido encontrado</h5><p class="mb-0">${emptyDescription}</p></div></div>`;
        restoreSearchFocus(focusedSearch, searchSelectionStart, searchSelectionEnd);
        updatePedidosStats(data.stats);
        return;
    }

    const tbody = document.querySelector('#pedidosTable tbody');
    tbody.insertAdjacentHTML('beforeend', data.pedidos.map(renderPedidoRow).join(''));
    updateBulkButtons();
    updatePedidosStats(data.stats);
    if (window.twemoji) twemoji.parse(results, { folder: 'svg', ext: '.svg' });
    restoreSearchFocus(focusedSearch, searchSelectionStart, searchSelectionEnd);
}

function updatePedidosAsyncHeader(data) {
    const header = document.getElementById('pedidosAsyncHeader');
    if (!header) return;

    document.getElementById('pedidosAsyncTitle').textContent = data.title;
    document.getElementById('pedidosAsyncCount').textContent = data.total + ' resultado(s)';
}

function restoreSearchFocus(shouldFocus, selectionStart, selectionEnd) {
    if (!shouldFocus) return;

    const searchInput = document.getElementById('tableSearch');
    if (!searchInput) return;

    searchInput.focus({ preventScroll: true });
    if (selectionStart !== null && selectionEnd !== null) {
        searchInput.setSelectionRange(selectionStart, selectionEnd);
    }
}

async function loadPedidos(reset = false) {
    if (!reset && (pedidosState.loading || !pedidosState.hasMore)) return;
    if (reset && pedidosState.requestController) {
        pedidosState.requestController.abort();
    }

    const requestId = ++pedidosState.requestId;
    const controller = new AbortController();
    pedidosState.requestController = controller;
    pedidosState.loading = true;
    if (reset) {
        pedidosState.page = 1;
        pedidosState.hasMore = true;
        if (!document.activeElement || document.activeElement.id !== 'tableSearch') {
            showPedidosSkeleton();
        } else {
            document.getElementById('pedidosLoadingMore')?.classList.remove('d-none');
        }
    } else {
        document.getElementById('pedidosLoadingMore')?.classList.remove('d-none');
    }

    try {
        const params = getFilterParams();
        params.set('page', pedidosState.page);
        const response = await fetch('/admin/api/pedidos_list.php?' + params.toString(), {
            headers: { 'Accept': 'application/json' },
            signal: controller.signal
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Erro ao carregar pedidos.');
        if (requestId !== pedidosState.requestId) return;
        renderPedidos(data, !reset);
        pedidosState.hasMore = data.hasMore;
        pedidosState.page += 1;
    } catch (error) {
        if (error.name === 'AbortError') return;
        document.getElementById('pedidosAlerts').innerHTML = `<div class="alert alert-danger">${escapeHtml(error.message)}</div>`;
    } finally {
        if (requestId !== pedidosState.requestId) return;
        pedidosState.loading = false;
        pedidosState.requestController = null;
        document.getElementById('pedidosLoadingMore')?.classList.add('d-none');
        setTimeout(maybeLoadMore, 0);
    }
}

async function submitPedidosAction(action, reservations) {
    const formData = new FormData();
    formData.append('action', action === 'bulk_approve' ? 'aprovar' : action === 'bulk_reject' ? 'rejeitar' : action);
    formData.append('reservations', JSON.stringify(reservations));
    formData.append('csrf_token', document.getElementById('global-csrf-token')?.value || '');

    const response = await fetch('/admin/api/pedidos_action.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Erro ao processar pedido.');
    return data;
}

// Bulk action functions
function toggleSelectAll() {
    const selectAllCheckbox = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.row-checkbox');

    checkboxes.forEach(checkbox => {
        // Only toggle visible checkboxes
        if (checkbox.closest('tr').style.display !== 'none') {
            checkbox.checked = selectAllCheckbox.checked;
        }
    });

    updateBulkButtons();
}

function updateBulkButtons() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;

    const approveBtn = document.getElementById('bulkApproveBtn');
    const rejectBtn = document.getElementById('bulkRejectBtn');
    const countSpan = document.getElementById('selectedCount');

    if (approveBtn && rejectBtn && countSpan) {
        approveBtn.disabled = count === 0;
        rejectBtn.disabled = count === 0;
        countSpan.textContent = count + ' selecionado' + (count !== 1 ? 's' : '');
    }

    // Update select all checkbox state
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        const visibleCheckboxes = Array.from(document.querySelectorAll('.row-checkbox')).filter(cb =>
            cb.closest('tr').style.display !== 'none'
        );
        const checkedVisibleCheckboxes = visibleCheckboxes.filter(cb => cb.checked);
        selectAllCheckbox.checked = visibleCheckboxes.length > 0 && checkedVisibleCheckboxes.length === visibleCheckboxes.length;
        selectAllCheckbox.indeterminate = checkedVisibleCheckboxes.length > 0 && checkedVisibleCheckboxes.length < visibleCheckboxes.length;
    }
}

function bulkApprove() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Por favor, selecione pelo menos uma reserva.');
        return;
    }

    const reservations = [];
    let summary = '<ul class="text-start">';

    checkboxes.forEach(checkbox => {
        const sala = checkbox.getAttribute('data-sala');
        const tempo = checkbox.getAttribute('data-tempo');
        const data = checkbox.getAttribute('data-data');
        const salaName = checkbox.getAttribute('data-salaname');
        const dataFormatted = checkbox.getAttribute('data-dataformatted');
        const horasName = checkbox.getAttribute('data-horasname');

        reservations.push({
            sala: decodeURIComponent(sala),
            tempo: decodeURIComponent(tempo),
            data: decodeURIComponent(data)
        });

        summary += `<li><strong>${escapeHtml(salaName)}</strong> - ${escapeHtml(dataFormatted)} às ${escapeHtml(horasName)}</li>`;
    });

    summary += '</ul>';

    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const modalHeader = document.getElementById('modalHeader');
    const modalBody = document.getElementById('modalBody');
    const confirmBtn = document.getElementById('confirmBtn');

    modalHeader.className = 'modal-header bg-success text-white';
    modalBody.innerHTML = `
        <div class="text-center mb-3">
            <div style="font-size: 4rem;">&#x2705;</div>
        </div>
        <h5 class="text-center">Aprovar ${reservations.length} reserva(s)?</h5>
        <div class="alert alert-light border mt-3" style="max-height: 300px; overflow-y: auto;">
            ${summary}
        </div>
        <p class="text-muted small text-center mb-0">Os utilizadores serão notificados por email.</p>
    `;
    confirmBtn.className = 'btn btn-success';
    confirmBtn.textContent = 'Aprovar Todas';
    confirmBtn.onclick = function() {
        submitBulkAction('bulk_approve', reservations);
    };

    modal.show();
}

function bulkReject() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkboxes.length === 0) {
        alert('Por favor, selecione pelo menos uma reserva.');
        return;
    }

    const reservations = [];
    let summary = '<ul class="text-start">';

    checkboxes.forEach(checkbox => {
        const sala = checkbox.getAttribute('data-sala');
        const tempo = checkbox.getAttribute('data-tempo');
        const data = checkbox.getAttribute('data-data');
        const salaName = checkbox.getAttribute('data-salaname');
        const dataFormatted = checkbox.getAttribute('data-dataformatted');
        const horasName = checkbox.getAttribute('data-horasname');

        reservations.push({
            sala: decodeURIComponent(sala),
            tempo: decodeURIComponent(tempo),
            data: decodeURIComponent(data)
        });

        summary += `<li><strong>${escapeHtml(salaName)}</strong> - ${escapeHtml(dataFormatted)} às ${escapeHtml(horasName)}</li>`;
    });

    summary += '</ul>';

    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    const modalHeader = document.getElementById('modalHeader');
    const modalBody = document.getElementById('modalBody');
    const confirmBtn = document.getElementById('confirmBtn');

    modalHeader.className = 'modal-header bg-danger text-white';
    modalBody.innerHTML = `
        <div class="text-center mb-3">
            <div style="font-size: 4rem; color: #dc3545;">✕</div>
        </div>
        <h5 class="text-center text-danger">Rejeitar ${reservations.length} reserva(s)?</h5>
        <div class="alert alert-light border mt-3" style="max-height: 300px; overflow-y: auto;">
            ${summary}
        </div>
        <div class="alert alert-warning">
            <strong>Atenção:</strong> Esta ação irá eliminar as reservas permanentemente e notificar os utilizadores.
        </div>
    `;
    confirmBtn.className = 'btn btn-danger';
    confirmBtn.textContent = 'Rejeitar Todas';
    confirmBtn.onclick = function() {
        submitBulkAction('bulk_reject', reservations);
    };

    modal.show();
}

async function submitBulkAction(action, reservations) {
    try {
        const result = await submitPedidosAction(action, reservations);
        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
        showPedidosActionResult(result);
        loadPedidos(true);
    } catch (error) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/admin/pedidos.php?subaction=' + action;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'reservations';
        input.value = JSON.stringify(reservations);
        form.appendChild(input);

        const tokenSource = document.getElementById('global-csrf-token');
        const csrfToken = tokenSource ? tokenSource.value : '';
        if (csrfToken) {
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
        }

        document.body.appendChild(form);
        form.submit();
    }
}

function maybeLoadMore() {
    if (pedidosState.loading || !pedidosState.hasMore) return;
    if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 300) {
        loadPedidos(false);
    }
}

// Initialize Twemoji to parse all emojis on the page
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.getElementById('filterForm');
    if (filterForm) {
        filterForm.addEventListener('submit', function(event) {
            event.preventDefault();
            loadPedidos(true);
        });
        filterForm.querySelectorAll('select, input[type="hidden"]').forEach(function(input) {
            input.addEventListener('change', function() { loadPedidos(true); });
        });
    }

    document.getElementById('pedidosResults')?.addEventListener('click', function(event) {
        const button = event.target.closest('.pedido-action');
        if (!button) return;

        confirmAction(
            button.getAttribute('data-action'),
            button.getAttribute('data-tempo'),
            button.getAttribute('data-data'),
            button.getAttribute('data-sala'),
            button.getAttribute('data-salaname'),
            button.getAttribute('data-dataformatted'),
            button.getAttribute('data-horasname')
        );
    });

    window.addEventListener('scroll', function() {
        maybeLoadMore();
    });

    const hasServerAction = new URLSearchParams(window.location.search).has('subaction');
    if (!hasServerAction) {
        document.getElementById('pedidosAsyncHeader')?.classList.remove('d-none');
        loadPedidos(true);
    }

    twemoji.parse(document.body, {
        folder: 'svg',
        ext: '.svg'
    });
});
</script>
