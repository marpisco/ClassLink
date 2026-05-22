<?php
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}
// Check session validity
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada']);
    exit;
}
// Guard: reject pending TOTP/setup flows from API access
if (isset($_SESSION['pending_totp_user']) || isset($_SESSION['pending_user_setup'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Autenticação incompleta']);
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Sanitize limit and offset
if ($limit < 1) $limit = 10;
if ($limit > 100) $limit = 100;
if ($offset < 0) $offset = 0;

// Escape LIKE wildcards for safe search
$escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);

if ($search !== '') {
    $searchPattern = '%' . $escapedSearch . '%';
    $stmt = $db->prepare("SELECT id, horashumanos FROM tempos WHERE horashumanos LIKE ? ESCAPE '\\' ORDER BY horashumanos ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $searchPattern, $limit, $offset);
} else {
    $stmt = $db->prepare("SELECT id, horashumanos FROM tempos ORDER BY horashumanos ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

$tempos = [];
while ($row = $result->fetch_assoc()) {
    $tempos[] = [
        'id' => $row['id'],
        'horashumanos' => $row['horashumanos']
    ];
}
$stmt->close();

// Get total count for pagination
if ($search !== '') {
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM tempos WHERE horashumanos LIKE ? ESCAPE '\\'");
    $countStmt->bind_param("s", $searchPattern);
} else {
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM tempos");
}
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$total = $totalResult['total'];
$countStmt->close();

$db->close();

echo json_encode([
    'tempos' => $tempos,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
