<?php require 'index.php';
require_once(__DIR__ . '/../func/csrf.php');
?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão de Materiais</h3>

<div class="mb-4">
    <h5>Importar Materiais via CSV</h5>
    <a href="/assets/csvsample_materiais.csv" download>Download do modelo CSV</a>
    <p class="text-muted small"><strong>Nota:</strong> Para obter o RoomID de uma sala, consulte a gestão de salas ou use a listagem abaixo.</p>
    <p class="small" style="color:red;font-weight:bold;">Deve de consultar o manual do administrador para mais informações.</p>
    <form action="materiais.php?action=import" method="POST" enctype="multipart/form-data" class="d-flex align-items-center justify-content-center">
        <?php echo csrf_token_field(); ?>
        <div class="me-2">
            <input type="file" class="form-control" id="csvfile" name="csvfile" accept=".csv" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm" style="height: 38px;">Importar CSV</button>
    </form>
</div>

<div class="d-flex align-items-center justify-content-center mb-3">
    <span class="me-3">Adicionar um material via flow</span>
    <?php formulario("materiais.php?action=criar", [
        ["type" => "text", "id" => "nomematerial", "placeholder" => "Nome do Material", "label" => "Nome do Material", "value" => null]
    ]); ?>
</div>

<?php
switch (isset($_GET['action']) ? $_GET['action'] : null){
    // Import CSV
    case "import":
        $maxFileSize = 2 * 1024 * 1024; // 2 MB
        if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
            echo "<div class='alert alert-danger fade show' role='alert'><strong>Erro:</strong> Token CSRF inválido.</div>";
            break;
        } elseif (!isset($_FILES['csvfile']) || $_FILES['csvfile']['error'] !== UPLOAD_ERR_OK) {
            echo "<div class='alert alert-danger fade show' role='alert'>Erro ao fazer upload do ficheiro.</div>";
            break;
        } elseif ($_FILES['csvfile']['size'] > $maxFileSize) {
            echo "<div class='alert alert-danger fade show' role='alert'><strong>Erro:</strong> Ficheiro demasiado grande (máximo 2 MB).</div>";
            break;
        }
        
        // Set database charset to utf8mb4
        $db->set_charset("utf8mb4");
        
        // Read the entire file content first to detect encoding
        $fileContent = file_get_contents($_FILES['csvfile']['tmp_name']);
        
        // Detect encoding and convert to UTF-8 if needed
        $encoding = mb_detect_encoding($fileContent, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $fileContent = mb_convert_encoding($fileContent, 'UTF-8', $encoding);
        }
        
        // Create a temporary file with UTF-8 content
        $tempFile = tmpfile();
        fwrite($tempFile, $fileContent);
        rewind($tempFile);
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        $lineNumber = 0;
        
        while (($data = fgetcsv($tempFile, 0, ',')) !== FALSE) {
            $lineNumber++;
            
            // Skip empty lines
            if (empty($data[0])) continue;
            
            // Skip header row (detect by checking if first column contains "MaterialName" or similar)
            if ($lineNumber == 1 && (strtolower($data[0]) == 'materialname' || strtolower($data[0]) == 'nome')) {
                continue;
            }
            
            // Expecting: MaterialName,MaterialDescription,RoomID
            if (count($data) < 3) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber} inválida: " . htmlspecialchars(implode(',', $data), ENT_QUOTES, 'UTF-8');
                continue;
            }
            
            $nome = trim($data[0]);
            $descricao = trim($data[1]);
            $sala_id = trim($data[2]);
            
            // Validate room exists
            $checkStmt = $db->prepare("SELECT id FROM salas WHERE id = ?");
            $checkStmt->bind_param("s", $sala_id);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows == 0) {
                $errorCount++;
                $errors[] = "Sala não encontrada para material '{$nome}': " . htmlspecialchars($sala_id, ENT_QUOTES, 'UTF-8');
                $checkStmt->close();
                continue;
            }
            $checkStmt->close();
            
            // Insert material
            $randomuuid = uuid4();
            $stmt = $db->prepare("INSERT INTO materiais (id, nome, descricao, sala_id) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $randomuuid, $nome, $descricao, $sala_id);
            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Erro ao inserir material '{$nome}': " . htmlspecialchars($stmt->error, ENT_QUOTES, 'UTF-8');
            }
            $stmt->close();
        }
        
        fclose($tempFile);
        
        if ($successCount > 0) {
            echo "<div class='alert alert-success fade show' role='alert'>{$successCount} material(ais) importado(s) com sucesso.</div>";
            acaoexecutada("Importação de Materiais via CSV");
        }
        if ($errorCount > 0) {
            echo "<div class='alert alert-warning fade show' role='alert'>{$errorCount} erro(s) durante a importação:<br>" . implode('<br>', array_slice($errors, 0, 10)) . "</div>";
        }
        break;
        
    // Create material
    case "criar":
        if (!isset($_POST['nomematerial'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        
        // Show form to complete material creation
        echo "<div class='alert alert-info fade show' role='alert'>Completar informações do material</div>";
        ?>
        <form action="materiais.php?action=criar_completo" method="POST" class="mb-3">
            <?= csrf_token_field(); ?>
            <div class="form-floating mb-2">
                <input type="text" class="form-control" id="nomematerial" name="nomematerial" placeholder="Nome do Material" value="<?php echo htmlspecialchars($_POST['nomematerial'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <label for="nomematerial">Nome do Material</label>
            </div>
            <div class="form-floating mb-2">
                <textarea class="form-control" id="descricao" name="descricao" placeholder="Descrição" rows="3" style="height: 100px;"></textarea>
                <label for="descricao">Descrição (opcional)</label>
            </div>
            <div class="form-floating mb-2">
                <select class="form-select" id="sala_id" name="sala_id" required>
                    <option value="" selected disabled>Escolha uma sala</option>
                    <?php
                    $salas = $db->query("SELECT * FROM salas ORDER BY nome ASC;");
                    while ($sala = $salas->fetch_assoc()) {
                        echo "<option value='{$sala['id']}'>{$sala['nome']}</option>";
                    }
                    ?>
                </select>
                <label for="sala_id">Sala</label>
            </div>
            <button type="submit" class="btn btn-primary">Criar Material</button>
        </form>
        <?php
        break;
        
    // Complete material creation
    case "criar_completo":
        if (!isset($_POST['nomematerial']) || !isset($_POST['sala_id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        
        $randomuuid = uuid4();
        $descricao = $_POST['descricao'] ?? '';
        $stmt = $db->prepare("INSERT INTO materiais (id, nome, descricao, sala_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $randomuuid, $_POST["nomematerial"], $descricao, $_POST["sala_id"]);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Criação de Material");
        break;
        
    // Delete material
    case "apagar":
        if (!isset($_POST['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        
        $stmt = $db->prepare("DELETE FROM materiais WHERE id = ?");
        $stmt->bind_param("s", $_POST['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Eliminação de Material");
        break;
        
    // Edit material
    case "edit":
        if (!isset($_GET['id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>ID inválido.</div>";
            break;
        }
        
        $stmt = $db->prepare("SELECT * FROM materiais WHERE id = ?");
        $stmt->bind_param("s", $_GET['id']);
        $stmt->execute();
        $d = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$d) {
            echo "<div class='alert alert-danger fade show' role='alert'>Material não encontrado.</div>";
            break;
        }
        
        echo "<div class='alert alert-warning fade show' role='alert'>A editar o Material <b>" . htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') . "</b>.</div>";
        ?>
        <form action="materiais.php?action=update&id=<?php echo urlencode($d['id']); ?>" method="POST" class="mb-3">
            <?= csrf_token_field(); ?>
            <div class="form-floating mb-2">
                <input type="text" class="form-control" id="nomematerial" name="nomematerial" placeholder="Nome do Material" value="<?php echo htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <label for="nomematerial">Nome do Material</label>
            </div>
            <div class="form-floating mb-2">
                <textarea class="form-control" id="descricao" name="descricao" placeholder="Descrição" rows="3" style="height: 100px;"><?php echo htmlspecialchars($d['descricao'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                <label for="descricao">Descrição (opcional)</label>
            </div>
            <div class="form-floating mb-2">
                <select class="form-select" id="sala_id" name="sala_id" required>
                    <?php
                    $salas = $db->query("SELECT * FROM salas ORDER BY nome ASC;");
                    while ($sala = $salas->fetch_assoc()) {
                        $selected = ($sala['id'] == $d['sala_id']) ? 'selected' : '';
                        echo "<option value='{$sala['id']}' {$selected}>{$sala['nome']}</option>";
                    }
                    ?>
                </select>
                <label for="sala_id">Sala</label>
            </div>
            <button type="submit" class="btn btn-primary">Submeter</button>
        </form>
        <?php
        break;
        
    // Update material
    case "update":
        if (!isset($_GET['id']) || !isset($_POST['nomematerial']) || !isset($_POST['sala_id'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        
        $descricao = $_POST['descricao'] ?? '';
        $stmt = $db->prepare("UPDATE materiais SET nome = ?, descricao = ?, sala_id = ? WHERE id = ?");
        $stmt->bind_param("ssss", $_POST['nomematerial'], $descricao, $_POST['sala_id'], $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Atualização de Material");
        break;
}

// List all materials grouped by room
$materiaisQuery = $db->query("
    SELECT m.*, s.nome as sala_nome 
    FROM materiais m 
    LEFT JOIN salas s ON m.sala_id = s.id 
    ORDER BY s.nome ASC, m.nome ASC
");

echo "<div class='mt-4 mb-3'>";
echo "<h5>Materiais Existentes</h5>";

if ($materiaisQuery->num_rows == 0) {
    echo "<div class='alert alert-info alert-dismissible fade show' role='alert'>Não existem materiais.</div>\n";
} else {
    echo "<button type='button' class='btn btn-primary' data-bs-toggle='modal' data-bs-target='#materiaisModal'>";
    echo "Ver Todos os Materiais ({$materiaisQuery->num_rows})";
    echo "</button>";
    
    // Modal for materials list
    echo "<div class='modal fade' id='materiaisModal' tabindex='-1' aria-labelledby='materiaisModalLabel' aria-hidden='true'>";
    echo "<div class='modal-dialog modal-xl modal-dialog-scrollable'>";
    echo "<div class='modal-content'>";
    echo "<div class='modal-header'>";
    echo "<h5 class='modal-title' id='materiaisModalLabel'>Materiais Existentes</h5>";
    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
    echo "</div>";
    echo "<div class='modal-body'>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped table-hover'>";
    echo "<thead>";
    echo "<tr><th scope='col'>Nome</th><th scope='col'>Descrição</th><th scope='col'>Sala</th><th scope='col'>Ações</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    // Reset pointer to iterate again
    $materiaisQuery->data_seek(0);
    
    while ($row = $materiaisQuery->fetch_assoc()) {
        $idEnc = urlencode($row['id']);
        $nome = htmlspecialchars($row['nome'], ENT_QUOTES, 'UTF-8');
        $descricao = htmlspecialchars($row['descricao'], ENT_QUOTES, 'UTF-8');
        $salaNome = htmlspecialchars($row['sala_nome'], ENT_QUOTES, 'UTF-8');
        
        echo "<tr>";
        echo "<td><strong>{$nome}</strong></td>";
        echo "<td>" . (empty($descricao) ? '<em class=\"text-muted\">Sem descrição</em>' : $descricao) . "</td>";
        echo "<td><span class='badge bg-info'>{$salaNome}</span></td>";
        echo "<td>";
        echo "<a href='/admin/materiais.php?action=edit&id={$idEnc}' class='btn btn-sm btn-outline-primary me-1'>Editar</a>";
        echo "<form action='/admin/materiais.php?action=apagar' method='POST' style='display:inline;' onsubmit='return confirm(\"Tem a certeza que pretende apagar este material?\");'>";
        echo csrf_token_field();
        echo "<input type='hidden' name='id' value='{$idEnc}'>";
        echo "<button type='submit' class='btn btn-sm btn-outline-danger'>Apagar</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
    echo "<div class='modal-footer'>";
    echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Fechar</button>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

echo "</div>";

// Get reference section for Room IDs before closing connection
$salasRef = $db->query("SELECT id, nome FROM salas ORDER BY nome ASC;");

if ($salasRef && $salasRef->num_rows > 0) {
    echo "<div class='mt-4 mb-3'>";
    echo "<button type='button' class='btn btn-info' data-bs-toggle='modal' data-bs-target='#salasRefModal'>";
    echo "<i class='bi bi-list-ul me-2'></i>Ver Referência de IDs de Salas ({$salasRef->num_rows})";
    echo "</button>";
    echo "</div>";
    
    // Modal for room IDs reference
    echo "<div class='modal fade' id='salasRefModal' tabindex='-1' aria-labelledby='salasRefModalLabel' aria-hidden='true'>";
    echo "<div class='modal-dialog modal-lg modal-dialog-scrollable'>";
    echo "<div class='modal-content'>";
    echo "<div class='modal-header'>";
    echo "<h5 class='modal-title' id='salasRefModalLabel'>Referência de IDs de Salas</h5>";
    echo "<button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>";
    echo "</div>";
    echo "<div class='modal-body'>";
    echo "<p class='mb-3'>Use estes IDs na coluna <code>RoomID</code> ao criar o ficheiro CSV para importação de materiais.</p>";
    echo "<div class='table-responsive'>";
    echo "<table class='table table-striped table-hover'>";
    echo "<thead>";
    echo "<tr><th scope='col'>Nome da Sala</th><th scope='col'>ID (Room ID)</th></tr>";
    echo "</thead>";
    echo "<tbody>";
    
    while ($sala = $salasRef->fetch_assoc()) {
        $salaNome = htmlspecialchars($sala['nome'], ENT_QUOTES, 'UTF-8');
        $salaId = htmlspecialchars($sala['id'], ENT_QUOTES, 'UTF-8');
        echo "<tr>";
        echo "<td><strong>{$salaNome}</strong></td>";
        echo "<td><code class='user-select-all'>{$salaId}</code></td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</div>";
    echo "<div class='modal-footer'>";
    echo "<button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Fechar</button>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
}

$db->close();
?>
</div>
