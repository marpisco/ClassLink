<?php require 'index.php'; ?>
<script src="https://cdn.ckeditor.com/ckeditor5/40.1.0/classic/ckeditor.js"></script>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
<h3>Gestão de Páginas Pós-Reserva</h3>
<div class="d-flex align-items-center justify-content-center mb-3">
    <span class="me-3">Selecione uma sala para editar a página pós-reserva</span>
</div>

<?php
switch (isset($_GET['action']) ? $_GET['action'] : null){
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
        echo "<div class='alert alert-warning fade show' role='alert'>A editar a Página Pós-Reserva da Sala <b>" . htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') . "</b>.</div>";
        ?>
        <form action="salas_postreserva.php?action=update&id=<?php echo urlencode($d['id']); ?>" method="POST" style="max-width: 100%; margin: 0 auto;">
            <div class="mb-3">
                <label for="nomesala" class="form-label">Sala</label>
                <input type="text" class="form-control" id="nomesala" value="<?php echo htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </div>
            <div class="mb-3">
                <label for="post_reservation_content" class="form-label">Conteúdo da Página Pós-Reserva</label>
                <textarea id="post_reservation_content" name="post_reservation_content" class="form-control" rows="10"><?php echo htmlspecialchars($d['post_reservation_content'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
            <a href="salas_postreserva.php" class="btn btn-secondary">Voltar</a>
        </form>
        <script>
            // Ensure bootstrap's .form-control doesn't force a white background
            (function(){
                const ta = document.querySelector('#post_reservation_content');
                if (ta && ta.classList) ta.classList.remove('form-control');

                ClassicEditor
                    .create(ta, {
                        toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|', 'outdent', 'indent', '|', 'blockQuote', 'insertTable', 'undo', 'redo']
                    })
                    .then(editor => {
                        try {
                            const editable = editor.ui.view.editable.element;
                            // Make the editor background transparent so the admin dark background shows through
                            editable.style.background = 'transparent';
                            // Keep text color as-inherited from the admin dashboard (so dark-theme white text remains)
                            editable.style.color = 'inherit';
                            // Add some minimum height for a comfortable editing area
                            editable.style.minHeight = '200px';
                            // Remove white background from the editor's wrapper if present
                            const wrapper = editable.closest('.ck');
                            if (wrapper) wrapper.style.background = 'transparent';
                        } catch (e) {
                            console.warn('Could not adjust editor styles', e);
                        }
                    })
                    .catch(error => {
                        console.error(error);
                    });
            })();
        </script>
        <?php
        break;
    // caso seja submetida a edição:
    case "update":
        if (!isset($_GET['id']) || !isset($_POST['post_reservation_content'])) {
            echo "<div class='alert alert-danger fade show' role='alert'>Dados inválidos.</div>";
            break;
        }
        $stmt = $db->prepare("UPDATE salas SET post_reservation_content = ? WHERE id = ?");
        $stmt->bind_param("ss", $_POST['post_reservation_content'], $_GET['id']);
        $stmt->execute();
        $stmt->close();
        acaoexecutada("Atualização de Página Pós-Reserva");
        echo "<a href='salas_postreserva.php' class='btn btn-primary mt-2'>Voltar</a>";
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
    
    const url = '/admin/api/salas_search.php?include_postreserva=1&limit=' + salasLimit + '&offset=' + salasOffset + '&q=' + encodeURIComponent(salasSearchQuery);
    
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
                            <th scope='col'>Estado do Conteúdo</th>
                            <th scope='col'>AÇÕES</th>
                        </tr>
                    </thead>
                    <tbody id='salasTableBody'>`;
            }
            
            data.salas.forEach(sala => {
                const idEnc = encodeURIComponent(sala.id);
                const hasContent = sala.has_post_reservation_content 
                    ? "<span class='badge bg-success'>Configurado</span>" 
                    : "<span class='badge bg-secondary'>Vazio</span>";
                
                const rowHtml = `<tr>
                    <td>${escapeHtml(sala.nome)}</td>
                    <td>${hasContent}</td>
                    <td>
                        <a href='/admin/salas_postreserva.php?action=edit&id=${idEnc}' class='btn btn-sm btn-primary'>EDITAR</a>
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
