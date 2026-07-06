<?php
require_once(__DIR__ . '/../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (isset($_SESSION['pending_totp_user']) || isset($_SESSION['pending_user_setup'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Autenticação incompleta']);
    exit;
}

if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada']);
    exit;
}

$sala = trim($_GET['sala'] ?? '');
$before = trim($_GET['before'] ?? '');

if ($sala === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Sala inválida']);
    exit;
}

$stmt = $db->prepare('SELECT id, tipo_sala, bloqueado FROM salas WHERE id = ?');
$stmt->bind_param('s', $sala);
$stmt->execute();
$salaData = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$salaData) {
    http_response_code(404);
    echo json_encode(['error' => 'Sala não encontrada']);
    exit;
}

if ($before !== '') {
    $date = DateTime::createFromFormat('d-m-Y', $before);
    $dateErrors = DateTime::getLastErrors();
    $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
    if (!$date || $hasDateErrors) {
        http_response_code(400);
        echo json_encode(['error' => 'Data inválida']);
        exit;
    }
    $segunda = strtotime($date->format('Y-m-d'));
} else {
    $segunda = strtotime('monday this week');
}

$today = date('Y-m-d');
$canCreateReservation = ($salaData['bloqueado'] != 1 || !empty($_SESSION['admin']));
$days = [];
for ($i = 0; $i < 7; $i++) {
    $dayDate = date('Y-m-d', strtotime("+{$i} day", $segunda));
    $days[] = [
        'date' => $dayDate,
        'label' => date('d/m', strtotime($dayDate)) . '<br>' . date('Y', strtotime($dayDate)),
        'isToday' => $dayDate === $today,
        'isPast' => $dayDate < $today,
    ];
}

$tempos = [];
$temposResult = $db->query('SELECT id, horashumanos FROM tempos ORDER BY horashumanos ASC');
while ($tempo = $temposResult->fetch_assoc()) {
    $tempos[] = $tempo;
}

$statusBySlot = [];
$startDate = $days[0]['date'];
$endDate = $days[6]['date'];
$stmt = $db->prepare("SELECT r.tempo, r.data, r.aprovado, c.nome AS requisitor_nome
    FROM reservas r
    LEFT JOIN cache c ON c.id = r.requisitor
    WHERE r.sala = ? AND r.data BETWEEN ? AND ?");
$stmt->bind_param('sss', $sala, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
while ($reservation = $result->fetch_assoc()) {
    if ($reservation['aprovado'] == -1) {
        continue;
    }
    $nome = $reservation['requisitor_nome'] ?? '';
    $nome = preg_replace('/^(\S+).*?(\S+)$/u', '$1 $2', $nome);
    $statusBySlot[$reservation['tempo'] . '|' . $reservation['data']] = [
        'status' => $reservation['aprovado'] == 0 ? 'pending' : 'occupied',
        'label' => $reservation['aprovado'] == 0 ? 'Pendente' : 'Ocupado',
        'requisitor' => $nome,
    ];
}
$stmt->close();

$slots = [];
foreach ($tempos as $tempo) {
    foreach ($days as $day) {
        $key = $tempo['id'] . '|' . $day['date'];
        $canInteract = (!$day['isPast'] || !empty($_SESSION['admin']));
        $slots[$tempo['id']][$day['date']] = $statusBySlot[$key] ?? [
            'status' => 'free',
            'label' => 'Livre',
            'requisitor' => '',
        ];
        $slots[$tempo['id']][$day['date']]['canInteract'] = $canInteract;
        $slots[$tempo['id']][$day['date']]['canCreateReservation'] = $canCreateReservation;
    }
}

echo json_encode([
    'sala' => $sala,
    'before' => $before,
    'previousWeek' => date('d-m-Y', strtotime('-1 week', $segunda)),
    'nextWeek' => date('d-m-Y', strtotime('+1 week', $segunda)),
    'currentWeek' => '',
    'days' => $days,
    'tempos' => $tempos,
    'slots' => $slots,
]);
