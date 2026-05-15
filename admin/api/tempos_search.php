<?php
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado']);
    exit;
}

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 20;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

// Sanitize limit and offset
if ($limit < 1) $limit = 20;
if ($limit > 100) $limit = 100;
if ($offset < 0) $offset = 0;

if ($search !== '') {
    $searchPattern = '%' . $search . '%';
    $stmt = $db->prepare("SELECT id, horashumanos FROM tempos WHERE horashumanos LIKE ? ORDER BY horashumanos ASC LIMIT ? OFFSET ?");
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
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM tempos WHERE horashumanos LIKE ?");
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
