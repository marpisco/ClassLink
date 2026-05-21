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

$stmt = $db->prepare("SELECT id, nome, email FROM cache WHERE id = ? OR id LIKE ? ESCAPE '\\\\' OR nome LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\' ORDER BY nome ASC LIMIT ?");
$stmt->bind_param("ssssi", $query, $idPrefixParam, $searchParam, $searchParam, $limit);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = [
        'id' => $row['id'],
        'title' => $row['nome'],
        'subtitle' => $row['email']
    ];
}

$stmt->close();
$db->close();

echo json_encode(['items' => $items, 'limit' => $limit, 'requiresFilter' => false]);
?>
