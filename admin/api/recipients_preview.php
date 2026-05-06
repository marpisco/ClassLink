<?php
require_once(__DIR__ . '/../../src/db.php');
require_once(__DIR__ . '/../../func/validation.php');
session_start();

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

// Get parameters
$emailMode = isset($_GET['email_mode']) ? $_GET['email_mode'] : '';
$selectedWeek = isset($_GET['week']) ? trim($_GET['week']) : '';
$selectedClassroom = isset($_GET['classroom']) ? $_GET['classroom'] : '';

if (empty($emailMode)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing email mode']);
    exit;
}

$startOfWeek = null;
$endOfWeek = null;
if (!empty($selectedWeek)) {
    $weekParts = explode('-W', $selectedWeek);
    if (count($weekParts) === 2) {
        $year = $weekParts[0];
        $week = $weekParts[1];
        $startOfWeek = date('Y-m-d', strtotime($year . 'W' . $week . '1'));
        $endOfWeek = date('Y-m-d', strtotime($year . 'W' . $week . '7'));
    }
}

$result = null;
$recipients = [];

if ($emailMode === 'admins') {
    $query = "SELECT nome, email FROM cache WHERE admin = 1 ORDER BY nome ASC";
    $stmt = $db->prepare($query);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
    }
} else {
    $where = "1=1";
    $params = array();
    $types = "";

    if (!empty($startOfWeek) && !empty($endOfWeek)) {
        $where .= " AND r.data >= ? AND r.data <= ?";
        $params[] = $startOfWeek;
        $params[] = $endOfWeek;
        $types .= "ss";
    }

    if (!empty($selectedClassroom)) {
        $where .= " AND r.sala = ?";
        $params[] = $selectedClassroom;
        $types .= "s";
    }

    $query = "SELECT DISTINCT c.nome, c.email 
              FROM cache c
              INNER JOIN reservas r ON c.id = r.requisitor
              WHERE $where
              ORDER BY c.nome ASC";

    $stmt = $db->prepare($query);
    if ($stmt) {
        if (count($params) > 0) {
            $bind_names = array();
            $bind_names[] = $types;
            for ($i = 0; $i < count($params); $i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array(array($stmt, 'bind_param'), $bind_names);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    }
}

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recipients[] = $row['nome'] . ' (' . $row['email'] . ')';
    }
    if (isset($stmt)) $stmt->close();
}

echo json_encode([
    'count' => count($recipients),
    'recipients' => $recipients
]);
