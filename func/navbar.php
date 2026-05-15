<nav class="navbar navbar-expand-lg classlink-navbar">
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
