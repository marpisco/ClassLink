<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
        http_response_code(403);
        header("Location: /login");
        die("A reencaminhar para iniciar sessão...");
    } else {
        // A validade da sessão está quase a expirar. Extender por mais 30 minutos.
        if ($_SESSION['validity'] - time() < 900) {
            $_SESSION['validity'] = time() + 1800;
        }
    }
    
    require_once(__DIR__ . '/src/db.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.12.9/dist/umd/popper.min.js"></script>
    <link rel="stylesheet" href="/assets/index.css?v=<?php echo time(); ?>">
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <link rel='icon' href='/assets/logo.png'>
</head>
<body>
    <?php require_once(__DIR__ . '/func/navbar.php'); ?>
    <div class="text">
        <h3>Seja bem vindo, <?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?>, ao <br> <span>ClassLink</span> </h3>
        <p>O que é que vamos fazer hoje?</p>
        <a href="/reservar" class="btn">Reservar uma Sala</a>
        <div class="bottom" style="position: fixed; bottom: 0; width:100%; margin-bottom: 10px;">
            <img src="/assets/poch.png" alt="Pessoas 2030 | Portugal 2030 | Cofinanciado pela União Europeia" style="max-width: 15%">
            <img src="/assets/rep_edu.png" alt="República Portuguesa Educação Ciência e Inovação" style="max-width: 6%">
        </div>
    </div>
    <div class="circle"></div>
</body>
</html>
