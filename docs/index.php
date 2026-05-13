<?php
    require_once(__DIR__ . '/../func/session_config.php');
    session_start();
    if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
        header("Location: /login", true, 302);
        exit;
    } else {
        if ($_SESSION['validity'] - time() < 900) {
            $_SESSION['validity'] = time() + 1800;
        }
    }

    require_once(__DIR__ . '/../func/markdown.php');

    // Allowed documentation files (whitelist – prevents directory traversal)
    $docs_dir = __DIR__;
    $docs_dir_real = realpath($docs_dir);
    $allowed_files = [];
    if ($docs_dir_real !== false) {
        foreach (glob($docs_dir . '/*.md') as $filepath) {
            if (!is_file($filepath) || is_link($filepath)) {
                continue;
            }

            $real_path = realpath($filepath);
            if ($real_path === false || !str_starts_with($real_path, $docs_dir_real . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $allowed_files[basename($filepath)] = $real_path;
        }
    }
    ksort($allowed_files);

    // Determine which file to show
    $requested = isset($_GET['doc']) ? basename($_GET['doc']) : null;
    $current_file = null;
    $content_html = '';
    $doc_title = '';

    if ($requested !== null && array_key_exists($requested, $allowed_files)) {
        $raw = file_get_contents($allowed_files[$requested]);
        if ($raw === false) {
            http_response_code(500);
            $content_html = '<div class="alert alert-danger">Erro ao ler o ficheiro de documentação.</div>';
        } else {
            $content_html = parse_markdown($raw);
        }
        $current_file = $requested;
        $doc_title = htmlspecialchars(pathinfo($requested, PATHINFO_FILENAME), ENT_QUOTES, 'UTF-8');
        $doc_title = str_replace(['_', '-'], ' ', $doc_title);
        $doc_title = ucwords($doc_title);
    } elseif ($requested === null && !empty($allowed_files)) {
        // Default: show the first file
        $current_file = array_key_first($allowed_files);
        header("Location: /docs/?doc=" . urlencode($current_file));
        exit;
    } elseif ($requested !== null) {
        http_response_code(404);
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
    <link rel="stylesheet" href="/assets/theme.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/navbar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="/assets/docs.css?v=<?php echo time(); ?>">
    <link rel='icon' href='/assets/logo.png'>
    <script src="/assets/theme-switcher.js"></script>
</head>
<body>
    <?php require_once(__DIR__ . '/../func/navbar.php'); ?>

    <div class="container-fluid docs-content-container">
        <div class="row">
            <!-- Sidebar with document list -->
            <aside class="col-md-3 col-lg-2 docs-sidebar">
                <div class="d-flex justify-content-between align-items-center d-md-none mt-2 mb-2 px-3">
                    <h6 class="sidebar-heading m-0 text-uppercase fw-semibold" style="color: var(--heading-color);">
                        <i class="fa fa-book me-1"></i> Documentação
                    </h6>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#docsMenuCollapse" aria-expanded="false" aria-controls="docsMenuCollapse">
                        <i class="fa fa-bars"></i> Ver Lista
                    </button>
                </div>
                <div class="collapse d-md-block" id="docsMenuCollapse">
                    <div class="position-sticky pt-md-3">
                        <h6 class="sidebar-heading px-3 mt-2 mb-1 text-uppercase fw-semibold d-none d-md-block">
                            <i class="fa fa-book me-1"></i> Documentação
                        </h6>
                        <ul class="nav flex-column mb-3">
                            <?php foreach (array_keys($allowed_files) as $file): ?>
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
                </div>
            </aside>

            <!-- Main content area -->
            <main class="col-md-9 col-lg-10 px-md-4 docs-main">
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
