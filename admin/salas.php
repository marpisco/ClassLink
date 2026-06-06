<?php require 'index.php'; ?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão das Salas</h3>
<div class="d-flex align-items-center justify-content-center mb-3">
    <span class="me-3">Adicionar uma sala</span>
    <?php formulario("salas.php?action=criar", [
        ["type" => "text", "id" => "nomesala", "placeholder" => "Sala", "label" => "Sala", "value" => null]
    ]); ?>
</div>

<?php
// Destructive actions must be POST. CSRF is validated globally in
// admin/index.php for any POST to /admin/*.
$destructiveActions = ['apagar'];
$actionParam = $_GET['action'] ?? null;
if (in_array($actionParam, $destructiveActions, true) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo "<div class='alert alert-danger fade show' role='alert'>Pedido inválido. As ações destrutivas requerem POST.</div>";
    return;
}

switch (isset($_GET['action']) ? $_GET['action'] : null){
    // caso seja preenchido o formulário de criação:
    case "criar":
        if (!isset($_POST['nomesala'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        $randomuuid = uuid4();
        $stmt = $db->prepare("INSERT INTO salas (id, nome) VALUES (?, ?)");
        $stmt->bind_param("ss", $randomuuid, $_POST["nomesala"]);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Criação de Sala");
        break;
    // caso execute a ação apagar:
    case "apagar":
        if (!isset($_POST['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        try {
            $stmt = $db->prepare("SELECT * FROM reservas WHERE sala = ? AND aprovado != -1");
            $stmt->bind_param("s", $_POST['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Existem reservas associadas a esta sala. Por segurança, é necessária uma intervenção manual de um Administrador.");
            }
            $stmt->close();
        } catch (Exception $e) {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            break;
        }
        $stmt = $db->prepare("DELETE FROM salas WHERE id = ?");
        $stmt->bind_param("s", $_POST['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Eliminação de Sala");
        break;
    // caso execute a ação editar:
    case "edit":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        $stmt = $db->prepare("SELECT * FROM salas WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$d) {
            echo "<div class='alert alert-danger fade show' role='alert'>Sala não encontrada.</div>";
            break;
        }
        echo "<div class='alert alert-warning fade show' role='alert'>A editar a Sala <b>" . htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') . "</b>.</div>";
        ?>
        <form action="salas.php?action=update&id=<?php echo urlencode($d['id']); ?>" method="POST" class="d-flex align-items-center">
            <div class="form-floating me-2" style="flex: 1;">
                <input type="text" class="form-control form-control-sm" id="nomesala" name="nomesala" placeholder="Sala" value="<?php echo htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <label for="nomesala">Sala</label>
            </div>
            <div class="form-floating me-2" style="flex: 1;">
                <select class="form-select form-select-sm" id="tipo_sala" name="tipo_sala" required>
                    <option value="1" <?php echo ($d['tipo_sala'] == 1 || !isset($d['tipo_sala'])) ? 'selected' : ''; ?>>Normal (Requer Aprovação)</option>
                    <option value="2" <?php echo ($d['tipo_sala'] == 2) ? 'selected' : ''; ?>>Reserva Autónoma</option>
                </select>
                <label for="tipo_sala">Tipo de Sala</label>
            </div>
            <div class="form-floating me-2" style="flex: 1;">
                <select class="form-select form-select-sm" id="bloqueado" name="bloqueado" required>
                    <option value="0" <?php echo (!isset($d['bloqueado']) || $d['bloqueado'] == 0) ? 'selected' : ''; ?>>Desbloqueada</option>
                    <option value="1" <?php echo ($d['bloqueado'] == 1) ? 'selected' : ''; ?>>Bloqueada (Apenas Admins)</option>
                </select>
                <label for="bloqueado">Estado</label>
            </div>
            <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">Submeter</button>
        </form>
        <?php
        break;
    // caso seja submetida a edição:
    case "update":
        if (!isset($_GET['id']) || !isset($_POST['nomesala']) || !isset($_POST['tipo_sala']) || !isset($_POST['bloqueado'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        $tipo_sala = intval($_POST['tipo_sala']);
        if ($tipo_sala != 1 && $tipo_sala != 2) {
            echo "<div class='alert alert-danger fade show' role='alert'>Tipo de sala inválido.</div>";
            break;
        }
        $bloqueado = intval($_POST['bloqueado']);
        if ($bloqueado != 0 && $bloqueado != 1) {
            echo "<div class='alert alert-danger fade show' role='alert'>Estado de sala inválido.</div>";
            break;
        }
        $stmt = $db->prepare("UPDATE salas SET nome = ?, tipo_sala = ?, bloqueado = ? WHERE id = ?");
        $stmt->bind_param("siis", $_POST['nomesala'], $tipo_sala, $bloqueado, $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Atualização de Sala");
        break;
}

$numSalas = $db->query("SELECT COUNT(*) as total FROM salas")->fetch_assoc()['total'];
$db->close();
?>

<div class="mt-4">
    <h5>Lista de Salas (<?php echo $numSalas; ?>)</h5>
    <div class="mb-3">
        <input type="text" class="form-control" id="salasSearchInput" placeholder="Pesquisar salas..." oninput="searchSalas()">
    </div>
    <div id="salasListContainer">
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">A carregar...</span>
            </div>
        </div>
    </div>
    <div id="salasLoadMore" class="text-center mt-3" style="display: none;">
        <button class="btn btn-outline-primary" onclick="loadMoreSalas()">Carregar mais</button>
    </div>
</div>

<script>
let salasOffset = 0;
let salasSearchQuery = '';
let salasLoading = false;
let salasHasMore = true;
const salasLimit = 20;

function searchSalas() {
    salasSearchQuery = document.getElementById('salasSearchInput').value;
    salasOffset = 0;
    salasHasMore = true;
    loadSalas(true);
}

function loadSalas(reset = false) {
    if (salasLoading) return;
    salasLoading = true;
    
    const container = document.getElementById('salasListContainer');
    const loadMoreBtn = document.getElementById('salasLoadMore');
    
    if (reset) {
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">A carregar...</span></div></div>';
    }
    
    const url = '/admin/api/salas_search.php?limit=' + salasLimit + '&offset=' + salasOffset + '&q=' + encodeURIComponent(salasSearchQuery);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            salasLoading = false;
            
            if (reset) {
                container.innerHTML = '';
            }
            
            if (data.salas.length === 0 && salasOffset === 0) {
                container.innerHTML = '<div class="alert alert-warning">Não existem salas.</div>';
                loadMoreBtn.style.display = 'none';
                return;
            }
            
            let tableHtml = '';
            if (salasOffset === 0) {
                tableHtml = `<table class='table table-striped table-hover'>
                    <thead class='table-dark'>
                        <tr>
                            <th scope='col'>Sala</th>
                            <th scope='col'>Tipo de Sala</th>
                            <th scope='col'>Estado</th>
                            <th scope='col'>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id='salasTableBody'>`;
            }
            
            data.salas.forEach(sala => {
                const idEnc = encodeURIComponent(sala.id);
                const tipoSala = (sala.tipo_sala == 2) ? "<span class='badge bg-success'>Reserva Autónoma</span>" : "<span class='badge bg-primary'>Normal</span>";
                const estadoSala = (sala.bloqueado == 1) ? "<span class='badge bg-danger'>Bloqueada</span>" : "<span class='badge bg-success'>Desbloqueada</span>";
                
                const rowHtml = `<tr>
                    <td>${escapeHtml(sala.nome)}</td>
                    <td>${tipoSala}</td>
                    <td>${estadoSala}</td>
                    <td>
                        <a href='/admin/salas.php?action=edit&id=${idEnc}' class='btn btn-sm btn-primary'>EDITAR</a>
                        <form action='/admin/salas.php' method='POST' style='display:inline;' onsubmit='return confirm("Tem a certeza que pretende apagar a sala? Isto irá causar problemas se a sala tiver reservas passadas.");'><input type='hidden' name='action' value='apagar'><input type='hidden' name='id' value='${idEnc}'><button type='submit' class='btn btn-sm btn-danger'>APAGAR</button></form>
                    </td>
                </tr>`;
                
                if (salasOffset === 0) {
                    tableHtml += rowHtml;
                } else {
                    document.getElementById('salasTableBody').insertAdjacentHTML('beforeend', rowHtml);
                }
            });
            
            if (salasOffset === 0) {
                tableHtml += '</tbody></table>';
                container.innerHTML = tableHtml;
            }
            
            salasOffset += data.salas.length;
            salasHasMore = salasOffset < data.total;
            loadMoreBtn.style.display = salasHasMore ? 'block' : 'none';
        })
        .catch(error => {
            salasLoading = false;
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar salas.</div>';
            console.error('Error:', error);
        });
}

function loadMoreSalas() {
    if (salasHasMore && !salasLoading) {
        loadSalas(false);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load initial data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadSalas(true);
});
</script>


</div>
