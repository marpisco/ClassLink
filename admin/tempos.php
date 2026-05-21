<?php require 'index.php'; require_once(__DIR__ . '/../func/csrf.php'); ?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão de Tempos</h3>
<div class="mb-4">
    <h5>Importar Tempos via CSV</h5>
    <a href="/assets/csvsample_tempos.csv" download>Download do modelo CSV</a>
    <p class="small" style="color:red;font-weight:bold;">Deve consultar o manual do administrador para mais informações.</p>
    <form action="tempos.php?action=import" method="POST" enctype="multipart/form-data" class="d-flex align-items-center justify-content-center">
        <?php echo csrf_token_field(); ?>
        <div class="me-2">
            <input type="file" class="form-control" id="csvfile" name="csvfile" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">Importar CSV</button>
    </form>
</div>
<div class="d-flex align-items-center justify-content-center mb-3">
    <span class="me-3">Adicionar um tempo</span>
    <?php formulario("tempos.php?action=criar", [
        ["type" => "text", "id" => "horahumana", "placeholder" => "Horas (08:05-08:55)", "label" => "Horas (08:05-08:55)", "value" => null]
    ]); ?>
</div>

<?php
switch (isset($_GET['action']) ? $_GET['action'] : null){
    // Import CSV
    case "import":
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Token CSRF inválido.</div>";
            break;
        }

        if (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro ao fazer upload do ficheiro.</div>";
            break;
        }

        $db->set_charset("utf8mb4");
        $fileContent = file_get_contents($_FILES['csvfile']['tmp_name']);
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }

        $tempFile = tmpfile();
        fwrite($tempFile, $fileContent);
        rewind($tempFile);

        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $lineNumber = 0;

        while (($data = fgetcsv($tempFile, 0, ';')) !== FALSE) {
            $lineNumber++;

            if (!isset($data[0]) || trim($data[0]) === '') {
                continue;
            }

            if ($lineNumber == 1 && in_array(strtolower(trim($data[0])), ['horario', 'horashumanos', 'tempo', 'time'])) {
                continue;
            }

            $horashumanos = trim($data[0]);

            $randomuuid = uuid4();
            $stmt = $db->prepare("INSERT INTO tempos (id, horashumanos) VALUES (?, ?)");
            $stmt->bind_param("ss", $randomuuid, $horashumanos);
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Erro ao inserir tempo '" . htmlspecialchars($horashumanos, ENT_QUOTES, 'UTF-8') . "': " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
            }
            $stmt->close();
        }

        fclose($tempFile);

        if ($successCount > 0) {
            echo "<div class='alert alert-success fade show' role='alert'>{$successCount} tempo(s) importado(s) com sucesso.</div>";
            acaoexecutada("Importação de Tempos via CSV");
        }
        if ($errorCount > 0) {
            echo "<div class='alert alert-warning fade show' role='alert'>{$errorCount} erro(s) durante a importação:<br>" . implode('<br>', array_slice($errors, 0, 10)) . "</div>";
        }
        break;

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
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        try {
            $stmt = $db->prepare("SELECT * FROM reservas WHERE tempo = ? AND aprovado != -1");
            $stmt->bind_param("s", $_GET['id']);
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
        $stmt->bind_param("s", $_GET['id']);
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
                        <a href='/admin/tempos.php?action=apagar&id=${idEnc}' class='btn btn-sm btn-danger' onclick='return confirm("Tem a certeza que pretende apagar o tempo? Isto irá causar problemas se a sala tiver reservas passadas.");'>APAGAR</a>
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
