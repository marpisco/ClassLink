<?php require 'index.php'; ?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão de Tempos</h3>
<div class="d-flex align-items-center justify-content-center mb-3">
    <span class="me-3">Adicionar um tempo</span>
    <?php formulario("tempos.php?action=criar", [
        ["type" => "text", "id" => "horahumana", "placeholder" => "Horas (08:05-08:55)", "label" => "Horas (08:05-08:55)", "value" => null]
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
        if (!isset($_POST['horahumana'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        $randomuuid = uuid4();
        $stmt = $db->prepare("INSERT INTO tempos (id, horashumanos) VALUES (?, ?)");
        $stmt->bind_param("ss", $randomuuid, $_POST["horahumana"]);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Criação de Tempo");
        break;
    // caso execute a ação apagar:
    case "apagar":
        if (!isset($_POST['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        try {
            $stmt = $db->prepare("SELECT * FROM reservas WHERE tempo = ? AND aprovado != -1");
            $stmt->bind_param("s", $_POST['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                throw new Exception("Existem reservas associadas a este tempo. Por segurança, é necessária uma intervenção manual de um Administrador.");
            }
            $stmt->close();
        } catch (Exception $e) {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
            break;
        }
        $stmt = $db->prepare("DELETE FROM tempos WHERE id = ?");
        $stmt->bind_param("s", $_POST['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Eliminação de Tempo");
        break;
    // caso execute a ação editar:
    case "edit":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        $stmt = $db->prepare("SELECT * FROM tempos WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$d) {
            echo "<div class='alert alert-danger fade show' role='alert'>Tempo não encontrado.</div>";
            break;
        }
        echo "<div class='alert alert-warning fade show' role='alert'>A editar o Tempo <b>" . htmlspecialchars($d['horashumanos'], ENT_QUOTES, 'UTF-8') . "</b>.</div>";
        formulario("tempos.php?action=update&id=" . urlencode($d['id']), [
            ["type" => "text", "id" => "horahumana", "placeholder" => "Horas (08:05-08:55)", "label" => "Horas (08:05-08:55)", "value" => $d['horashumanos']]]);
        break;
    // caso seja submetida a edição:
    case "update":
        if (!isset($_GET['id']) || !isset($_POST['horahumana'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        $stmt = $db->prepare("UPDATE tempos SET horashumanos = ? WHERE id = ?");
        $stmt->bind_param("ss", $_POST['horahumana'], $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Atualização de Tempo");
        break;
}

$numTempos = $db->query("SELECT COUNT(*) as total FROM tempos")->fetch_assoc()['total'];
$db->close();
?>

<div class="mt-4">
    <h5>Lista de Tempos (<?php echo $numTempos; ?>)</h5>
    <div class="mb-3">
        <input type="text" class="form-control" id="temposSearchInput" placeholder="Pesquisar tempos..." oninput="searchTempos()">
    </div>
    <div id="temposListContainer">
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">A carregar...</span>
            </div>
        </div>
    </div>
    <div id="temposLoadMore" class="text-center mt-3" style="display: none;">
        <button class="btn btn-outline-primary" onclick="loadMoreTempos()">Carregar mais</button>
    </div>
</div>

<script>
let temposOffset = 0;
let temposSearchQuery = '';
let temposLoading = false;
let temposHasMore = true;
const temposLimit = 20;

function searchTempos() {
    temposSearchQuery = document.getElementById('temposSearchInput').value;
    temposOffset = 0;
    temposHasMore = true;
    loadTempos(true);
}

function loadTempos(reset = false) {
    if (temposLoading) return;
    temposLoading = true;
    
    const container = document.getElementById('temposListContainer');
    const loadMoreBtn = document.getElementById('temposLoadMore');
    
    if (reset) {
        container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">A carregar...</span></div></div>';
    }
    
    const url = '/admin/api/tempos_search.php?limit=' + temposLimit + '&offset=' + temposOffset + '&q=' + encodeURIComponent(temposSearchQuery);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            temposLoading = false;
            
            if (reset) {
                container.innerHTML = '';
            }
            
            if (data.tempos.length === 0 && temposOffset === 0) {
                container.innerHTML = '<div class="alert alert-warning">Não existem tempos.</div>';
                loadMoreBtn.style.display = 'none';
                return;
            }
            
            let tableHtml = '';
            if (temposOffset === 0) {
                tableHtml = `<table class='table table-striped table-hover'>
                    <thead class='table-dark'>
                        <tr>
                            <th scope='col'>Hora Humana</th>
                            <th scope='col'>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id='temposTableBody'>`;
            }
            
            data.tempos.forEach(tempo => {
                const idEnc = encodeURIComponent(tempo.id);
                
                const rowHtml = `<tr>
                    <td>${escapeHtml(tempo.horashumanos)}</td>
                    <td>
                        <a href='/admin/tempos.php?action=edit&id=${idEnc}' class='btn btn-sm btn-primary'>EDITAR</a>
                        <form action='/admin/tempos.php' method='POST' style='display:inline;' onsubmit='return confirm("Tem a certeza que pretende apagar o tempo? Isto irá causar problemas se a sala tiver reservas passadas.");'><input type='hidden' name='action' value='apagar'><input type='hidden' name='id' value='${idEnc}'><button type='submit' class='btn btn-sm btn-danger'>APAGAR</button></form>
                    </td>
                </tr>`;
                
                if (temposOffset === 0) {
                    tableHtml += rowHtml;
                } else {
                    document.getElementById('temposTableBody').insertAdjacentHTML('beforeend', rowHtml);
                }
            });
            
            if (temposOffset === 0) {
                tableHtml += '</tbody></table>';
                container.innerHTML = tableHtml;
            }
            
            temposOffset += data.tempos.length;
            temposHasMore = temposOffset < data.total;
            loadMoreBtn.style.display = temposHasMore ? 'block' : 'none';
        })
        .catch(error => {
            temposLoading = false;
            container.innerHTML = '<div class="alert alert-danger">Erro ao carregar tempos.</div>';
            console.error('Error:', error);
        });
}

function loadMoreTempos() {
    if (temposHasMore && !temposLoading) {
        loadTempos(false);
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load initial data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadTempos(true);
});
</script>


</div>
