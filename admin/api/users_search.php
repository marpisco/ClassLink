<?php
require_once(__DIR__ . '/../../src/db.php');
require_once(__DIR__ . '/../../func/validation.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search'], 100) : '';
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = 20;

if ($action === 'search') {
    if ($search !== '') {
        // Escape SQL LIKE wildcards to prevent unintended pattern matching
        $escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);
        $searchParam = '%' . $escapedSearch . '%';
        $stmt = $db->prepare("SELECT id, nome, email, admin FROM cache WHERE nome LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\' ORDER BY nome ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ssii", $searchParam, $searchParam, $limit, $offset);
    } else {
        $stmt = $db->prepare("SELECT id, nome, email, admin FROM cache ORDER BY nome ASC LIMIT ? OFFSET ?");
        $stmt->bind_param("ii", $limit, $offset);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'admin' => (bool)$row['admin'],
            'isPreRegistered' => str_starts_with($row['id'], PRE_REGISTERED_PREFIX)
        ];
    }
    $stmt->close();
    
    // Get total count for the search
    if ($search !== '') {
        // Escape SQL LIKE wildcards to prevent unintended pattern matching
        $escapedSearch = str_replace(['%', '_'], ['\\%', '\\_'], $search);
        $searchParam = '%' . $escapedSearch . '%';
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM cache WHERE nome LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\' ");
        $countStmt->bind_param("ss", $searchParam, $searchParam);
    } else {
        $countStmt = $db->prepare("SELECT COUNT(*) as total FROM cache");
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalCount = $countResult->fetch_assoc()['total'];
    $countStmt->close();
    
    echo json_encode([
        'users' => $users,
        'total' => $totalCount,
        'offset' => $offset,
        'limit' => $limit,
        'hasMore' => ($offset + count($users)) < $totalCount
    ]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Ação inválida.']);
}

$db->close();
?>
