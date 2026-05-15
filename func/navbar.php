<?php
require_once(__DIR__ . '/get_config.php');
$isDevMode = is_development_mode();
?>

<?php if ($isDevMode): ?>
<div class="dev-mode-banner-top" style="background-color: #dc3545 !important; color: white !important; padding: 4px !important; font-size: 12px !important; font-weight: bold !important; text-align: center !important;">
    ⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento
</div>
<?php endif; ?>

<nav class="navbar navbar-expand-lg classlink-navbar<?php echo $isDevMode ? ' dev-mode-nav' : ''; ?>">
    <div class="container-fluid" style="padding: 0 4%;">
        <a href="/" class="logo-link navbar-brand m-0 p-0">
            <img src="/assets/logo.png" class="logo" alt="ClassLink">
            <span class="logo-text">ClassLink</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarList" aria-controls="navbarList" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse list" id="navbarList">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                <li class="nav-item"><a class="nav-link" href="/reservas">As minhas reservas</a></li>
                <li class="nav-item"><a class="nav-link" href="/reservar">Reservar sala</a></li>
                <li class="nav-item"><a class="nav-link" href="/assets/manual_utilizador_classlink.pdf" target="_blank">Manual de Utilizador</a></li>
                <?php
                if (!empty($_SESSION['admin'])) {
                    echo "<li class='nav-item'><a class='nav-link' href='/admin/'>Painel Administrativo</a></li>";
                }
                ?>
                <li class="nav-item"><a class="nav-link" href="/login/?action=logout">Terminar sessão</a></li>
            </ul>
        </div>
    </div>
</nav>

<?php if ($isDevMode): ?>
<div class="dev-mode-banner-bottom" style="background-color: #dc3545 !important; color: white !important; padding: 4px !important; font-size: 12px !important; font-weight: bold !important; text-align: center !important; position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; z-index: 9999 !important;">
    ⚠️ MODO DE DESENVOLVIMENTO - Dados de teste | Base de dados de desenvolvimento
</div>
<?php endif; ?>
