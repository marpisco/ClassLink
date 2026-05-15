<?php
header('Content-Type: application/json; charset=utf-8');
require_once(__DIR__ . '/../../src/db.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $stmt = $db->prepare("SELECT id, nome, tipo_sala, bloqueado, post_reservation_content FROM salas WHERE nome LIKE ? ORDER BY nome ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("sii", $searchPattern, $limit, $offset);
} else {
    $stmt = $db->prepare("SELECT id, nome, tipo_sala, bloqueado, post_reservation_content FROM salas ORDER BY nome ASC LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $limit, $offset);
}

$stmt->execute();
$result = $stmt->get_result();

$salas = [];
while ($row = $result->fetch_assoc()) {
    $sala = [
        'id' => $row['id'],
        'nome' => $row['nome'],
        'tipo_sala' => $row['tipo_sala'],
        'bloqueado' => $row['bloqueado']
    ];
    // Include post_reservation_content status if requested
    if (isset($_GET['include_postreserva'])) {
        $sala['has_post_reservation_content'] = !empty($row['post_reservation_content']);
    }
    $salas[] = $sala;
}
$stmt->close();

// Get total count for pagination
if ($search !== '') {
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM salas WHERE nome LIKE ?");
    $countStmt->bind_param("s", $searchPattern);
} else {
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM salas");
}
$countStmt->execute();
$totalResult = $countStmt->get_result()->fetch_assoc();
$total = $totalResult['total'];
$countStmt->close();

$db->close();

echo json_encode([
    'salas' => $salas,
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset
]);
