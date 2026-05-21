<?php 
require __DIR__ . '/index.php';
require_once(__DIR__ . '/../func/email_helper.php');
require_once(__DIR__ . '/../func/csrf.php');
require_once(__DIR__ . '/../func/validation.php');
?>
<div style="margin-left: 20%; margin-right: 20%; text-align: center;">
<h1>Reserva em Massa</h1>
<p>Este script permite criar reservas em massa de salas ao longo de várias semanas.</p>
<p>Selecione a sala, o utilizador, os tempos desejados e o intervalo de datas para criar as reservas.</p>

<style>
    body {
        overflow-y: auto !important;
    }
    
    .time-checkbox-container {
        max-height: 250px;
        overflow-y: auto;
        overflow-x: hidden;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
    }
    
    @media (min-height: 768px) {
        .time-checkbox-container {
            max-height: 350px;
        }
    }
    
    @media (min-height: 1024px) {
        .time-checkbox-container {
            max-height: 450px;
        }
    }
    
    .time-checkbox-item {
        padding: 0.5rem;
        margin-bottom: 0.5rem;
        border: 1px solid #e0e0e0;
        border-radius: 0.25rem;
        background-color: #f8f9fa;
        word-wrap: break-word;
    }
    .time-checkbox-item:hover {
        background-color: #e9ecef;
    }
    
    /* Dark mode support for time checkbox items */
    @media (prefers-color-scheme: dark) {
        .time-checkbox-item {
            background-color: #343a40;
            border-color: #495057;
            color: #f8f9fa;
        }
        .time-checkbox-item:hover {
            background-color: #495057;
        }
    }
    
    .form-floating label {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }
</style>

<script>
    // User selection modal functions
    function filterUsers() {
        const searchInput = document.getElementById('userSearchInput');
        const filter = searchInput.value.toLowerCase();
        const userItems = document.querySelectorAll('.user-item');
        
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
    
    function selectUser(element) {
        const userId = element.getAttribute('data-user-id');
        const userName = element.getAttribute('data-user-name');
        const userEmail = element.getAttribute('data-user-email');
        
        document.getElementById('requisitor').value = userId;
        document.getElementById('selectedUserDisplay').value = userName + ' (' + userEmail + ')';
        
        // Close the modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('userSelectModal'));
        modal.hide();
    }
    
    function clearUserSelection() {
        document.getElementById('requisitor').value = '';
        document.getElementById('selectedUserDisplay').value = '';
        document.getElementById('selectedUserDisplay').placeholder = 'Selecione um utilizador...';
    }

    const lookupConfig = {
        requisitor: {
            endpoint: '/admin/api/requisitor_lookup.php',
            inputId: 'lookupRequisitorInput',
            resultsId: 'lookupRequisitorResults',
            emptyMessage: 'Sem resultados de requisitorID.'
        },
        tempo: {
            endpoint: '/admin/api/tempo_lookup.php',
            inputId: 'lookupTempoInput',
            resultsId: 'lookupTempoResults',
            emptyMessage: 'Sem resultados de tempoID.'
        },
        sala: {
            endpoint: '/admin/api/sala_lookup.php',
            inputId: 'lookupSalaInput',
            resultsId: 'lookupSalaResults',
            emptyMessage: 'Sem resultados de salaID.'
        }
    };

    function lookupEscapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showLookupSkeleton(targetId) {
        const target = document.getElementById(targetId);
        target.innerHTML = `
            <div class="placeholder-glow mb-2"><span class="placeholder col-12"></span></div>
            <div class="placeholder-glow mb-2"><span class="placeholder col-10"></span></div>
            <div class="placeholder-glow"><span class="placeholder col-8"></span></div>
        `;
    }

    function searchLookup(type) {
        const config = lookupConfig[type];
        if (!config) return;

        const input = document.getElementById(config.inputId);
        const target = document.getElementById(config.resultsId);
        const query = input.value.trim();

        if (query.length < 2) {
            target.innerHTML = "<div class='alert alert-info py-2 mb-0'>Digite pelo menos 2 caracteres para pesquisar (máx. 10 resultados).</div>";
            return;
        }

        showLookupSkeleton(config.resultsId);

        const params = new URLSearchParams();
        params.set('q', query);
        fetch(config.endpoint + '?' + params.toString())
            .then(response => response.json())
            .then(data => {
                if (!Array.isArray(data.items) || data.items.length === 0) {
                    target.innerHTML = "<div class='alert alert-warning py-2 mb-0'>" + config.emptyMessage + "</div>";
                    return;
                }

                const itemsHtml = data.items.slice(0, 10).map(item => {
                    const title = item.title ? `<strong>${lookupEscapeHtml(item.title)}</strong><br>` : '';
                    const subtitle = item.subtitle ? `<small class='text-muted'>${lookupEscapeHtml(item.subtitle)}</small><br>` : '';
                    const itemId = lookupEscapeHtml(item.id || '');
                    return `<div class='list-group-item'>
                        ${title}
                        ${subtitle}
                        <code class='user-select-all'>${itemId}</code>
                    </div>`;
                }).join('');

                target.innerHTML = `<div class="small text-muted mb-2">A mostrar até 10 resultados.</div><div class="list-group">${itemsHtml}</div>`;
            })
            .catch(() => {
                target.innerHTML = "<div class='alert alert-danger py-2 mb-0'>Erro ao pesquisar. Tente novamente.</div>";
            });
    }

    function initLookupTabs() {
        const tabButtons = document.querySelectorAll('#csvLookupTabs button[data-bs-toggle="tab"]');
        tabButtons.forEach(button => {
            button.addEventListener('shown.bs.tab', function (event) {
                const targetType = event.target.getAttribute('data-lookup-type');
                if (!targetType) return;
                searchLookup(targetType);
            });
        });
    }
    
    // Form validation
    function validateForm(event) {
        const requisitor = document.getElementById('requisitor').value;
        if (!requisitor) {
            event.preventDefault();
            alert('Por favor, selecione um utilizador.');
            return false;
        }
        return true;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('massReservationForm');
        if (form) {
            form.addEventListener('submit', validateForm);
        }
        initLookupTabs();
        searchLookup('requisitor');
    });
</script>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'import_csv') {
    $tempFile = null;
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'><strong>Erro:</strong> Token CSRF inválido.</div>";
    } elseif (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'><strong>Erro:</strong> Erro ao fazer upload do ficheiro CSV.</div>";
    } else {
        $tempFile = fopen($_FILES['csvfile']['tmp_name'], 'r');
        if ($tempFile === false) {
            echo "<div class='mt-3 alert alert-danger fade show' role='alert'><strong>Erro:</strong> Não foi possível ler o ficheiro CSV.</div>";
            $tempFile = null;
        }
    }

    if ($tempFile) {

        $salasValidas = [];
        $stmtSalas = $db->prepare("SELECT id FROM salas");
        $stmtSalas->execute();
        $resultSalas = $stmtSalas->get_result();
        while ($row = $resultSalas->fetch_assoc()) {
            $salasValidas[$row['id']] = true;
        }
        $stmtSalas->close();

        $requisitoresValidos = [];
        $stmtRequisitores = $db->prepare("SELECT id FROM cache");
        $stmtRequisitores->execute();
        $resultRequisitores = $stmtRequisitores->get_result();
        while ($row = $resultRequisitores->fetch_assoc()) {
            $requisitoresValidos[$row['id']] = true;
        }
        $stmtRequisitores->close();

        $temposValidos = [];
        $stmtTempos = $db->prepare("SELECT id FROM tempos");
        $stmtTempos->execute();
        $resultTempos = $stmtTempos->get_result();
        while ($row = $resultTempos->fetch_assoc()) {
            $temposValidos[$row['id']] = true;
        }
        $stmtTempos->close();

        $stmtCheck = $db->prepare("SELECT 1 FROM reservas WHERE sala = ? AND tempo = ? AND data = ? LIMIT 1");
        $stmtInsert = $db->prepare("INSERT INTO reservas (sala, tempo, data, requisitor, aprovado, motivo, extra) VALUES (?, ?, ?, ?, 1, ?, ?)");

        $lineNumber = 0;
        $successCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;
        $errors = [];

        while (($data = fgetcsv($tempFile, 0, ';')) !== false) {
            $lineNumber++;

            if (!isset($data[0]) || trim($data[0]) === '') {
                continue;
            }

            $firstColumn = preg_replace('/^\xEF\xBB\xBF/', '', trim($data[0]));
            if ($lineNumber === 1 && strtolower($firstColumn) === 'salaid') {
                continue;
            }

            if (count($data) < 5) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber} inválida (mínimo 5 colunas).";
                continue;
            }

            $salaId = $firstColumn;
            $requisitorId = trim($data[1]);
            $tempoId = trim($data[2]);
            $dataReserva = trim($data[3]);
            $motivo = trim($data[4]);
            $extra = isset($data[5]) ? trim($data[5]) : '';

            if (!validate_date($dataReserva)) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Data inválida '{$dataReserva}' (formato esperado: YYYY-MM-DD).";
                continue;
            }

            if (!isset($salasValidas[$salaId])) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Sala inválida '{$salaId}'.";
                continue;
            }

            if (!isset($requisitoresValidos[$requisitorId])) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Requisitor inválido '{$requisitorId}'.";
                continue;
            }

            if (!isset($temposValidos[$tempoId])) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Tempo inválido '{$tempoId}'.";
                continue;
            }

            if ($motivo === '') {
                $motivo = 'Importada via CSV (reserva em massa)';
            }

            $stmtCheck->bind_param("sss", $salaId, $tempoId, $dataReserva);
            $stmtCheck->execute();
            $exists = $stmtCheck->get_result()->fetch_assoc();
            if ($exists) {
                $duplicateCount++;
                continue;
            }

            $stmtInsert->bind_param("ssssss", $salaId, $tempoId, $dataReserva, $requisitorId, $motivo, $extra);
            if ($stmtInsert->execute()) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Erro ao inserir reserva.";
            }
        }

        $stmtCheck->close();
        $stmtInsert->close();
        fclose($tempFile);

        if ($successCount > 0) {
            echo "<div class='mt-3 alert alert-success fade show' role='alert'><strong>Sucesso:</strong> {$successCount} reserva(s) importada(s) com sucesso.</div>";
            acaoexecutada("Importação de Reservas em Massa via CSV");
        }
        if ($duplicateCount > 0) {
            echo "<div class='mt-3 alert alert-warning fade show' role='alert'><strong>Atenção:</strong> {$duplicateCount} reserva(s) já existia(m) e foi/foram ignorada(s).</div>";
        }
        if ($errorCount > 0) {
            $displayLimit = 10;
            $displayedErrors = array_slice(array_map(function($error) {
                return htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
            }, $errors), 0, $displayLimit);
            $truncatedNote = $errorCount > $displayLimit ? "<br><em>A mostrar os primeiros {$displayLimit} de {$errorCount} erro(s).</em>" : "";
            echo "<div class='mt-3 alert alert-danger fade show' role='alert'><strong>Erros:</strong> {$errorCount} linha(s) com erro.<br>" . implode('<br>', $displayedErrors) . $truncatedNote . "</div>";
        }
    }
}
?>

<div class="mb-4 p-3 border rounded bg-light-subtle">
    <h5>Importar Reservas via CSV</h5>
    <a href="../assets/csvsample_reservas.csv" download>Download do modelo CSV</a>
    <p class="text-muted small mb-2"><strong>Formato:</strong> SalaID;RequisitorID;TempoID;Data(YYYY-MM-DD);Motivo;Extra(opcional)</p>
    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" data-bs-toggle="modal" data-bs-target="#csvLookupModal">
        Pesquisar IDs (Requisitor/Tempo/Sala)
    </button>
    <form action="reservaemmassa.php?action=import_csv" method="POST" enctype="multipart/form-data" class="d-flex align-items-center justify-content-center">
        <?php echo csrf_token_field(); ?>
        <div class="me-2">
            <input type="file" class="form-control" id="csvfile" name="csvfile" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">Importar CSV</button>
    </form>
</div>

<div class="modal fade" id="csvLookupModal" tabindex="-1" aria-labelledby="csvLookupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="csvLookupModalLabel">Pesquisar IDs para CSV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs mb-3" id="csvLookupTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="requisitor-tab" data-bs-toggle="tab" data-bs-target="#requisitor-pane" type="button" role="tab" data-lookup-type="requisitor">requisitorID</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tempo-tab" data-bs-toggle="tab" data-bs-target="#tempo-pane" type="button" role="tab" data-lookup-type="tempo">tempoID</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="sala-tab" data-bs-toggle="tab" data-bs-target="#sala-pane" type="button" role="tab" data-lookup-type="sala">salaID</button>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="requisitor-pane" role="tabpanel" aria-labelledby="requisitor-tab">
                        <input type="text" class="form-control mb-2" id="lookupRequisitorInput" placeholder="Filtrar requisitorID, nome ou email..." oninput="searchLookup('requisitor')">
                        <div id="lookupRequisitorResults"><div class="alert alert-info py-2 mb-0">Digite pelo menos 2 caracteres para pesquisar (máx. 10 resultados).</div></div>
                    </div>
                    <div class="tab-pane fade" id="tempo-pane" role="tabpanel" aria-labelledby="tempo-tab">
                        <input type="text" class="form-control mb-2" id="lookupTempoInput" placeholder="Filtrar tempoID ou horário..." oninput="searchLookup('tempo')">
                        <div id="lookupTempoResults"><div class="alert alert-info py-2 mb-0">Digite pelo menos 2 caracteres para pesquisar (máx. 10 resultados).</div></div>
                    </div>
                    <div class="tab-pane fade" id="sala-pane" role="tabpanel" aria-labelledby="sala-tab">
                        <input type="text" class="form-control mb-2" id="lookupSalaInput" placeholder="Filtrar salaID ou nome da sala..." oninput="searchLookup('sala')">
                        <div id="lookupSalaResults"><div class="alert alert-info py-2 mb-0">Digite pelo menos 2 caracteres para pesquisar (máx. 10 resultados).</div></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <small class="text-muted me-auto">Resultados limitados a 10 por pesquisa para reduzir carga na base de dados.</small>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<form id="massReservationForm" action="reservaemmassa.php" method="POST" class="mt-4">
    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-floating">
                <select class="form-select" id="sala" name="sala" required>
                    <option value="" selected disabled>Escolha uma sala</option>
                    <?php
                    $salas = $db->query("SELECT * FROM salas ORDER BY nome ASC;");
                    while ($sala = $salas->fetch_assoc()) {
                        $selected = (isset($_POST['sala']) && $_POST['sala'] == $sala['id']) ? 'selected' : '';
                        echo "<option value='{$sala['id']}' {$selected}>" . htmlspecialchars($sala['nome'], ENT_QUOTES, 'UTF-8') . "</option>";
                    }
                    ?>
                </select>
                <label for="sala">Sala</label>
            </div>
        </div>
        <div class="col-md-6">
            <input type="hidden" id="requisitor" name="requisitor" value="<?php echo isset($_POST['requisitor']) ? htmlspecialchars($_POST['requisitor'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
            <label class="form-label text-start d-block"><strong>Utilizador (requisitor):</strong></label>
            <div class="input-group">
                <input type="text" class="form-control" id="selectedUserDisplay" placeholder="Selecione um utilizador..." readonly value="<?php
                    if (isset($_POST['requisitor']) && !empty($_POST['requisitor'])) {
                        $userStmt = $db->prepare("SELECT nome, email FROM cache WHERE id = ?");
                        $userStmt->bind_param("s", $_POST['requisitor']);
                        $userStmt->execute();
                        $selectedUser = $userStmt->get_result()->fetch_assoc();
                        $userStmt->close();
                        if ($selectedUser) {
                            echo htmlspecialchars($selectedUser['nome'] . ' (' . $selectedUser['email'] . ')', ENT_QUOTES, 'UTF-8');
                        }
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
    </div>

    <div class="row mb-3">
        <div class="col-md-12">
            <label class="form-label"><strong>Tempos (selecione os horários para reservar):</strong></label>
            <div class="time-checkbox-container">
                <?php
                $tempos = $db->query("SELECT * FROM tempos ORDER BY horashumanos ASC;");
                while ($tempo = $tempos->fetch_assoc()) {
                    $checked = (isset($_POST['tempos']) && in_array($tempo['id'], $_POST['tempos'])) ? 'checked' : '';
                    echo "<div class='time-checkbox-item'>
                        <div class='form-check'>
                            <input class='form-check-input' type='checkbox' name='tempos[]' value='{$tempo['id']}' id='tempo_{$tempo['id']}' {$checked}>
                            <label class='form-check-label' for='tempo_{$tempo['id']}'>
                                " . htmlspecialchars($tempo['horashumanos'], ENT_QUOTES, 'UTF-8') . "
                            </label>
                        </div>
                    </div>";
                }
                ?>
            </div>
            <small class="text-muted">Selecione um ou mais tempos para reservar em cada semana</small>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <div class="form-floating">
                <select class="form-select" id="dia_semana" name="dia_semana" required>
                    <option value="" selected disabled>Escolha um dia</option>
                    <?php
                    $dias = [
                        '1' => 'Segunda-feira',
                        '2' => 'Terça-feira',
                        '3' => 'Quarta-feira',
                        '4' => 'Quinta-feira',
                        '5' => 'Sexta-feira',
                        '6' => 'Sábado',
                        '0' => 'Domingo'
                    ];
                    foreach ($dias as $value => $label) {
                        $selected = (isset($_POST['dia_semana']) && $_POST['dia_semana'] == $value) ? 'selected' : '';
                        echo "<option value='{$value}' {$selected}>{$label}</option>";
                    }
                    ?>
                </select>
                <label for="dia_semana">Dia da semana</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-floating">
                <input type="date" class="form-control" id="data_inicio" name="data_inicio" placeholder="Data de início" value="<?php echo isset($_POST['data_inicio']) ? htmlspecialchars($_POST['data_inicio']) : ''; ?>" required>
                <label for="data_inicio" title="Data de início">Data de início</label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="form-floating">
                <input type="date" class="form-control" id="data_fim" name="data_fim" placeholder="Data de fim" value="<?php echo isset($_POST['data_fim']) ? htmlspecialchars($_POST['data_fim']) : ''; ?>" required>
                <label for="data_fim" title="Data de fim">Data de fim</label>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <div class="form-floating">
                <input type="text" class="form-control" id="motivo" name="motivo" placeholder="Motivo" value="<?php echo isset($_POST['motivo']) ? htmlspecialchars($_POST['motivo'], ENT_QUOTES, 'UTF-8') : 'Importada automaticamente do horário'; ?>" required>
                <label for="motivo">Motivo</label>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-floating">
                <textarea class="form-control" id="extra" name="extra" placeholder="Informação Adicional" style="height: 58px;"><?php echo isset($_POST['extra']) ? htmlspecialchars($_POST['extra'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                <label for="extra">Informação Adicional</label>
            </div>
        </div>
    </div>

    <div class="d-grid">
        <button type="submit" class="btn btn-primary btn-lg">Criar Reservas em Massa</button>
    </div>
</form>

<div class="modal fade" id="userSelectModal" tabindex="-1" aria-labelledby="userSelectModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userSelectModalLabel">Selecionar Utilizador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="userSearchInput" placeholder="Pesquisar por nome ou email..." oninput="filterUsers()">
                </div>
                <div class="list-group" id="userList">
                    <?php
                    $users = $db->query("SELECT id, nome, email FROM cache ORDER BY nome ASC");
                    while ($user = $users->fetch_assoc()) {
                        $userId = htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8');
                        $userName = htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8');
                        $userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                        $isPreRegistered = str_starts_with($user['id'], PRE_REGISTERED_PREFIX);
                        $preRegBadge = $isPreRegistered ? " <span class='badge bg-warning text-dark'>Pré-registado</span>" : "";
                        echo "<button type='button' class='list-group-item list-group-item-action user-item' 
                            data-user-id='{$userId}' 
                            data-user-name='{$userName}' 
                            data-user-email='{$userEmail}'
                            onclick='selectUser(this)'>
                            <strong>{$userName}</strong>{$preRegBadge}<br>
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

<?php 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sala']) && isset($_POST['requisitor']) && isset($_POST['tempos']) && isset($_POST['dia_semana']) && isset($_POST['data_inicio']) && isset($_POST['data_fim']) && isset($_POST['motivo'])) {
    
    // Validate inputs
    $sala_id = $_POST['sala'];
    $requisitor_id = $_POST['requisitor'];
    $tempos_ids = $_POST['tempos'];
    $dia_semana = intval($_POST['dia_semana']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $motivo = $_POST['motivo'];
    $extra = isset($_POST['extra']) ? $_POST['extra'] : '';
    
    if (empty($tempos_ids)) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
            <strong>Erro:</strong> Deve selecionar pelo menos um tempo.
        </div>";
    } elseif (empty($motivo)) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
            <strong>Erro:</strong> O campo Motivo é obrigatório.
        </div>";
    } elseif (strtotime($data_fim) < strtotime($data_inicio)) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
            <strong>Erro:</strong> A data de fim deve ser igual ou posterior à data de início.
        </div>";
    } else {
        // Verify sala exists
        $stmt = $db->prepare("SELECT * FROM salas WHERE id = ?");
        $stmt->bind_param("s", $sala_id);
        $stmt->execute();
        $sala = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        // Verify requisitor exists
        $stmt = $db->prepare("SELECT * FROM cache WHERE id = ?");
        $stmt->bind_param("s", $requisitor_id);
        $stmt->execute();
        $requisitor = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$sala || !$requisitor) {
            echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
                <strong>Erro:</strong> Sala ou utilizador inválido.
            </div>";
        } else {
            // Calculate first occurrence of the selected day of week from data_inicio
            $first_date = strtotime($data_inicio);
            $end_date = strtotime($data_fim);
            $first_day_of_week = date('w', $first_date);
            
            // Adjust to the next occurrence of the selected day if needed
            if ($first_day_of_week != $dia_semana) {
                $days_to_add = ($dia_semana - $first_day_of_week + 7) % 7;
                $first_date = strtotime("+{$days_to_add} days", $first_date);
            }
            
            // Check if the first occurrence is after the end date
            if ($first_date > $end_date) {
                echo "<div class='mt-3 alert alert-warning fade show' role='alert'>
                    <strong>Atenção:</strong> O dia da semana selecionado não ocorre dentro do intervalo de datas especificado. Nenhuma reserva foi criada.
                </div>";
            } else {
                $reservas_criadas = 0;
                $reservas_duplicadas = 0;
                $erros = [];
                $num_semanas = 0;
                
                // Create reservations for each week until we pass the end date
                $current_date = $first_date;
                while ($current_date <= $end_date) {
                    $num_semanas++;
                    $data_reserva_formatted = date('Y-m-d', $current_date);
                    
                    // Create reservation for each selected time
                    foreach ($tempos_ids as $tempo_id) {
                        // Verify tempo exists
                        $stmt = $db->prepare("SELECT * FROM tempos WHERE id = ?");
                        $stmt->bind_param("s", $tempo_id);
                        $stmt->execute();
                        $tempo = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        if (!$tempo) {
                            $erros[] = "Tempo inválido: {$tempo_id}";
                            continue;
                        }
                        
                        // Check if reservation already exists
                        $stmt = $db->prepare("SELECT * FROM reservas WHERE sala = ? AND tempo = ? AND data = ?");
                        $stmt->bind_param("sss", $sala_id, $tempo_id, $data_reserva_formatted);
                        $stmt->execute();
                        $existing = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        if ($existing) {
                            $reservas_duplicadas++;
                            continue;
                        }
                        
                        // Insert reservation with user-provided motivo and extra
                        $stmt = $db->prepare("INSERT INTO reservas (sala, tempo, data, requisitor, aprovado, motivo, extra) VALUES (?, ?, ?, ?, 1, ?, ?)");
                        $stmt->bind_param("ssssss", $sala_id, $tempo_id, $data_reserva_formatted, $requisitor_id, $motivo, $extra);
                        
                        if ($stmt->execute()) {
                            $reservas_criadas++;
                        } else {
                            $erros[] = "Erro ao criar reserva para {$data_reserva_formatted} - {$tempo['horashumanos']}: " . $stmt->error;
                        }
                        $stmt->close();
                    }
                    
                    // Move to next week
                    $current_date = strtotime("+1 week", $current_date);
                }
                
                // Send email notification to the user if any reservations were created
                if ($reservas_criadas > 0) {
                    sendRecurringWeeklyReservationsEmail(
                        $db,
                        $requisitor_id,
                        $reservas_criadas,
                        $reservas_duplicadas,
                        $sala_id,
                        $dia_semana,
                        $data_inicio,
                        $data_fim,
                        $num_semanas,
                        count($tempos_ids),
                        $motivo
                    );
                }
                
                // Display results
                echo "<div class='mt-3 alert alert-success fade show' role='alert'>
                    <strong>Sucesso!</strong> {$reservas_criadas} reserva(s) criada(s) com sucesso.
                </div>";
                
                if ($reservas_duplicadas > 0) {
                    echo "<div class='mt-3 alert alert-warning fade show' role='alert'>
                        <strong>Atenção:</strong> {$reservas_duplicadas} reserva(s) já existia(m) e não foi/foram criada(s).
                    </div>";
                }
                
                if (!empty($erros)) {
                    echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
                        <strong>Erros encontrados:</strong>
                        <ul class='mb-0'>";
                    foreach ($erros as $erro) {
                        echo "<li>" . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . "</li>";
                    }
                    echo "</ul></div>";
                }
                
                // Summary
                echo "<div class='mt-3 alert alert-info fade show' role='alert'>
                    <strong>Resumo:</strong><br>
                    - Sala: " . htmlspecialchars($sala['nome'], ENT_QUOTES, 'UTF-8') . "<br>
                    - Utilizador: " . htmlspecialchars($requisitor['nome'], ENT_QUOTES, 'UTF-8') . "<br>
                    - Tempos selecionados: " . count($tempos_ids) . "<br>
                    - Semanas abrangidas: {$num_semanas}<br>
                    - Total de reservas esperadas: " . (count($tempos_ids) * $num_semanas) . "<br>
                    - Reservas criadas: {$reservas_criadas}<br>
                    - Motivo: " . htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') . "
                </div>";
            }
        }
    }
}
?>
</div>