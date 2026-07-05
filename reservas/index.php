<?php
require_once(__DIR__ . '/../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['pending_totp_user'])) { header('Location: /login?step=totp'); exit(); }
if (isset($_SESSION['pending_user_setup'])) { header('Location: /login?step=setup'); exit(); }
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    header("Location: /login");
    die("A reencaminhar para iniciar sessão...");
}

// Get statistics for the dashboard with a single optimized query
$requisitor = $_SESSION['id'];
$stmt = $db->prepare("SELECT 
    SUM(CASE WHEN aprovado = 0 THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN aprovado = 1 THEN 1 ELSE 0 END) as aprovadas,
    SUM(CASE WHEN data >= CURDATE() THEN 1 ELSE 0 END) as futuras
    FROM reservas WHERE requisitor = ?");
$stmt->bind_param("s", $requisitor);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$totalPendentes = $stats['pendentes'] ?? 0;
$totalAprovadas = $stats['aprovadas'] ?? 0;
$totalFuturas = $stats['futuras'] ?? 0;
$stmt->close();
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>As suas reservas | ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/reservar.css">
    <link href="/assets/index.css" rel="stylesheet">
    <link rel='icon' href='/assets/logo.png'>
    <script src="https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js" crossorigin="anonymous"></script>
    <script src="/assets/theme-switcher.js"></script>
    <style>
        img.emoji {
            height: 1em;
            width: 1em;
            margin: 0 .05em 0 .1em;
            vertical-align: -0.1em;
        }
        .stat-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
            border: none;
            border-radius: 12px;
        }
        .stat-card-pending {
            background: linear-gradient(135deg, var(--stat-pending-start) 0%, var(--stat-pending-end) 100%);
        }
        .stat-card-approved {
            background: linear-gradient(135deg, var(--stat-approved-start) 0%, var(--stat-approved-end) 100%);
        }
        .stat-card-future {
            background: linear-gradient(135deg, var(--stat-future-start) 0%, var(--stat-future-end) 100%);
        }
        .stat-card-content {
            color: var(--stat-card-text);
        }
        :root,
        html[data-bs-theme="light"] {
            --stat-pending-start: #fff9e6;
            --stat-pending-end: #fff3cd;
            --stat-approved-start: #e8f5e9;
            --stat-approved-end: #c8e6c9;
            --stat-future-start: #e3f2fd;
            --stat-future-end: #bbdefb;
            --stat-card-text: #212529;
        }
        html[data-bs-theme="dark"] {
            --stat-pending-start: #4d3800;
            --stat-pending-end: #7a5700;
            --stat-approved-start: #0f3d21;
            --stat-approved-end: #1f5c35;
            --stat-future-start: #083352;
            --stat-future-end: #0d4f7a;
            --stat-card-text: #f8f9fa;
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
        .reserva-card {
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            margin-bottom: 1rem;
        }
        .reserva-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .reserva-card.pending {
            border-left-color: #ffc107;
        }
        .reserva-card.approved {
            border-left-color: #28a745;
        }
        .reserva-card.past {
            opacity: 0.7;
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
        .action-btn.btn-outline-primary:hover {
            color: #fff;
            transition: all 0.2s ease;
        }
        .action-btn.btn-outline-danger:hover {
            color: #fff;
            transition: all 0.2s ease;
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
        .filter-btn {
            border-color: #0d6efd;
            border-radius: 20px;
            color: #0a58ca;
            padding: 0.4rem 1rem;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            background: transparent;
        }
        .filter-btn:hover {
            background-color: #e7f1ff;
            border-color: #0a58ca;
            color: #084298;
        }
        .filter-btn.active,
        .filter-btn.active:hover {
            background-color: #0d6efd;
            color: #fff;
            border-color: #0d6efd;
            font-weight: 700;
        }
        [data-bs-theme="dark"] .filter-btn {
            border-color: #6ea8fe;
            color: #9ec5fe;
        }
        [data-bs-theme="dark"] .filter-btn:hover {
            background-color: #0d6efd;
            border-color: #9ec5fe;
            color: #fff;
        }
        [data-bs-theme="dark"] .filter-btn.active,
        [data-bs-theme="dark"] .filter-btn.active:hover {
            background-color: #0d6efd;
            border-color: #6ea8fe;
            color: #fff;
        }
        .reservations-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .horario-badge {
            background-color: #f8f9fa;
            color: #212529;
            border: 1px solid #dee2e6;
        }
        html[data-bs-theme="dark"] .horario-badge {
            background-color: var(--bs-tertiary-bg);
            color: var(--bs-body-color);
            border-color: var(--bs-border-color);
        }
        .reservations-table-head th {
            --bs-table-bg: var(--bs-card-bg);
            --bs-table-color: var(--bs-body-color);
            --bs-table-border-color: var(--bs-border-color);
            background-color: var(--bs-card-bg);
            color: var(--bs-body-color);
            border-color: var(--bs-border-color);
        }
        [data-bs-theme="dark"] .reservations-table-head th {
            --bs-table-bg: var(--bs-tertiary-bg);
            --bs-table-color: var(--bs-body-color);
            --bs-table-border-color: var(--bs-border-color);
            background-color: var(--bs-tertiary-bg);
            color: var(--bs-body-color);
            border-color: var(--bs-border-color);
        }
        @media (max-width: 768px) {
            .stat-number {
                font-size: 1.5rem;
            }
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <?php require_once(__DIR__ . '/../func/navbar.php'); ?>
    
    <div class="reservations-container fade-in">
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <p class="h2 fw-light mb-1">As Minhas Reservas</p>
                        <p class="text-muted mb-0">Gerir e acompanhar as suas reservas de salas</p>
                    </div>
                    <div>
                        <a href="/reservar" class="btn btn-primary">
                            + Nova Reserva
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4 g-3">
            <div class="col-md-4">
                <div class="card stat-card stat-card-pending shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-warning text-white me-3">
                            &#x23F3;
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-number"><?php echo $totalPendentes; ?></div>
                            <div class="small">Pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card stat-card-approved shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-success text-white me-3">
                            &#x2705;
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-number"><?php echo $totalAprovadas; ?></div>
                            <div class="small">Aprovadas</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card stat-card-future shadow-sm h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="stat-icon bg-info text-white me-3">
                            &#x1F4C5;
                        </div>
                        <div class="stat-card-content">
                            <div class="stat-number"><?php echo $totalFuturas; ?></div>
                            <div class="small">Futuras</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn filter-btn active" onclick="filterReservations('all', event)">Todas</button>
                            <button type="button" class="btn filter-btn" onclick="filterReservations('pending', event)">Pendentes</button>
                            <button type="button" class="btn filter-btn" onclick="filterReservations('approved', event)">Aprovadas</button>
                            <button type="button" class="btn filter-btn" onclick="filterReservations('future', event)">Futuras</button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <input type="text" class="form-control search-box" id="searchInput" placeholder="Pesquisar por sala, data ou motivo..." oninput="searchReservations()">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php
                // Use JOIN to fetch all data in a single query (eliminates N+1 query problem)
                $stmt = $db->prepare("SELECT r.*, s.nome as sala_nome, t.horashumanos as tempo_nome 
                                      FROM reservas r 
                                      LEFT JOIN salas s ON r.sala = s.id 
                                      LEFT JOIN tempos t ON r.tempo = t.id 
                                      WHERE r.requisitor = ? 
                                      ORDER BY r.data DESC");
                $stmt->bind_param("s", $requisitor);
                $stmt->execute();
                $reservas = $stmt->get_result();
                
                // Portuguese day names constant
                $daysPortuguese = ['', 'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'];
                
                if ($reservas->num_rows == 0) {
                    echo "<div class='empty-state'>
                            <div class='empty-state-icon'>&#x1F4ED;</div>
                            <h5>Nenhuma reserva encontrada</h5>
                            <p class='mb-3'>Ainda não tem reservas no seu nome.</p>
                            <a href='/reservar' class='btn btn-primary'>Fazer uma Reserva</a>
                          </div>";
                } else {
                    echo "<div class='table-responsive'>
                            <table class='table table-hover align-middle mb-0' id='reservationsTable'>
                                <thead class='reservations-table-head'>
                                    <tr>
                                        <th scope='col'>Sala</th>
                                        <th scope='col'>Data</th>
                                        <th scope='col'>Horário</th>
                                        <th scope='col'>Estado</th>
                                        <th scope='col' class='text-center'>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>";
                    
                    $today = date('Y-m-d');
                    
                    while ($reserva = $reservas->fetch_assoc()) {
                        $tempoEnc = urlencode($reserva['tempo']);
                        $salaEnc = urlencode($reserva['sala']);
                        $dataEnc = urlencode($reserva['data']);
                        
                        // Determine status and row classes
                        $isApproved = ($reserva['aprovado'] == 1);
                        $isPast = ($reserva['data'] < $today);
                        $isToday = ($reserva['data'] == $today);
                        $isFuture = ($reserva['data'] >= $today);
                        
                        $rowClass = $isPast ? 'reserva-past' : '';
                        $statusClass = $isApproved ? 'approved' : 'pending';
                        $dataStatus = $isApproved ? 'approved' : 'pending';
                        $dataFuture = $isFuture ? 'true' : 'false';
                        
                        // Format date nicely
                        $dataFormatted = date('d/m/Y', strtotime($reserva['data']));
                        $dayNum = date('N', strtotime($reserva['data']));
                        $dayName = $daysPortuguese[$dayNum];
                        
                        // Get names from joined data (with fallback)
                        $salaName = $reserva['sala_nome'] ?? 'N/A';
                        $tempoName = $reserva['tempo_nome'] ?? 'N/A';
                        
                        echo "<tr class='{$rowClass}' data-status='{$dataStatus}' data-future='{$dataFuture}' data-search='" . 
                             htmlspecialchars(strtolower($salaName . ' ' . $reserva['data'] . ' ' . $tempoName), ENT_QUOTES, 'UTF-8') . "'>";
                        
                        // Room column
                        echo "<td><strong>" . htmlspecialchars($salaName, ENT_QUOTES, 'UTF-8') . "</strong></td>";
                        
                        // Date column
                        echo "<td>";
                        echo "<div><strong>" . htmlspecialchars($dataFormatted, ENT_QUOTES, 'UTF-8') . "</strong></div>";
                        echo "<small class='text-muted'>" . htmlspecialchars($dayName, ENT_QUOTES, 'UTF-8') . "</small>";
                        if ($isToday) {
                            echo " <span class='badge bg-primary ms-1'>Hoje</span>";
                        } elseif ($isPast) {
                            echo " <span class='badge bg-secondary ms-1'>Passado</span>";
                        }
                        echo "</td>";
                        
                        // Time column
                        echo "<td><span class='badge horario-badge'>" . htmlspecialchars($tempoName, ENT_QUOTES, 'UTF-8') . "</span></td>";
                        
                        // Status column
                        echo "<td>";
                        if ($isApproved) {
                            echo "<span class='badge bg-success' data-bs-toggle='tooltip' title='A sua reserva foi aprovada! Um email foi lhe enviado com mais informações.'>
                                    &#x2705; Aprovado
                                  </span>";
                        } else {
                            echo "<span class='badge bg-warning text-dark' data-bs-toggle='tooltip' title='A sua reserva foi enviada e está a ser revista. Irá receber um email com mais informações em breve.'>
                                    &#x23F3; Pendente
                                  </span>";
                        }
                        echo "</td>";
                        
                        // Actions column
                        // Escape data for JavaScript using json_encode for proper escaping
                        $salaNameJs = json_encode($salaName, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
                        $dataFormattedJs = json_encode($dataFormatted, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
                        $tempoNameJs = json_encode($tempoName, JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG);
                        
                        // Determine if delete button should be shown
                        // Show delete button only if: it's not a past reservation OR user is an admin
                        $canDelete = !$isPast || $_SESSION['admin'];
                        
                        echo "<td class='text-center'>
                                <div class='btn-group' role='group'>
                                    <a href='/reservar/manage.php?tempo={$tempoEnc}&sala={$salaEnc}&data={$dataEnc}' 
                                       class='btn btn-outline-primary btn-sm action-btn' 
                                       title='Ver detalhes'>
                                        &#x1F441; Detalhes
                                    </a>";
                        
                        if ($canDelete) {
                            echo "<button type='button' 
                                            class='btn btn-outline-danger btn-sm action-btn' 
                                            onclick='confirmDelete(\"{$tempoEnc}\", \"{$salaEnc}\", \"{$dataEnc}\", {$salaNameJs}, {$dataFormattedJs}, {$tempoNameJs})' 
                                            title='Apagar reserva'>
                                        &#x1F5D1; Apagar
                                    </button>";
                        }
                        
                        echo "</div>
                              </td>";
                        
                        echo "</tr>";
                    }
                    
                    echo "</tbody></table></div>";
                }
                $stmt->close();
                ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirmar Eliminação</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body" id="deleteModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Apagar Reserva</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Filter reservations by status
        function filterReservations(filter, event) {
            const rows = document.querySelectorAll('#reservationsTable tbody tr');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update button states
            buttons.forEach(btn => btn.classList.remove('active'));
            if (event && event.target && event.target.classList.contains('filter-btn')) {
                event.target.classList.add('active');
            }
            
            rows.forEach(row => {
                const status = row.getAttribute('data-status');
                const isFuture = row.getAttribute('data-future') === 'true';
                
                let show = false;
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'pending':
                        show = (status === 'pending');
                        break;
                    case 'approved':
                        show = (status === 'approved');
                        break;
                    case 'future':
                        show = isFuture;
                        break;
                }
                
                row.style.display = show ? '' : 'none';
            });
        }
        
        // Search reservations
        function searchReservations() {
            const searchInput = document.getElementById('searchInput');
            const filter = searchInput.value.toLowerCase();
            const rows = document.querySelectorAll('#reservationsTable tbody tr');
            
            rows.forEach(row => {
                const searchData = row.getAttribute('data-search');
                if (searchData && searchData.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Confirm delete modal
        function confirmDelete(tempo, sala, data, salaNome, dataFormatted, horasNome) {
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            const modalBody = document.getElementById('deleteModalBody');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            
            modalBody.innerHTML = `
                <div class="text-center mb-3">
                    <div style="font-size: 4rem;">&#x26A0;</div>
                </div>
                <h5 class="text-center text-danger">Tem a certeza que pretende apagar esta reserva?</h5>
                <div class="alert alert-light border mt-3">
                    <p class="mb-1"><strong>Sala:</strong> ${salaNome}</p>
                    <p class="mb-1"><strong>Data:</strong> ${dataFormatted}</p>
                    <p class="mb-0"><strong>Horário:</strong> ${horasNome}</p>
                </div>
                <div class="alert alert-warning">
                    <strong>Atenção:</strong> Esta ação é irreversível.
                </div>
            `;
            
            confirmBtn.href = '/reservar/manage.php?subaction=apagar&tempo=' + tempo + '&sala=' + sala + '&data=' + data;
            
            modal.show();
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="/assets/tooltips.js"></script>
    <script>
        // Initialize Twemoji to parse all emojis on the page
        document.addEventListener('DOMContentLoaded', function() {
            twemoji.parse(document.body, {
                folder: 'svg',
                ext: '.svg'
            });
        });
    </script>
</body>

</html>