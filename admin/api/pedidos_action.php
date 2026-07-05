<?php
require_once(__DIR__ . '/../../src/db.php');
require_once(__DIR__ . '/../../func/csrf.php');
require_once(__DIR__ . '/../../func/email_helper.php');
require_once(__DIR__ . '/../../func/logaction.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

header('Content-Type: application/json');

function fail_json($message, $status = 400) {
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if (!isset($_SESSION['admin']) || !$_SESSION['admin']) { fail_json('Acesso negado.', 403); }
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) { fail_json('Sessão expirada', 403); }
if (isset($_SESSION['pending_totp_user']) || isset($_SESSION['pending_user_setup'])) { fail_json('Autenticação incompleta', 403); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { fail_json('Método inválido.', 405); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) { fail_json('Pedido inválido.', 403); }

$action = $_POST['action'] ?? '';
$rawReservations = $_POST['reservations'] ?? '[]';
$reservations = json_decode($rawReservations, true);
if (!is_array($reservations)) { fail_json('Dados inválidos.'); }
if (($action === 'aprovar' || $action === 'rejeitar') && empty($reservations)) {
    $reservations[] = ['sala' => $_POST['sala'] ?? '', 'tempo' => $_POST['tempo'] ?? '', 'data' => $_POST['data'] ?? ''];
}
if (!in_array($action, ['aprovar', 'rejeitar'], true)) { fail_json('Ação inválida.'); }

$processed = 0;
$failed = 0;
$emailErrors = [];
$emailGroups = [];
$singleAction = count($reservations) === 1;

foreach ($reservations as $res) {
    if (empty($res['sala']) || empty($res['tempo']) || empty($res['data'])) {
        $failed++;
        continue;
    }

    $stmt = $db->prepare('SELECT r.requisitor, s.nome as sala_nome, t.horashumanos as tempo_nome, c.nome as requisitor_nome FROM reservas r LEFT JOIN salas s ON r.sala = s.id LEFT JOIN tempos t ON r.tempo = t.id LEFT JOIN cache c ON r.requisitor = c.id WHERE r.sala=? AND r.tempo=? AND r.data=? AND r.aprovado=0');
    $stmt->bind_param('sss', $res['sala'], $res['tempo'], $res['data']);
    $stmt->execute();
    $details = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$details || empty($details['requisitor'])) {
        $failed++;
        continue;
    }

    if ($action === 'aprovar') {
        $stmt = $db->prepare('UPDATE reservas SET aprovado=1 WHERE sala=? AND tempo=? AND data=? AND aprovado=0');
    } else {
        $stmt = $db->prepare('DELETE FROM reservas WHERE sala=? AND tempo=? AND data=? AND aprovado=0');
    }
    $stmt->bind_param('sss', $res['sala'], $res['tempo'], $res['data']);
    $stmt->execute();
    $ok = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$ok) {
        $failed++;
        continue;
    }

    $reqId = $details['requisitor'];
    if ($singleAction) {
        $emailResult = $action === 'aprovar'
            ? sendReservationApprovedEmail($db, $reqId, $res['sala'], $res['tempo'], $res['data'])
            : sendReservationRejectedEmail($db, $reqId, $res['sala'], $res['tempo'], $res['data']);
        if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
            $emailErrors[] = "Utilizador ID: {$reqId}";
        }
    } else {
        $emailGroups[$reqId][] = [
            'requisitor' => $reqId,
            'sala_nome' => $details['sala_nome'],
            'tempo_nome' => $details['tempo_nome'],
            'data' => $res['data'],
        ];
    }

    $verb = $action === 'aprovar' ? 'Aprovou' : 'Rejeitou';
    logaction("{$verb} a reserva do utilizador '{$details['requisitor_nome']}': sala '{$details['sala_nome']}' no dia {$res['data']} às {$details['tempo_nome']}", $_SESSION['id']);
    $processed++;
}

foreach ($emailGroups as $reqId => $items) {
    $emailResult = $action === 'aprovar'
        ? sendBulkReservationApprovedEmail($db, $items)
        : sendBulkReservationRejectedEmail($db, $items);
    if (!$emailResult['success'] && $emailResult['error'] !== 'Email not enabled') {
        $emailErrors[] = "Utilizador ID: {$reqId}";
    }
}

echo json_encode([
    'success' => true,
    'processed' => $processed,
    'failed' => $failed,
    'emailErrors' => $emailErrors,
    'message' => $action === 'aprovar' ? 'Reserva(s) aprovada(s).' : 'Reserva(s) rejeitada(s).',
]);

$db->close();
?>
