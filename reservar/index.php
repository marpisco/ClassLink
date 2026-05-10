<?php
require_once(__DIR__ . '/../src/db.php');
session_start();
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    header("Location: /login");
    die("A reencaminhar para iniciar sessão...");
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar uma Sala | ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="/assets/index.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/reservar.css">
    <link rel='icon' href='/assets/logo.png'>
    <script src="/assets/theme-switcher.js"></script>
    <style>
        @media (max-width: 1366px) {
            .table {
                font-size: 0.7rem !important;
            }
            .table th, .table td {
                padding: 2px !important;
                font-size: 0.65rem !important;
            }
        }
        @media (max-width: 768px) {
            .table {
                font-size: 0.6rem !important;
                max-width: 100% !important;
            }
            .table th, .table td {
                padding: 1px !important;
                font-size: 0.55rem !important;
            }
            .bulk-checkbox {
                width: 12px !important;
                height: 12px !important;
            }
        }
        .reservation-table-container {
            overflow-x: auto;
            width: 100%;
        }
    </style>
    <script>
        function updateBulkControls() {
            const checkboxes = document.querySelectorAll('.bulk-checkbox:checked');
            const controls = document.getElementById('bulkReservationControls');
            const counter = document.getElementById('selectedCount');
            
            if (checkboxes.length > 0) {
                controls.style.display = 'block';
                counter.textContent = checkboxes.length + ' tempo' + (checkboxes.length > 1 ? 's' : '') + ' selecionado' + (checkboxes.length > 1 ? 's' : '');
            } else {
                controls.style.display = 'none';
            }
        }
        
        function clearBulkSelection() {
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkControls();
        }
        
        // User selection modal functions for bulk reservations
        function filterBulkUsers() {
            const searchInput = document.getElementById('bulkUserSearchInput');
            const filter = searchInput.value.toLowerCase();
            const userItems = document.querySelectorAll('.bulk-user-item');
            
            userItems.forEach(item => {
                const name = item.getAttribute('data-user-name').toLowerCase();
                const email = item.getAttribute('data-user-email').toLowerCase();
                if (name.includes(filter) || email.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function selectBulkUser(element) {
            const userId = element.getAttribute('data-user-id');
            const userName = element.getAttribute('data-user-name');
            const userEmail = element.getAttribute('data-user-email');
            
            document.getElementById('bulkRequisitor').value = userId;
            document.getElementById('bulkSelectedUserDisplay').value = userName + ' (' + userEmail + ')';
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('bulkUserSelectModal'));
            modal.hide();
        }
        
        function clearBulkUserSelection() {
            document.getElementById('bulkRequisitor').value = '';
            document.getElementById('bulkSelectedUserDisplay').value = '';
            document.getElementById('bulkSelectedUserDisplay').placeholder = 'Reservar para mim mesmo';
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.bulk-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkControls);
            });
        });
    </script>
</head>

<body>
    <nav>
        <a href="/"><img src="/assets/logo.png" class="logo"></a>
        <div class="list">
            <ul>
                <li><a href="/reservas">As minhas reservas</a></li>
                <li><a href="/reservar">Reservar sala</a></li>
                <li><a href="/docs/">Documentação</a></li>
                <?php
                if ($_SESSION['admin']) {
                    echo "<li><a href='/admin'>Painel Administrativo</a></li>";
                }
                ?>
                <li><a href="/login/?action=logout">Terminar sessão</a></li>
            </ul>
        </div>
    </nav>
    <div class="d-flex align-items-center justify-content-center flex-column">
        <p class="h2 fw-light">Reservar uma Sala</p>
        <form action="<?php echo $_SERVER['REQUEST_URI']; ?>" method="POST" class="d-flex align-items-center">
            <div class="form-floating me-2">
                <select class="form-select" id="sala" name="sala" required onchange="this.form.submit();">
                    <?php if ($_POST['sala'] == "0" | !$_POST['sala']) {
                        echo "<option value='0' selected disabled>Escolha uma sala</option>";
                    } else {
                        echo "<option value='0' disabled>Escolha uma sala</option>";
                    }
                    $salas = $db->query("SELECT * FROM salas ORDER BY nome ASC;");
                    while ($sala = $salas->fetch_assoc()) {
                        if ($_POST['sala'] == $sala['id'] || $_GET['sala'] == $sala['id']) {
                            echo "<option value='{$sala['id']}' selected>{$sala['nome']}</option>";
                        } else {
                            echo "<option value='{$sala['id']}'>{$sala['nome']}</option>";
                        }
                    }
                    ?>
                </select>
                <label for="sala" class="form-label">Escolha uma sala</label>
            </div>
        </form>
    </div>
    <?php
    if (isset($_POST['sala']) || isset($_GET['sala'])) {
        // Get the selected room (POST takes precedence over GET for form submissions)
        $sala = isset($_POST['sala']) ? $_POST['sala'] : $_GET['sala'];
        
        // Query room information to check if it's autonomous and if it's locked
        $stmt = $db->prepare("SELECT nome, tipo_sala, bloqueado FROM salas WHERE id = ?");
        $stmt->bind_param("s", $sala);
        $stmt->execute();
        $salaData = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $isAutonomous = ($salaData && $salaData['tipo_sala'] == 2);
        $isLocked = ($salaData && $salaData['bloqueado'] == 1);
        $canCreateReservation = (!$isLocked || $_SESSION['admin']);
        
        echo "<div class='container mt-3 d-flex align-items-center justify-content-center flex-column'>";
        
        // Display locked room notice
        if ($isLocked) {
            if ($_SESSION['admin']) {
                echo "<div class='alert alert-warning mb-3' style='width: 100%;'><strong>Sala Bloqueada:</strong> Esta sala encontra-se bloqueada. Como administrador, pode criar reservas.</div>";
            } else {
                echo "<div class='alert alert-danger mb-3' style='width: 100%;'><strong>Sala Bloqueada:</strong> Esta sala está bloqueada.</div>";
            }
        }
        
        // Check if user has an internal @aejics.org email for autonomous reservations
        $isInternalUser = str_ends_with(strtolower($_SESSION['email']), '@aejics.org');
        
        // Display autonomous reservation message if applicable
        if ($isAutonomous) {
            if ($isInternalUser) {
                echo "<div class='alert alert-info mb-3' style='width: 100%;'><strong>Reserva Autónoma:</strong> Esta sala é de reserva autónoma. A sua reserva será aprovada automaticamente.</div>";
            } else {
                echo "<div class='alert alert-warning mb-3' style='width: 100%;'><strong>Reserva Autónoma:</strong> Esta sala é de reserva autónoma, mas como utilizador externo, a sua reserva necessita de aprovação por um administrador.</div>";
            }
        }
        
        echo (
            "<form id='bulkReservationForm' method='POST' action='/reservar/manage.php?subaction=bulk'>
            <div class='reservation-table-container'>
            <table class='table table-bordered' style='table-layout: fixed; width: 100%; max-width: 70%; margin: 0 auto; font-size: 0.85rem;'><thead><tr><th scope='col' style='font-size: 0.75rem;'>Tempos</th>"
        );
        $today = date("Y-m-d");
        for ($i = 0; $i < 7; $i++) {
            if ($_GET['before']) {
                $segunda = strtotime($_GET['before']);
            } else {
                $segunda = strtotime("monday this week");
            }
            $segundadiaantes = strtotime("-1 week", $segunda);
            $segundadiaantes = date("d-m-Y", $segundadiaantes);
            $segundadiadepois = strtotime("+1 week", $segunda);
            $segundadiadepois = date("d-m-Y", $segundadiadepois);
            $diaObj = strtotime("+{$i} day", $segunda);
            $diaFormatted = date("d/m", $diaObj) . "<br>" . date("Y", $diaObj);
            $headerDate = date("Y-m-d", $diaObj);
            $isHeaderToday = ($headerDate === $today);
            $isHeaderPast = ($headerDate < $today);
            $headerStyle = 'text-align: center; font-size: 0.75rem; padding: 4px;';
            if ($isHeaderToday) {
                $headerStyle .= ' box-shadow: inset 0 0 0 3px #0d6efd; background-color: rgba(13, 110, 253, 0.1);';
            } elseif ($isHeaderPast) {
                $headerStyle .= ' opacity: 0.5;';
            }
            echo "<th scope='col' style='{$headerStyle}'>{$diaFormatted}</th>";
        };
        echo "</tr></thead><tbody>";
        $tempos = $db->query("SELECT * FROM tempos ORDER BY horashumanos ASC;");
        // por cada tempo:
        for ($i = 1; $i <= $tempos->num_rows; $i++) {
            while ($row = $tempos->fetch_assoc()) {
                echo "<tr><th scope='row' style='font-size: 0.75rem; padding: 4px;'>{$row['horashumanos']}</td>";
                // por cada dia da semana:
                for ($j = 0; $j < 7; $j++) {
                    $diacheckdb = $segunda + ($j * 86400);
                    $diacheckdb = date("Y-m-d", $diacheckdb);
                    
                    // Check if this day is today or in the past
                    $isToday = ($diacheckdb === $today);
                    $isPast = ($diacheckdb < $today);
                    $canInteract = (!$isPast || $_SESSION['admin']);
                    
                    $sala = isset($_POST['sala']) ? $_POST['sala'] : $_GET['sala'];
                    
                    $stmt = $db->prepare("SELECT * FROM reservas WHERE sala=? AND data=? AND tempo=?");
                    $stmt->bind_param("sss", $sala, $diacheckdb, $row['id']);
                    $stmt->execute();
                    $tempoatualdb = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Build cell style with highlighting for today and graying out for past days
                    $cellStyle = 'padding: 4px; overflow: hidden; position: relative;';
                    if ($isToday) {
                        $cellStyle .= ' box-shadow: inset 0 0 0 3px #0d6efd;';
                    }
                    
                    if (!$tempoatualdb || $tempoatualdb['aprovado'] == -1) {
                        if ($canCreateReservation && $canInteract) {
                            $innerStyle = 'display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 50px;';
                            if ($isPast) {
                                $innerStyle .= ' opacity: 0.5;';
                            }
                            echo "<td class='bg-success text-white text-center' style='{$cellStyle}'>
                            <div style='{$innerStyle}'>
                            <input type='checkbox' name='slots[]' value='" . urlencode($row['id']) . "|" . urlencode($sala) . "|" . urlencode($diacheckdb) . "' class='bulk-checkbox' style='width: 16px; height: 16px;'>
                            <a class='reserva' href='/reservar/manage.php?tempo=" . urlencode($row['id']) . "&sala=" . urlencode($sala) . "&data=" . urlencode($diacheckdb) . "' style='display: block; font-size: 0.75rem; word-break: break-word;'>
                            Livre
                            </a>
                            </div></td>";
                        } else {
                            $innerStyle = 'display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3px; min-height: 50px;';
                            if ($isPast) {
                                $innerStyle .= ' opacity: 0.5;';
                            }
                            echo "<td class='bg-success text-white text-center' style='{$cellStyle}'>
                            <div style='{$innerStyle}'>
                            <span style='font-size: 0.75rem;'>Livre</span>
                            </div></td>";
                        }
                    } else {
                        $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
                        $stmt->bind_param("s", $tempoatualdb['requisitor']);
                        $stmt->execute();
                        $nomerequisitor = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        $nomerequisitor['nome'] = preg_replace('/^(\S+).*?(\S+)$/u', '$1 $2', $nomerequisitor['nome']);
                        $cellStyleWithHeight = $cellStyle . ' min-height: 50px;';
                        $innerStyle = '';
                        if ($isPast) {
                            $innerStyle = 'opacity: 0.5;';
                        }
                        if ($tempoatualdb['aprovado'] == 0) {
                            if ($canInteract) {
                                echo "<td class='bg-warning text-white text-center' style='{$cellStyleWithHeight}'>
                                <div style='{$innerStyle}'>
                                <a class='reserva' href='/reservar/manage.php?tempo=" . urlencode($row['id']) . "&sala=" . urlencode($sala) . "&data=" . urlencode($diacheckdb) . "' style='font-size: 0.75rem; word-break: break-word;'>
                                Pendente
                                <br>
                                " . htmlspecialchars($nomerequisitor['nome'], ENT_QUOTES, 'UTF-8') . "
                                </a></div></td>";
                            } else {
                                echo "<td class='bg-warning text-white text-center' style='{$cellStyleWithHeight}'>
                                <div style='{$innerStyle}'>
                                <span style='font-size: 0.75rem; word-break: break-word;'>
                                Pendente
                                <br>
                                " . htmlspecialchars($nomerequisitor['nome'], ENT_QUOTES, 'UTF-8') . "
                                </span></div></td>";
                            }
                        } else if ($tempoatualdb['aprovado'] == 1) {
                            if ($canInteract) {
                                echo "<td class='bg-danger text-white text-center' style='{$cellStyleWithHeight}'>
                                <div style='{$innerStyle}'>
                                <a class='reserva' href='/reservar/manage.php?tempo=" . urlencode($row['id']) . "&sala=" . urlencode($sala) . "&data=" . urlencode($diacheckdb) . "' style='font-size: 0.75rem; word-break: break-word;'>
                                Ocupado
                                <br>
                                " . htmlspecialchars($nomerequisitor['nome'], ENT_QUOTES, 'UTF-8') . "
                                </a></div></td>";
                            } else {
                                echo "<td class='bg-danger text-white text-center' style='{$cellStyleWithHeight}'>
                                <div style='{$innerStyle}'>
                                <span style='font-size: 0.75rem; word-break: break-word;'>
                                Ocupado
                                <br>
                                " . htmlspecialchars($nomerequisitor['nome'], ENT_QUOTES, 'UTF-8') . "
                                </span></div></td>";
                            }
                        }
                    }
                }
                echo "</tr>";
            }
        }
        echo "</table><br>
        </div>
        <div id='bulkReservationControls' style='display: none; width: 100%; max-width: 70%; margin: 20px auto 15px auto;'>
            <div class='card'>
                <div class='card-body'>
                    <h5 class='card-title'>Reservas em Massa</h5>
                    <p id='selectedCount'>0 tempos selecionados</p>";
        
        // Show user selection for admins with modal lookup
        if ($_SESSION['admin']) {
            $usersStmt = $db->query("SELECT id, nome, email FROM cache ORDER BY nome ASC");
            $usersData = [];
            while ($user = $usersStmt->fetch_assoc()) {
                $usersData[] = $user;
            }
            echo "<input type='hidden' id='bulkRequisitor' name='requisitor_id' value=''>
            <div class='mb-2'>
                <label class='form-label'><strong>Reservar para utilizador (<span style='color: red'>ADMIN</span>):</strong></label>
                <div class='input-group'>
                    <input type='text' class='form-control' id='bulkSelectedUserDisplay' placeholder='Reservar para mim mesmo' readonly>
                    <button class='btn btn-outline-secondary' type='button' data-bs-toggle='modal' data-bs-target='#bulkUserSelectModal'>
                        Procurar
                    </button>
                    <button class='btn btn-outline-danger' type='button' onclick='clearBulkUserSelection()'>
                        Limpar
                    </button>
                </div>
            </div>";
            
            // User selection modal for bulk reservations
            echo "<div class='modal fade' id='bulkUserSelectModal' tabindex='-1' aria-labelledby='bulkUserSelectModalLabel' aria-hidden='true'>
                <div class='modal-dialog modal-dialog-scrollable'>
                    <div class='modal-content'>
                        <div class='modal-header'>
                            <h5 class='modal-title' id='bulkUserSelectModalLabel'>Selecionar Utilizador</h5>
                            <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fechar'></button>
                        </div>
                        <div class='modal-body'>
                            <div class='mb-3'>
                                <input type='text' class='form-control' id='bulkUserSearchInput' placeholder='Pesquisar por nome ou email...' oninput='filterBulkUsers()'>
                            </div>
                            <div class='list-group' id='bulkUserList'>";
            foreach ($usersData as $user) {
                $userId = htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8');
                $userName = htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8');
                $userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                $isPreRegistered = str_starts_with($user['id'], PRE_REGISTERED_PREFIX);
                $preRegBadge = $isPreRegistered ? " <span class='badge bg-warning text-dark'>Pré-registado</span>" : "";
                echo "<button type='button' class='list-group-item list-group-item-action bulk-user-item' 
                    data-user-id='{$userId}' 
                    data-user-name='{$userName}' 
                    data-user-email='{$userEmail}'
                    onclick='selectBulkUser(this)'>
                    <strong>{$userName}</strong>{$preRegBadge}<br>
                    <small class='text-muted'>{$userEmail}</small>
                </button>";
            }
            echo "</div>
                        </div>
                        <div class='modal-footer'>
                            <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>";
        }
        
        echo "<div class='form-floating mb-2'>
                        <input type='text' class='form-control' id='bulkMotivo' name='motivo' placeholder='Motivo da Reserva' required>
                        <label for='bulkMotivo'>Motivo da Reserva</label>
                    </div>
                    <div class='form-floating mb-2'>
                        <textarea class='form-control' id='bulkExtra' name='extra' placeholder='Informação Extra' rows='3' style='height: 100px;'></textarea>
                        <label for='bulkExtra'>Informação Extra</label>
                    </div>";
        
        // Get materials for the selected room
        $salaForMateriais = isset($_POST['sala']) ? $_POST['sala'] : (isset($_GET['sala']) ? $_GET['sala'] : null);
        
        // Only fetch materials if we have a valid room ID
        if ($salaForMateriais) {
            $materiaisStmt = $db->prepare("SELECT id, nome, descricao FROM materiais WHERE sala_id = ? ORDER BY nome ASC");
            $materiaisStmt->bind_param("s", $salaForMateriais);
            $materiaisStmt->execute();
            $materiaisResult = $materiaisStmt->get_result();
            $materiaisStmt->close();
        } else {
            $materiaisResult = null;
        }
        
        if ($materiaisResult && $materiaisResult->num_rows > 0) {
            echo "<div class='mb-2'>";
            echo "<label class='form-label'><strong>Materiais Disponíveis (opcional):</strong></label>";
            echo "<div class='border rounded p-3' style='max-height: 200px; overflow-y: auto;'>";
            while ($material = $materiaisResult->fetch_assoc()) {
                $materialId = htmlspecialchars($material['id'], ENT_QUOTES, 'UTF-8');
                $materialNome = htmlspecialchars($material['nome'], ENT_QUOTES, 'UTF-8');
                $materialDesc = htmlspecialchars($material['descricao'], ENT_QUOTES, 'UTF-8');
                echo "<div class='form-check'>";
                echo "<input class='form-check-input' type='checkbox' name='materiais[]' value='{$materialId}' id='bulk_material_{$materialId}'>";
                echo "<label class='form-check-label' for='bulk_material_{$materialId}'>";
                echo "<strong>{$materialNome}</strong>";
                if (!empty($materialDesc)) {
                    echo "<br><small class='text-muted'>{$materialDesc}</small>";
                }
                echo "</label>";
                echo "</div>";
            }
            echo "</div>";
            echo "</div>";
        }
        
        echo "
                    <button type='submit' class='btn btn-success me-2'>Reservar Selecionados</button>
                    <button type='button' class='btn btn-secondary' onclick='clearBulkSelection()'>Limpar Seleção</button>
                </div>
            </div>
        </div>
        </form>";
        $currentSalaId = $_POST['sala'] ?? $_GET['sala'];
        echo "<div class='d-flex gap-2 mt-2'>";
        echo "<a href='/reservar/?before={$segundadiaantes}&sala={$currentSalaId}' class='btn mb-2 btn-success'>Semana Anterior</a>";
        echo "<a href='/reservar/?sala={$currentSalaId}' class='btn mb-2 btn-primary'>Semana Atual</a>";
        echo "<a href='/reservar/?before={$segundadiadepois}&sala={$currentSalaId}' class='btn mb-2 btn-success'>Semana Seguinte</a>";
        echo "</div></div>";
    }
    ?>
</body>

</html>