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

$currentYear = date('Y');
$currentWeekStart = date('Y-m-d', strtotime('monday this week'));
$currentWeekEnd = date('Y-m-d', strtotime('sunday this week'));

// Top Reservers for the current year (approved reservations only)
$topReserversStmt = $db->prepare("
    SELECT c.nome, COUNT(*) as total_reservas
    FROM reservas r
    INNER JOIN cache c ON r.requisitor = c.id
    WHERE r.aprovado = 1 AND YEAR(r.data) = ?
    GROUP BY r.requisitor, c.nome
    ORDER BY total_reservas DESC
    LIMIT 10
");
$topReserversStmt->bind_param("i", $currentYear);
$topReserversStmt->execute();
$topReserversResult = $topReserversStmt->get_result();

$topReservers = [];
while ($row = $topReserversResult->fetch_assoc()) {
    $topReservers[] = [
        'label' => $row['nome'],
        'y' => intval($row['total_reservas'])
    ];
}
$topReserversStmt->close();

// Reservations per classroom for the current week (approved reservations only)
$reservationsPerRoomStmt = $db->prepare("
    SELECT s.nome, COUNT(*) as total_reservas
    FROM reservas r
    INNER JOIN salas s ON r.sala = s.id
    WHERE r.aprovado = 1 AND r.data >= ? AND r.data <= ?
    GROUP BY r.sala, s.nome
    ORDER BY total_reservas DESC
");
$reservationsPerRoomStmt->bind_param("ss", $currentWeekStart, $currentWeekEnd);
$reservationsPerRoomStmt->execute();
$reservationsPerRoomResult = $reservationsPerRoomStmt->get_result();

$reservationsPerRoom = [];
while ($row = $reservationsPerRoomResult->fetch_assoc()) {
    $reservationsPerRoom[] = [
        'label' => $row['nome'],
        'y' => intval($row['total_reservas'])
    ];
}
$reservationsPerRoomStmt->close();

echo json_encode([
    'topReservers' => $topReservers,
    'reservationsPerRoom' => $reservationsPerRoom,
    'currentYear' => $currentYear,
    'weekRange' => [
        'start' => $currentWeekStart,
        'end' => $currentWeekEnd
    ]
]);

$db->close();
?>