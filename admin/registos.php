<?php require 'index.php'; ?>
<div style="margin-left: 5%; margin-right: 5%; text-align: center;">
<h3>Registos Administrativos</h3>

<style>
    #logsTable {
        width: 100%;
        table-layout: fixed;
    }
    #logsTable th:nth-child(1),
    #logsTable td:nth-child(1) {
        width: 20%;
    }
    #logsTable th:nth-child(2),
    #logsTable td:nth-child(2) {
        width: 40%;
    }
    #logsTable th:nth-child(3),
    #logsTable td:nth-child(3) {
        width: 15%;
    }
    #logsTable th:nth-child(4),
    #logsTable td:nth-child(4) {
        width: 25%;
    }
    .action-text {
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .action-collapsed {
        display: inline;
    }
    .action-expanded {
        display: none;
    }
    .action-toggle {
        cursor: pointer;
        color: #0d6efd;
        text-decoration: underline;
        margin-left: 5px;
    }
    .action-toggle:hover {
        color: #0a58ca;
    }
    #loadingPlaceholder {
        text-align: center;
        padding: 20px;
    }
    #endOfLogs {
        text-align: center;
        padding: 20px;
        color: #6c757d;
        display: none;
    }
    .ip-hidden {
        color: #6c757d;
        font-style: italic;
    }
    .ip-visible {
        font-family: monospace;
    }
</style>

<script>
    let currentOffset = 0;
    const limit = 50;
    const scrollThreshold = 200;
    let isLoading = false;
    let hasMoreLogs = true;
    let showIPs = false;

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function toggleAction(logId) {
        const collapsedSpan = document.getElementById('collapsed-' + logId);
        const expandedSpan = document.getElementById('expanded-' + logId);
        const toggleLink = document.getElementById('toggle-' + logId);
        
        if (expandedSpan.style.display === 'none' || expandedSpan.style.display === '') {
            collapsedSpan.style.display = 'none';
            expandedSpan.style.display = 'inline';
            toggleLink.textContent = 'Ver menos';
        } else {
            collapsedSpan.style.display = 'inline';
            expandedSpan.style.display = 'none';
            toggleLink.textContent = 'Ver mais';
        }
    }

    function toggleAllIPs() {
        showIPs = !showIPs;
        const button = document.getElementById('toggleIPButton');
        const ipCells = document.querySelectorAll('.ip-cell');
        
        ipCells.forEach(cell => {
            const ipValue = cell.getAttribute('data-ip');
            if (showIPs) {
                cell.innerHTML = '<span class="ip-visible">' + escapeHtml(ipValue || 'N/A') + '</span>';
                button.textContent = 'Ocultar IPs';
                button.classList.remove('btn-primary');
                button.classList.add('btn-secondary');
            } else {
                cell.innerHTML = '<span class="ip-hidden">Oculto</span>';
                button.textContent = 'Mostrar IPs';
                button.classList.remove('btn-secondary');
                button.classList.add('btn-primary');
            }
        });
    }

    function createActionCell(logId, action) {
        if (action.length <= 10) {
            return '<span class="action-text">' + escapeHtml(action) + '</span>';
        }
        
        const truncated = action.substring(0, 10) + '...';
        return '<span id="collapsed-' + logId + '" class="action-collapsed">' + escapeHtml(truncated) + '</span>' +
               '<span id="expanded-' + logId + '" class="action-expanded action-text">' + escapeHtml(action) + '</span>' +
               '<span id="toggle-' + logId + '" class="action-toggle" onclick="toggleAction(\'' + logId + '\')">Ver mais</span>';
    }

    function loadLogs() {
        if (isLoading || !hasMoreLogs) return;
        
        isLoading = true;
        document.getElementById('loadingPlaceholder').style.display = 'block';
        
        fetch('api/api_registos.php?offset=' + currentOffset + '&limit=' + limit)
            .then(response => response.json())
            .then(data => {
                const tbody = document.getElementById('logsTableBody');
                
                if (data.logs.length === 0) {
                    hasMoreLogs = false;
                    document.getElementById('loadingPlaceholder').style.display = 'none';
                    document.getElementById('endOfLogs').style.display = 'block';
                    return;
                }
                
                data.logs.forEach(log => {
                    const tr = document.createElement('tr');
                    
                    // Utilizador column (name + email)
                    const userCell = document.createElement('td');
                    userCell.innerHTML = '<strong>' + escapeHtml(log.nome || 'Utilizador removido') + '</strong><br><small class="text-muted">' + escapeHtml(log.email || 'N/A') + '</small>';
                    tr.appendChild(userCell);
                    
                    // Action column
                    const actionCell = document.createElement('td');
                    actionCell.innerHTML = createActionCell(log.id, log.loginfo);
                    tr.appendChild(actionCell);
                    
                    // IP Address column (hidden by default)
                    const ipCell = document.createElement('td');
                    ipCell.className = 'ip-cell';
                    ipCell.setAttribute('data-ip', log.ip_address || 'N/A');
                    if (showIPs) {
                        ipCell.innerHTML = '<span class="ip-visible">' + escapeHtml(log.ip_address || 'N/A') + '</span>';
                    } else {
                        ipCell.innerHTML = '<span class="ip-hidden">Oculto</span>';
                    }
                    tr.appendChild(ipCell);
                    
                    // Timestamp column
                    const timestampCell = document.createElement('td');
                    timestampCell.textContent = log.timestamp;
                    tr.appendChild(timestampCell);
                    
                    tbody.appendChild(tr);
                });
                
                currentOffset += data.logs.length;
                
                if (data.logs.length < limit) {
                    hasMoreLogs = false;
                    document.getElementById('endOfLogs').style.display = 'block';
                }
                
                document.getElementById('loadingPlaceholder').style.display = 'none';
                isLoading = false;
            })
            .catch(error => {
                console.error('Error loading logs:', error);
                document.getElementById('loadingPlaceholder').innerHTML = '<div class="alert alert-danger">Erro ao carregar registos.</div>';
                isLoading = false;
            });
    }

    // Infinite scroll handler
    function handleScroll() {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - scrollThreshold) {
            loadLogs();
        }
    }

    // Initial load and scroll listener
    document.addEventListener('DOMContentLoaded', function() {
        loadLogs();
        window.addEventListener('scroll', handleScroll);
    });
</script>

<?php
$sql = "SELECT COUNT(*) as total FROM logs";
$result = $db->query($sql);
$numLogs = $result ? $result->fetch_assoc()['total'] : 0;
?>

<p class="text-muted mb-3">Total de registos: <?php echo htmlspecialchars($numLogs, ENT_QUOTES, 'UTF-8'); ?></p>

<?php if ($numLogs == 0): ?>
    <div class='alert alert-warning'>Não existem registos.</div>
<?php else: ?>
    <div class="mb-3">
        <button id="toggleIPButton" class="btn btn-primary" onclick="toggleAllIPs()">Mostrar IPs</button>
    </div>
    <div class="table-responsive">
        <table class='table table-striped table-hover' id="logsTable">
            <thead class='table-dark'>
                <tr>
                    <th scope='col'>Utilizador</th>
                    <th scope='col'>Ação</th>
                    <th scope='col'>Endereço IP</th>
                    <th scope='col'>Timestamp</th>
                </tr>
            </thead>
            <tbody id="logsTableBody">
            </tbody>
        </table>
    </div>
    
    <div id="loadingPlaceholder">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">A carregar...</span>
        </div>
        <p class="mt-2 text-muted">A carregar registos...</p>
    </div>
    
    <div id="endOfLogs">
        <p class="text-muted">Todos os registos foram carregados.</p>
    </div>
<?php endif; ?>

</div>
<?php
$db->close();
?>
