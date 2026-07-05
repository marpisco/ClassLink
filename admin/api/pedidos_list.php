<?php
require_once(__DIR__ . '/../../src/db.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    echo json_encode(['error' => 'Sessão expirada']);
    exit;
}
if (isset($_SESSION['pending_totp_user']) || isset($_SESSION['pending_user_setup'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Autenticação incompleta']);
    exit;
}

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;
$sala = $_GET['sala'] ?? '0';
$requisitor = trim($_GET['requisitor'] ?? '');
$search = trim($_GET['q'] ?? '');

$where = ['r.aprovado = 0'];
$params = [];
$types = '';

if ($requisitor !== '') {
    $where[] = 'r.requisitor = ?';
    $params[] = $requisitor;
    $types .= 's';
} elseif ($sala !== '' && $sala !== '0') {
    $where[] = 'r.sala = ?';
    $params[] = $sala;
    $types .= 's';
}

if ($search !== '') {
    $where[] = '(s.nome LIKE ? OR c.nome LIKE ? OR r.motivo LIKE ? OR r.data LIKE ? OR t.horashumanos LIKE ?)';
    $searchParam = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $searchParam;
        $types .= 's';
    }
}

$whereSql = implode(' AND ', $where);

$countStmt = $db->prepare("SELECT COUNT(*) as total
                           FROM reservas r
                           LEFT JOIN salas s ON r.sala = s.id
                           LEFT JOIN cache c ON r.requisitor = c.id
                           LEFT JOIN tempos t ON r.tempo = t.id
                           WHERE {$whereSql}");
if ($types !== '') {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$total = intval($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$query = "SELECT r.sala, r.tempo, r.data, r.motivo, r.requisitor, s.nome as sala_nome, c.nome as requisitor_nome, t.horashumanos as tempo_nome
          FROM reservas r
          LEFT JOIN salas s ON r.sala = s.id
          LEFT JOIN cache c ON r.requisitor = c.id
          LEFT JOIN tempos t ON r.tempo = t.id
          WHERE {$whereSql}
          ORDER BY r.data ASC, r.tempo ASC, r.sala ASC
          LIMIT ? OFFSET ?";
$stmt = $db->prepare($query);
$listParams = array_merge($params, [$limit, $offset]);
$listTypes = $types . 'ii';
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$result = $stmt->get_result();

$pedidos = [];
while ($row = $result->fetch_assoc()) {
    $pedidos[] = [
        'sala' => $row['sala'],
        'tempo' => $row['tempo'],
        'data' => $row['data'],
        'motivo' => $row['motivo'] ?? '',
        'sala_nome' => $row['sala_nome'] ?? 'N/A',
        'requisitor_nome' => $row['requisitor_nome'] ?? 'N/A',
        'tempo_nome' => $row['tempo_nome'] ?? 'N/A',
        'data_formatada' => date('d/m/Y', strtotime($row['data'])),
        'is_today' => $row['data'] === date('Y-m-d'),
        'is_past' => strtotime($row['data']) < strtotime(date('Y-m-d')),
    ];
}
$stmt->close();

$title = 'Todos os Pedidos Pendentes';
if ($requisitor !== '') {
    $nameStmt = $db->prepare('SELECT nome FROM cache WHERE id = ?');
    $nameStmt->bind_param('s', $requisitor);
    $nameStmt->execute();
    $name = $nameStmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
    $nameStmt->close();
    $title = 'Pedidos Pendentes - ' . $name;
} elseif ($sala !== '' && $sala !== '0') {
    $nameStmt = $db->prepare('SELECT nome FROM salas WHERE id = ?');
    $nameStmt->bind_param('s', $sala);
    $nameStmt->execute();
    $name = $nameStmt->get_result()->fetch_assoc()['nome'] ?? 'Sala';
    $nameStmt->close();
    $title = 'Pedidos Pendentes - ' . $name;
}

$stats = [
    'pendentes' => intval($db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 0")->fetch_assoc()['total'] ?? 0),
    'aprovadas' => intval($db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 1")->fetch_assoc()['total'] ?? 0),
    'hoje' => intval($db->query("SELECT COUNT(*) as total FROM reservas WHERE aprovado = 0 AND data = CURDATE()")->fetch_assoc()['total'] ?? 0),
];

echo json_encode([
    'pedidos' => $pedidos,
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
    'hasMore' => ($offset + count($pedidos)) < $total,
    'title' => $title,
    'stats' => $stats,
]);

$db->close();
?>
