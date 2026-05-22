<?php
require_once(__DIR__ . '/../../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
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

// Get pagination parameters
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 10;
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Build query with optional search filter
$whereClause = '';
$params = [];
$types = '';

if (!empty($search)) {
    $likeSearch = '%' . $search . '%';
    $whereClause = " WHERE l.loginfo LIKE ? OR c.nome LIKE ? OR c.email LIKE ? OR l.ip_address LIKE ?";
    $params = [$likeSearch, $likeSearch, $likeSearch, $likeSearch];
    $types = "ssss";
}

// Count total matching records
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM logs l LEFT JOIN cache c ON l.userid = c.id" . $whereClause);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = $countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();

// Fetch logs with user information
$query = "SELECT l.id, l.loginfo, l.timestamp, l.ip_address, c.nome, c.email
    FROM logs l
    LEFT JOIN cache c ON l.userid = c.id" . $whereClause . "
    ORDER BY l.timestamp DESC
    LIMIT ? OFFSET ?";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $bindParams = array_merge($params, [$limit, $offset]);
    $stmt->bind_param($types . "ii", ...$bindParams);
} else {
    $stmt->bind_param("ii", $limit, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$logs = [];
while ($row = $result->fetch_assoc()) {
    $logs[] = [
        'id' => $row['id'],
        'loginfo' => $row['loginfo'],
        'timestamp' => $row['timestamp'],
        'ip_address' => $row['ip_address'],
        'nome' => $row['nome'],
        'email' => $row['email']
    ];
}
$stmt->close();

echo json_encode(['logs' => $logs, 'total' => intval($total), 'limit' => $limit, 'offset' => $offset]);

$db->close();
?>
