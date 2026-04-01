<?php
    require_once(__DIR__ . '/../func/session_config.php');
    session_start();
    if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
        http_response_code(403);
        header("Location: /login");
        die("A reencaminhar para iniciar sessão...");
    } else {
        if ($_SESSION['validity'] - time() < 900) {
            $_SESSION['validity'] = time() + 3600;
        }
    }

    require_once(__DIR__ . '/../func/markdown.php');

    // Allowed documentation files (whitelist – prevents directory traversal)
    $docs_dir = __DIR__;
    $allowed_files = [];
    foreach (glob($docs_dir . '/*.md') as $filepath) {
        $allowed_files[] = basename($filepath);
    }
    sort($allowed_files);

    // Determine which file to show
    $requested = isset($_GET['doc']) ? basename($_GET['doc']) : null;
    $current_file = null;
    $content_html = '';
    $doc_title = '';

    if ($requested !== null && in_array($requested, $allowed_files, true)) {
        $full_path = $docs_dir . '/' . $requested;
        $raw = file_get_contents($full_path);
        if ($raw === false) {
            $content_html = '<div class="alert alert-danger">Erro ao ler o ficheiro de documentação.</div>';
        } else {
            $content_html = parse_markdown($raw);
        }
        $current_file = $requested;
        $doc_title = htmlspecialchars(pathinfo($requested, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
        $doc_title = str_replace(['_', '-'], ' ', $doc_title);
        $doc_title = ucwords($doc_title);
    } elseif (!empty($allowed_files)) {
        // Default: show the first file
        $current_file = $allowed_files[0];
        header("Location: /docs/?doc=" . urlencode($current_file));
        exit;
    }
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $doc_title ? $doc_title . ' - ' : ''; ?>Documentação - ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="/assets/theme.css">
    <link rel="stylesheet" href="/assets/docs.css">
    <link rel='icon' href='/assets/logo.png'>
    <script src="/assets/theme-switcher.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg border-bottom docs-navbar">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center gap-2" href="/">
                <img src="/assets/logo.png" alt="ClassLink" height="32">
                <span class="fw-semibold">ClassLink</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#docsNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="docsNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item"><a class="nav-link" href="/">Início</a></li>
                    <li class="nav-item"><a class="nav-link" href="/reservar">Reservar</a></li>
                    <?php if (!empty($_SESSION['admin'])): ?>
                    <li class="nav-item"><a class="nav-link" href="/admin/">Administração</a></li>
                    <?php endif; ?>
                    <li class="nav-item"><a class="nav-link" href="/login/?action=logout">Terminar sessão</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar with document list -->
            <nav class="col-md-3 col-lg-2 d-md-block docs-sidebar">
                <div class="position-sticky pt-3">
                    <h6 class="sidebar-heading px-3 mt-2 mb-1 text-uppercase fw-semibold">
                        <i class="fa fa-book me-1"></i> Documentação
                    </h6>
                    <ul class="nav flex-column mb-3">
                        <?php foreach ($allowed_files as $file): ?>
                        <?php
                            $label = pathinfo($file, PATHINFO_FILENAME);
                            $label = str_replace(['_', '-'], ' ', $label);
                            $label = ucwords($label);
                            $is_active = ($file === $current_file);
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $is_active ? 'active' : ''; ?>"
                               href="/docs/?doc=<?php echo urlencode($file); ?>">
                                <i class="fa fa-file-text-o me-1"></i>
                                <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($allowed_files)): ?>
                        <li class="nav-item">
                            <span class="nav-link text-muted fst-italic">Sem documentos.</span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main content area -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 docs-main">
                <?php if ($content_html): ?>
                <div class="docs-content">
                    <?php echo $content_html; ?>
                </div>
                <?php elseif (empty($allowed_files)): ?>
                <div class="alert alert-info mt-4">
                    <i class="fa fa-info-circle me-1"></i>
                    Ainda não existem documentos de documentação disponíveis.
                </div>
                <?php else: ?>
                <div class="alert alert-warning mt-4">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    Documento não encontrado. Por favor selecione um documento na lista à esquerda.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
