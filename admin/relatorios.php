<?php
require_once(__DIR__ . '/../func/logaction.php');
require_once(__DIR__ . '/../func/csrf.php');
require_once(__DIR__ . '/../src/db.php');
require_once(__DIR__ . '/../vendor/autoload.php');

// Handle PDF generation
if (isset($_POST['gerar_pdf'])) {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die("<div class='alert alert-danger text-center'>Pedido inválido. Atualize a página e tente novamente.</div>");
    }
    // Guard: redirect pending TOTP/setup flows to completion
    if (isset($_SESSION['pending_totp_user'])) { header('Location: /login?step=totp'); exit(); }
    if (isset($_SESSION['pending_user_setup'])) { header('Location: /login?step=setup'); exit(); }
    if (!$_SESSION['admin']) {
        http_response_code(403);
        die("<div class='alert alert-danger text-center'>Não tem permissão para entrar no painel administrativo. <a href='/'>Voltar para a página inicial</a></div>");
    }
    if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
        http_response_code(403);
        header("Location: /login");
        die("A reencaminhar para iniciar sessão...");
    } else {
        if ($_SESSION['validity'] - time() < 900) {
            $_SESSION['validity'] = time() + 1800;
        }
    }

    $data_selecionada = $_POST['data_relatorio'] ?? date('Y-m-d');
    $sala_id = $_POST['sala_id'] ?? null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_selecionada)) {
        $data_selecionada = date('Y-m-d');
    }

    if ($sala_id && $sala_id !== 'todas') {
        $stmt = $db->prepare("\n            SELECT \n                s.nome as sala_nome,\n                t.horashumanos,\n                c.nome as requisitor_nome,\n                r.aprovado,\n                r.motivo\n            FROM salas s\n            CROSS JOIN tempos t\n            LEFT JOIN reservas r ON r.sala = s.id AND r.tempo = t.id AND r.data = ?\n            LEFT JOIN cache c ON r.requisitor = c.id\n            WHERE s.id = ?\n            ORDER BY t.horashumanos ASC\n        ");
        $stmt->bind_param("ss", $data_selecionada, $sala_id);
    } else {
        $stmt = $db->prepare("\n            SELECT \n                s.nome as sala_nome,\n                t.horashumanos,\n                c.nome as requisitor_nome,\n                r.aprovado,\n                r.motivo\n            FROM salas s\n            CROSS JOIN tempos t\n            LEFT JOIN reservas r ON r.sala = s.id AND r.tempo = t.id AND r.data = ?\n            LEFT JOIN cache c ON r.requisitor = c.id\n            ORDER BY s.nome ASC, t.horashumanos ASC\n        ");
        $stmt->bind_param("s", $data_selecionada);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $salas_data = [];
    while ($row = $result->fetch_assoc()) {
        $sala_nome = $row['sala_nome'];
        if (!isset($salas_data[$sala_nome])) { $salas_data[$sala_nome] = []; }
        $salas_data[$sala_nome][] = [
            'tempo' => $row['horashumanos'],
            'requisitor' => $row['requisitor_nome'],
            'status' => $row['aprovado'],
            'motivo' => $row['motivo']
        ];
    }
    $stmt->close();

    ob_start();

    class PDF extends TCPDF {
        private $print_datetime;
        public function setPrintDateTime($datetime) { $this->print_datetime = $datetime; }
        public function Header() {
            $logo_path = __DIR__ . '/../assets/logo.png';
            if (file_exists($logo_path)) {
                $this->Image($logo_path, 15, 10, 15, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            $this->SetFont('helvetica', 'B', 18);
            $this->SetTextColor(0, 0, 0);
            $this->SetY(10);
            $this->Cell(0, 15, 'ClassLink', 0, 1, 'C');
            $this->SetLineStyle(array('width' => 0.5, 'color' => array(200, 200, 200)));
            $this->Line(15, 28, $this->getPageWidth() - 15, 28);
        }
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 9);
            $this->SetTextColor(128, 128, 128);
            $this->Cell(0, 10, 'Impresso em: ' . $this->print_datetime, 0, 0, 'R');
        }
    }

    $pdf = new PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $data_hora_impressao = date('d/m/Y') . ' às ' . date('H:i:s');
    $pdf->setPrintDateTime($data_hora_impressao);
    $pdf->SetCreator('ClassLink');
    $pdf->SetAuthor('Sistema de Reserva de Salas');
    $pdf->SetTitle('Relatório de Utilização de Salas - ' . date('d/m/Y', strtotime($data_selecionada)));
    $pdf->SetSubject('Relatório Diário');
    $pdf->SetMargins(15, 32, 15);
    $pdf->SetAutoPageBreak(TRUE, 20);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 8, 'Relatório de Utilização de Salas', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 11);
    $dia_semana_map = [
        'Monday' => 'Segunda-feira','Tuesday' => 'Terça-feira','Wednesday' => 'Quarta-feira',
        'Thursday' => 'Quinta-feira','Friday' => 'Sexta-feira','Saturday' => 'Sábado','Sunday' => 'Domingo'
    ];
    $dia_semana_en = date('l', strtotime($data_selecionada));
    $dia_semana = $dia_semana_map[$dia_semana_en] ?? $dia_semana_en;
    $pdf->Cell(0, 8, 'Data: ' . $dia_semana . ', ' . date('d/m/Y', strtotime($data_selecionada)), 0, 1, 'C');
    $pdf->Ln(5);

    foreach ($salas_data as $sala_nome => $tempos) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(0, 8, 'Sala: ' . $sala_nome, 0, 1, 'L', true);
        $html = '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $html .= '<thead style="background-color:#f0f0f0"><tr>';
        $html .= '<th style="width:25%;text-align:center">Horário</th>';
        $html .= '<th style="width:25%;text-align:center">Requisitor</th>';
        $html .= '<th style="width:25%;text-align:center">Status</th>';
        $html .= '<th style="width:25%;text-align:center">Motivo</th>';
        $html .= '</tr></thead><tbody>';
        $has_reservation = false;
        foreach ($tempos as $tempo) {
            if ($tempo['requisitor']) {
                $has_reservation = true;
                $status_label = '';
                $status_bg = '#FFFFFF';
                if ($tempo['status'] === null) { $status_label = 'Pendente'; $status_bg = '#FFFFC8'; }
                elseif ($tempo['status'] == 1) { $status_label = 'Aprovado'; $status_bg = '#C8FFC8'; }
                elseif ($tempo['status'] == 0) { $status_label = 'Rejeitado'; $status_bg = '#FFC8C8'; }
                else { $status_label = 'Cancelado'; $status_bg = '#E0E0E0'; }
                $horario = htmlspecialchars($tempo['tempo'] ?? '-', ENT_QUOTES, 'UTF-8');
                $requisitor_full = $tempo['requisitor'] ?? '';
                if ($requisitor_full && trim($requisitor_full) !== '') {
                    $requisitor_short = preg_replace('/^(\S+).*?(\S+)$/u', '$1 $2', $requisitor_full);
                    $requisitor = htmlspecialchars($requisitor_short, ENT_QUOTES, 'UTF-8');
                } else { $requisitor = '-'; }
                $motivo = htmlspecialchars($tempo['motivo'] ?? '-', ENT_QUOTES, 'UTF-8');
                $html .= '<tr>';
                $html .= '<td style="vertical-align:middle;text-align:center">' . $horario . '</td>';
                $html .= '<td style="vertical-align:middle;text-align:left">' . $requisitor . '</td>';
                $html .= '<td style="vertical-align:middle;text-align:center;background-color:' . $status_bg . '">' . $status_label . '</td>';
                $html .= '<td style="vertical-align:middle;text-align:left">' . $motivo . '</td>';
                $html .= '</tr>';
            }
        }
        if (!$has_reservation) { $html .= '<tr><td colspan="4" style="text-align:center">Sem reservas</td></tr>'; }
        $html .= '</tbody></table>';
        $pdf->SetFont('helvetica', '', 10);
        $pdf->writeHTML($html, true, false, true, false, '');
        $pdf->Ln(3);
    }

    $sala_log = ($sala_id && $sala_id !== 'todas') ? " (Sala específica)" : " (Todas as salas)";
    logaction('Geração de Relatório de Salas Diário para ' . $data_selecionada . $sala_log, $_SESSION['id']);
    ob_end_clean();
    $filename_base = 'Relatorio_Salas';
    if ($sala_id && $sala_id !== 'todas') {
        $keys = array_keys($salas_data);
        $sala_forfile = $keys[0] ?? 'sala';
        $sala_forfile = preg_replace('/[^A-Za-z0-9_-]/', '', str_replace(' ', '_', $sala_forfile));
        if ($sala_forfile) { $filename_base .= '_' . $sala_forfile; }
    }
    $filename = $filename_base . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}
require 'index.php';
?>
<div style="margin-left: 10%; margin-right: 10%; text-align: center;">
    <h3>Relatório de Utilização de Salas</h3>
    <p>Gerar um relatório em PDF da utilização de salas para um dia específico.</p>
    
    <form method="POST" style="max-width: 500px; margin: 20px auto;">
        <?= csrf_token_field(); ?>
        <div class="mb-3">
            <label for="data_relatorio" class="form-label">Selecione a Data</label>
            <input type="date" class="form-control" id="data_relatorio" name="data_relatorio" value="<?php echo date('Y-m-d'); ?>" required>
        </div>
        
        <div class="mb-3">
            <label for="sala_id" class="form-label">Selecione a Sala</label>
            <select class="form-select" id="sala_id" name="sala_id" required>
                <option value="todas" selected>Todas as Salas</option>
                <?php
                $salas = $db->query("SELECT id, nome FROM salas ORDER BY nome ASC");
                while ($sala = $salas->fetch_assoc()) {
                    echo "<option value='" . htmlspecialchars($sala['id'], ENT_QUOTES, 'UTF-8') . "'>" . 
                         htmlspecialchars($sala['nome'], ENT_QUOTES, 'UTF-8') . "</option>";
                }
                ?>
            </select>
        </div>
        
        <button type="submit" name="gerar_pdf" class="btn btn-primary">Gerar PDF</button>
    </form>
</div>
