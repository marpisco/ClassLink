<?php
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../src/db.php');
require_once(__DIR__ . '/../../func/validation.php');
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

$query = isset($_GET['q']) ? sanitize_input($_GET['q'], 100) : '';
$query = trim($query);

if (mb_strlen($query) < 2) {
    echo json_encode(['items' => [], 'limit' => 10, 'requiresFilter' => true]);
    $db->close();
    exit;
}

$escaped = str_replace(['%', '_'], ['\\%', '\\_'], $query);
$idPrefixParam = $escaped . '%';
$searchParam = '%' . $escaped . '%';
$limit = 10;

$stmt = $db->prepare("SELECT id, horashumanos FROM tempos WHERE id LIKE ? ESCAPE '\\\\' OR horashumanos LIKE ? ESCAPE '\\\\' ORDER BY horashumanos ASC LIMIT ?");
$stmt->bind_param("ssi", $idPrefixParam, $searchParam, $limit);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'title' => $row['horashumanos']
    ];
}

$stmt->close();
$db->close();

echo json_encode(['items' => $items, 'limit' => $limit, 'requiresFilter' => false]);
?>
