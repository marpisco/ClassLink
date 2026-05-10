<nav>
    <a href="/"><img src="/assets/logo.png" class="logo" alt="ClassLink"></a>
    <div class="list">
        <ul>
            <li><a href="/reservas">As minhas reservas</a></li>
            <li><a href="/reservar">Reservar sala</a></li>
            <li><a href="/docs/">Documentação</a></li>
            <?php
            if (!empty($_SESSION['admin'])) {
                echo "<li><a href='/admin/'>Painel Administrativo</a></li>";
            }
            ?>
            <li><a href="/login/?action=logout">Terminar sessão</a></li>
        </ul>
    </div>
</nav>