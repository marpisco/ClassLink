<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel='icon' href='/assets/logo.png'>
    <script src="/assets/theme-switcher.js"></script>
</head>
<body class="admin-panel">
<?php
    require_once(__DIR__ . '/../src/db.php');
    require_once(__DIR__ . '/../func/genuuid.php');
    session_start();
    if (!$_SESSION['admin']) {
        http_response_code(403);
        die("<div class='alert alert-danger text-center'>Não pode entrar no Painel Administrativo. <a href='/'>Voltar para a página inicial</a></div>");
    }
    if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
        http_response_code(403);
        header("Location: /login");
        die("A reencaminhar para iniciar sessão...");
    } else {
        // A validade da sessão está quase a expirir. Extender a sessão por mais 1h.
        if ($_SESSION['validity'] - time() < 900) {
            $_SESSION['validity'] = time() + 3600;
        }
    }
?>
<?php
    // Criação da Sidebar (reaproveito do módulo para as subpáginas)
    // Links do Sidebar
    function sidebarLink($url, $nome) {
        if ($url == "/admin/" && $_SERVER['REQUEST_URI'] == "/admin/") {
            echo "<li class='nav-item'><a href='$url' class='nav-link active'>$nome</a></li>";
        } else if (str_starts_with($_SERVER['REQUEST_URI'], $url) && $url != "/admin/" && $url != "/") {
            echo "<li class='nav-item'><a href='$url' class='nav-link active'>$nome</a></li>";
        } else {
            echo "<li class='nav-item'><a href='$url' class='nav-link'>$nome</a></li>";
        }
    }

    // Criação da Navbar no HTML
    echo "<nav class='navbar navbar-expand-lg border-bottom' id='admin-navbar'>
    <div class='container-fluid'>
        <span class='navbar-brand mb-0 h1'>Dashboard de Administração</span>
        <button class='navbar-toggler' type='button' data-bs-toggle='collapse' data-bs-target='#navbarNav' aria-controls='navbarNav' aria-expanded='false' aria-label='Toggle navigation'>
            <span class='navbar-toggler-icon'></span>
        </button>
        <div class='collapse navbar-collapse' id='navbarNav'>
            <ul class='navbar-nav ms-auto'>";
    // Links da Navbar
    sidebarLink('/admin/', 'Dashboard');
    sidebarLink('/admin/pedidos.php', 'Pedidos');
    sidebarLink('/admin/tempos.php', 'Tempos');
    sidebarLink('/admin/salas.php', 'Salas');
    sidebarLink('/admin/materiais.php', 'Materiais');
    sidebarLink('/admin/salas_postreserva.php', 'Pós-Reserva');
    sidebarLink('/admin/users.php', 'Utilizadores');
    sidebarLink('/admin/registos.php', 'Registos');
    sidebarLink('/docs/', 'Documentação');
    echo "<li class='nav-item dropdown'>
            <a class='nav-link dropdown-toggle' href='#' id='extensibilidadeDropdown' role='button' data-bs-toggle='dropdown' aria-expanded='false'>
                Extensibilidade
            </a>
            <ul class='dropdown-menu dropdown-menu-end' aria-labelledby='extensibilidadeDropdown'>";
    foreach (glob(__DIR__ . "/scripts/*.php") as $scriptFile) {
        // Skip example.php and logsadmin.php (moved to registos.php tab)
        if (basename($scriptFile) !== "example.php" && basename($scriptFile) !== "logsadmin.php") {
            $scriptName = basename($scriptFile, ".php");
            echo "<li>";
            echo "<a class='dropdown-item' href='/admin/scripts/$scriptName.php'>" . ucfirst($scriptName) . "</a>";
            echo "</li>";
        }
    }
    echo "</ul></li>";
    echo "<li class='nav-item'><a href='/' class='nav-link'>Voltar</a></li>";
    // Fechar Navbar no HTML, e passar o conteúdo para baixo
    echo "</ul></div></div></nav><div class='container-fluid mt-4 justify-content-center text-center'>";

?>

<?php
    // Bugfix: /admin buga os navbar links
    if ($_SERVER['REQUEST_URI'] == "/admin") {
        header("Location: /admin/");
        die();
    }

    if ($_SERVER['REQUEST_URI'] == "/admin/") {
        // Conteúdos para a Dashboard Administrativa. Apenas colocar o conteúdo neste bloco, pois
        // este módulo é reutilizado para as subpáginas.
        $nome_safe = htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8');
        $currentYear = date('Y');
        echo "<div class='d-flex align-items-center justify-content-center flex-column'>
        <h1>Olá, {$nome_safe}</h1>
        <p class='h4 fw-lighter'>O que vamos fazer hoje?</p>
        </div>";
        
        // Dashboard Charts Section
        echo "<div class='row mt-4' style='margin-left: 5%; margin-right: 5%;'>
            <div class='col-md-6 mb-4'>
                <div class='card shadow-sm'>
                    <div class='card-header bg-primary text-white'>
                        <h5 class='mb-0'>Top Reservadores - {$currentYear}</h5>
                    </div>
                    <div class='card-body'>
                        <div id='topReserversChart' style='height: 300px; width: 100%;'></div>
                        <p class='text-muted text-center mt-2' id='topReserversNoData' style='display: none;'>Sem dados disponíveis.</p>
                    </div>
                </div>
            </div>
            <div class='col-md-6 mb-4'>
                <div class='card shadow-sm'>
                    <div class='card-header bg-success text-white'>
                        <h5 class='mb-0'>Reservas por Sala - Esta Semana</h5>
                    </div>
                    <div class='card-body'>
                        <div id='reservationsPerRoomChart' style='height: 300px; width: 100%;'></div>
                        <p class='text-muted text-center mt-2' id='reservationsPerRoomNoData' style='display: none;'>Sem dados disponíveis.</p>
                    </div>
                </div>
            </div>
        </div>";
        
        // CanvasJS CDN and chart rendering script
        echo "<script src='https://cdn.canvasjs.com/canvasjs.min.js'></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Detect theme preference
            const isDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            const chartTheme = isDarkMode ? 'dark2' : 'light2';
            
            fetch('/admin/api/dashboard_stats.php')
                .then(response => response.json())
                .then(data => {
                    // Top Reservers Chart
                    if (data.topReservers && data.topReservers.length > 0) {
                        var topReserversChart = new CanvasJS.Chart('topReserversChart', {
                            animationEnabled: true,
                            theme: chartTheme,
                            axisY: {
                                title: 'Número de Reservas',
                                includeZero: true
                            },
                            axisX: {
                                title: 'Utilizadores',
                                interval: 1,
                                labelAngle: -45
                            },
                            data: [{
                                type: 'column',
                                color: '#0d6efd',
                                dataPoints: data.topReservers
                            }]
                        });
                        topReserversChart.render();
                    } else {
                        document.getElementById('topReserversChart').style.display = 'none';
                        document.getElementById('topReserversNoData').style.display = 'block';
                    }
                    
                    // Reservations per Room Chart
                    if (data.reservationsPerRoom && data.reservationsPerRoom.length > 0) {
                        var reservationsPerRoomChart = new CanvasJS.Chart('reservationsPerRoomChart', {
                            animationEnabled: true,
                            theme: chartTheme,
                            axisY: {
                                title: 'Número de Reservas',
                                includeZero: true
                            },
                            axisX: {
                                title: 'Salas',
                                interval: 1,
                                labelAngle: -45
                            },
                            data: [{
                                type: 'column',
                                color: '#198754',
                                dataPoints: data.reservationsPerRoom
                            }]
                        });
                        reservationsPerRoomChart.render();
                    } else {
                        document.getElementById('reservationsPerRoomChart').style.display = 'none';
                        document.getElementById('reservationsPerRoomNoData').style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error loading dashboard stats:', error);
                    var errorMsg = '<p class=\"text-danger text-center\">Erro ao carregar estatísticas.</p>';
                    document.getElementById('topReserversChart').innerHTML = errorMsg;
                    document.getElementById('reservationsPerRoomChart').innerHTML = errorMsg;
                });
        });
        </script>";
    }

        // criação rápida de formulários
        function formulario($action, $inputs) {
            $action_safe = htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
            echo "<form action='$action_safe' method='POST' class='d-flex align-items-center'>";
            foreach ($inputs as $input) {
                $id_safe = htmlspecialchars($input['id'], ENT_QUOTES, 'UTF-8');
                $value_safe = htmlspecialchars($input['value'], ENT_QUOTES, 'UTF-8');
                $label_safe = htmlspecialchars($input['label'], ENT_QUOTES, 'UTF-8');
                
                if ($input['type'] == "checkbox") {
                    echo "<div class='form-check me-2' style='flex: 1;'>
                        <input type='checkbox' class='form-check-input' id='$id_safe' name='$id_safe' value='$value_safe'>
                        <label class='form-check-label' for='$id_safe'>$label_safe</label>
                        </div>";
                } else {
                    $type_safe = htmlspecialchars($input['type'], ENT_QUOTES, 'UTF-8');
                    $placeholder_safe = htmlspecialchars($input['placeholder'], ENT_QUOTES, 'UTF-8');
                    echo "<div class='form-floating me-2' style='flex: 1;'>
                    <input type='$type_safe' class='form-control form-control-sm' id='$id_safe' name='$id_safe' placeholder='$placeholder_safe' value='$value_safe' required>
                    <label for='$id_safe'>$label_safe</label>
                    </div>";
                }
            }
            echo "<button type='submit' class='btn btn-primary btn-sm' style='height: 38px;'>Submeter</button></form>";
        }
    
        // ação executada
        function acaoexecutada($acao) {
            require_once(__DIR__ . '/../func/logaction.php');
            $acao_safe = htmlspecialchars($acao, ENT_QUOTES, 'UTF-8');
            echo "<div class='alert alert-success alert-dismissible fade show' role='alert'>Ação executada. <b>$acao_safe</b>
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Fechar'></button></div>";
            logaction($acao . ".\nPOST: " . var_export($_POST, true) . "\nGET: " . var_export($_GET, true), $_SESSION['id']);
        }    
?>

<?php if ($_SERVER['REQUEST_URI'] == "/admin/"): ?>
<!-- Version Alert Container at Bottom - Only on Dashboard -->
<div id='versionAlertContainer' style='margin-top: 20px;'></div>

<script>
// Version Checker - Checks for new commits on main branch - ONLY ON DASHBOARD
(function() {
    function checkVersion() {
        fetch('/admin/api/version_check.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showVersionAlert(data);
                } else if (data.error) {
                    console.warn('Version check error:', data.error);
                }
            })
            .catch(error => {
                console.error('Version check failed:', error);
            });
    }
    
    function showVersionAlert(data) {
        const container = document.getElementById('versionAlertContainer');
        if (!container) return;
        
        let alertHtml = '';
        const cacheStatus = data.cached ? ' (cache)' : '';
        
        if (data.commitsBehind > 0) {
            // Behind - show warning
            const plural = data.commitsBehind > 1 ? 'versões' : 'versão';
            const aheadText = data.commitsAhead > 0 ? ` (${data.commitsAhead} commit${data.commitsAhead > 1 ? 's' : ''} à frente)` : '';
            
            alertHtml = `
                <div class="alert alert-warning text-center" role="alert">
                    <strong>⚠️ Está a correr uma versão desatualizada do ClassLink.</strong> 
                    Está atrasado <strong>${data.commitsBehind}</strong> ${plural}${aheadText}.
                    <button type="button" class="btn btn-sm btn-outline-warning ms-3" data-bs-toggle="collapse" data-bs-target="#versionDetails">
                        Ver Detalhes
                    </button>
                    <div class="collapse mt-2" id="versionDetails">
                        <small class="d-block">
                            <strong>Último commit:</strong> ${escapeHtml(data.lastCommitMessage)}<br>
                            <strong>Autor:</strong> ${escapeHtml(data.lastCommitAuthor)}<br>
                            <strong>Data:</strong> ${formatDate(data.lastCommitDate)}<br>
                            <strong>Hash:</strong> ${data.currentCommit} → ${data.remoteCommit}
                        </small>
                    </div>
                </div>
            `;
        } else {
            // Up to date - show success
            alertHtml = `
                <div class="alert alert-success text-center" role="alert">
                    <strong>✅ Está a correr uma versão atualizada do ClassLink!</strong>
                    <small class="d-block mt-1 text-muted">
                        Última verificação: ${formatDate(data.timestamp)}${cacheStatus}
                    </small>
                </div>
            `;
        }
        
        container.innerHTML = alertHtml;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleString('pt-PT', {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    // Check version on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkVersion);
    } else {
        checkVersion();
    }
    
    // Check version every 15 minutes
    setInterval(checkVersion, 15 * 60 * 1000);
})();
</script>
<?php endif; ?>

</body>
</html>