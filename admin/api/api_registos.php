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

// Get pagination parameters
// Note: OFFSET-based pagination is used for simplicity. For very large datasets,
// consider cursor-based pagination using timestamp or id for better performance.
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 50;

// Fetch logs with user information
$stmt = $db->prepare("
    SELECT l.id, l.loginfo, l.timestamp, l.ip_address, c.nome, c.email 
    FROM logs l 
    LEFT JOIN cache c ON l.userid = c.id 
    ORDER BY l.timestamp DESC 
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $limit, $offset);
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

echo json_encode(['logs' => $logs]);

$db->close();
?>
