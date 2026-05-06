<?php 
require '../index.php';
require_once(__DIR__ . '/../../func/email_helper.php');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
?>
<script src="https://cdn.jsdelivr.net/npm/@twemoji/api@latest/dist/twemoji.min.js" crossorigin="anonymous"></script>
<script>
    // Initialize Twemoji to parse all emojis on the page
    document.addEventListener('DOMContentLoaded', function() {
        twemoji.parse(document.body, {
            folder: 'svg',
            ext: '.svg'
        });
    });
</script>
<style>
    img.emoji {
        height: 1em;
        width: 1em;
        margin: 0 .05em 0 .1em;
        vertical-align: -0.1em;
    }
</style>
<div style="margin-left: 20%; margin-right: 20%; text-align: center;">
<h1>Notificar por Email</h1>
<p>Este script permite enviar um email para utilizadores com reservas de sala numa semana específica.</p>
<p>Pode filtrar por sala específica ou enviar para todos os utilizadores com reservas.</p>
<p>O email será enviado em BCC (cópia oculta) para preservar a privacidade dos destinatários.</p>

<style>
    body {
        overflow-y: auto !important;
    }
    
    .preview-box {
        border: 2px solid #0d6efd;
        border-radius: 8px;
        padding: 20px;
        margin: 20px 0;
        background-color: #f8f9fa;
        text-align: left;
    }
    
    .recipient-list {
        max-height: 200px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 1rem;
        background-color: #ffffff;
        text-align: left;
    }
    
    .recipient-item {
        padding: 0.5rem;
        margin-bottom: 0.5rem;
        border: 1px solid #e0e0e0;
        border-radius: 0.25rem;
        background-color: #f8f9fa;
    }
    
    .week-selector-btn {
        cursor: pointer;
        width: 100%;
    }
    
    .week-calendar {
        position: absolute;
        z-index: 1000;
        background: white;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        padding: 1rem;
        margin-top: 0.25rem;
        min-width: 320px;
    }
    
    .week-calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }
    
    .week-calendar-nav {
        background: none;
        border: none;
        font-size: 1.2rem;
        cursor: pointer;
        padding: 0.25rem 0.5rem;
        color: #0d6efd;
    }
    
    .week-calendar-nav:hover {
        background-color: #e7f1ff;
        border-radius: 0.25rem;
    }
    
    .week-calendar-body {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.25rem;
    }
    
    .week-calendar-day-header {
        text-align: center;
        font-weight: bold;
        font-size: 0.875rem;
        padding: 0.5rem;
        color: #6c757d;
    }
    
    .week-calendar-day {
        text-align: center;
        padding: 0.5rem;
        cursor: pointer;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }
    
    .week-calendar-day:hover {
        background-color: #e7f1ff;
    }
    
    .week-calendar-day.selected-week {
        background-color: #0d6efd;
        color: white;
    }
    
    .week-calendar-day.other-month {
        color: #adb5bd;
    }
    
    .week-calendar-day.today {
        font-weight: bold;
        border: 2px solid #0d6efd;
    }
    
    @media (prefers-color-scheme: dark) {
        .preview-box {
            background-color: #343a40;
            border-color: #0d6efd;
        }
        
        .recipient-list {
            background-color: #212529;
        }
        
        .recipient-item {
            background-color: #343a40;
            border-color: #495057;
            color: #f8f9fa;
        }
        
        .week-calendar {
            background: #212529;
            border-color: #495057;
        }
        
        .week-calendar-day:hover {
            background-color: #343a40;
        }
        
        .week-calendar-nav:hover {
            background-color: #343a40;
        }
    }
    
    .email-preview {
        background-color: #ffffff;
        border-radius: 8px;
        padding: 20px;
        margin-top: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    @media (prefers-color-scheme: dark) {
        .email-preview {
            background-color: #212529;
            color: #f8f9fa;
        }
    }
</style>

<script>
    let weekCalendarVisible = false;
    let currentCalendarDate = new Date();
    let selectedWeekStart = null;
    
    function getWeekNumber(date) {
        const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
        const dayNum = d.getUTCDay() || 7;
        d.setUTCDate(d.getUTCDate() + 4 - dayNum);
        const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
        return Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
    }
    
    function getMonday(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(d.setDate(diff));
    }
    
    function formatDateForDisplay(date) {
        return date.toLocaleDateString('pt-PT', { day: '2-digit', month: '2-digit', year: 'numeric' });
    }
    
    function formatWeekForForm(date) {
        const year = date.getFullYear();
        const week = getWeekNumber(date);
        return year + '-W' + String(week).padStart(2, '0');
    }
    
    function toggleWeekCalendar() {
        weekCalendarVisible = !weekCalendarVisible;
        const calendar = document.getElementById('weekCalendar');
        if (weekCalendarVisible) {
            renderWeekCalendar();
            calendar.style.display = 'block';
        } else {
            calendar.style.display = 'none';
        }
    }
    
    function renderWeekCalendar() {
        const calendar = document.getElementById('weekCalendarBody');
        const monthYear = document.getElementById('calendarMonthYear');
        
        const year = currentCalendarDate.getFullYear();
        const month = currentCalendarDate.getMonth();
        
        monthYear.textContent = currentCalendarDate.toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDay = firstDay.getDay() || 7;
        
        let html = '';
        
        // Day headers
        const dayHeaders = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'];
        dayHeaders.forEach(day => {
            html += `<div class="week-calendar-day-header">${day}</div>`;
        });
        
        // Previous month days
        const prevMonthLastDay = new Date(year, month, 0).getDate();
        for (let i = startDay - 2; i >= 0; i--) {
            const day = prevMonthLastDay - i;
            html += `<div class="week-calendar-day other-month">${day}</div>`;
        }
        
        // Current month days
        const today = new Date();
        for (let day = 1; day <= lastDay.getDate(); day++) {
            const date = new Date(year, month, day);
            const monday = getMonday(date);
            const isToday = date.toDateString() === today.toDateString();
            const isSelectedWeek = selectedWeekStart && monday.toDateString() === selectedWeekStart.toDateString();
            
            let classes = 'week-calendar-day';
            if (isToday) classes += ' today';
            if (isSelectedWeek) classes += ' selected-week';
            
            html += `<div class="${classes}" onclick="selectWeek(new Date(${year}, ${month}, ${day}))">${day}</div>`;
        }
        
        // Next month days
        const remainingDays = 42 - (startDay - 1 + lastDay.getDate());
        for (let day = 1; day <= remainingDays; day++) {
            html += `<div class="week-calendar-day other-month">${day}</div>`;
        }
        
        calendar.innerHTML = html;
    }
    
    function changeMonth(delta) {
        currentCalendarDate.setMonth(currentCalendarDate.getMonth() + delta);
        renderWeekCalendar();
    }
    
    function selectWeek(date) {
        selectedWeekStart = getMonday(date);
        const sunday = new Date(selectedWeekStart);
        sunday.setDate(sunday.getDate() + 6);
        
        const displayText = `Semana: ${formatDateForDisplay(selectedWeekStart)} - ${formatDateForDisplay(sunday)}`;
        document.getElementById('weekSelectorBtn').textContent = displayText;
        document.getElementById('weekInput').value = formatWeekForForm(selectedWeekStart);
        
        renderWeekCalendar();
        toggleWeekCalendar();
        
        // Do not auto-submit; wait for user to press submit
    }
    
    function initWeekSelector() {
        const weekInput = document.getElementById('weekInput');
        if (weekInput.value) {
            const parts = weekInput.value.split('-W');
            const year = parseInt(parts[0]);
            const week = parseInt(parts[1]);
            const jan1 = new Date(year, 0, 1);
            const daysOffset = (week - 1) * 7;
            const targetDate = new Date(jan1.setDate(jan1.getDate() + daysOffset));
            selectedWeekStart = getMonday(targetDate);
            currentCalendarDate = new Date(selectedWeekStart);
            
            const sunday = new Date(selectedWeekStart);
            sunday.setDate(sunday.getDate() + 6);
            document.getElementById('weekSelectorBtn').textContent = `Semana: ${formatDateForDisplay(selectedWeekStart)} - ${formatDateForDisplay(sunday)}`;
        } else {
            // No week selected by default
            selectedWeekStart = null;
            currentCalendarDate = new Date();
            document.getElementById('weekSelectorBtn').textContent = 'Selecionar Semana';
        }
    }

    function clearWeekSelection() {
        const weekInput = document.getElementById('weekInput');
        weekInput.value = '';
        selectedWeekStart = null;
        document.getElementById('weekSelectorBtn').textContent = 'Selecionar Semana';
        // Do not auto-submit; wait for user to press submit
    }
    
    // Close calendar when clicking outside
    document.addEventListener('click', function(event) {
        const calendar = document.getElementById('weekCalendar');
        const btn = document.getElementById('weekSelectorBtn');
        if (weekCalendarVisible && calendar && btn && !calendar.contains(event.target) && !btn.contains(event.target)) {
            toggleWeekCalendar();
        }
    });
    
    async function showPreview() {
        const subject = document.getElementById('subject').value;
        const message = document.getElementById('message').value;
        const identifySender = document.getElementById('identify_sender').checked;
        const senderName = document.getElementById('senderName').value;
        const mode = document.getElementById('email_mode').value;
        const week = document.getElementById('weekInput').value;
        const classroom = document.getElementById('classroom') ? document.getElementById('classroom').value : '';
        
        if (!subject || !message) {
            alert('Por favor, preencha o assunto e a mensagem antes de visualizar.');
            return;
        }
        if (!mode) {
            alert('Por favor, selecione o tipo de destinatários.');
            return;
        }
        
        const btn = document.querySelector('button[onclick="showPreview()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'A carregar...';
        btn.disabled = true;

        try {
            const url = `../api/recipients_preview.php?email_mode=${encodeURIComponent(mode)}&week=${encodeURIComponent(week)}&classroom=${encodeURIComponent(classroom)}`;
            const response = await fetch(url);

            if (!response.ok) throw new Error('Erro na resposta da API');
            
            const data = await response.json();
            
            // Build preview HTML
            let previewHTML = '<div class="preview-box">';
            previewHTML += '<h4>Pré-visualização do Email</h4>';
            previewHTML += '<hr>';
            
            previewHTML += '<div class="mb-3">';
            if (data.count === 0) {
                previewHTML += '<strong>Destinatários (BCC):</strong> 0 utilizador(es) (Nenhum destinatário encontrado com os filtros atuais)';
            } else {
                previewHTML += '<strong>Destinatários (BCC):</strong> ' + data.count + ' utilizador(es)';
                
                if (data.recipients && data.recipients.length > 0) {
                    previewHTML += '<ul class="list-group mt-2" style="max-height: 150px; overflow-y: auto; font-size: 0.9em;">';
                    data.recipients.forEach(recipient => {
                        previewHTML += '<li class="list-group-item py-1">' + escapeHtml(recipient) + '</li>';
                    });
                    previewHTML += '</ul>';
                }
            }
            previewHTML += '</div>';
            previewHTML += '<hr>';
            
            // Email preview
            previewHTML += '<div class="email-preview">';
            previewHTML += '<p><strong>Assunto:</strong> ' + escapeHtml(subject) + '</p>';
            previewHTML += '<hr>';
            previewHTML += '<div style="white-space: pre-wrap;">' + escapeHtml(message) + '</div>';
            
            if (identifySender && senderName) {
                previewHTML += '<hr>';
                previewHTML += '<p><em>Enviado por: ' + escapeHtml(senderName) + '</em></p>';
            }
            previewHTML += '</div>';
            
            previewHTML += '</div>';
            
            document.getElementById('previewContainer').innerHTML = previewHTML;
            document.getElementById('previewContainer').style.display = 'block';

        } catch (error) {
            console.error('Erro:', error);
            alert('Não foi possível obter a lista de destinatários. Por favor, tente novamente.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function validateForm(event) {
        const subject = document.getElementById('subject').value;
        const message = document.getElementById('message').value;
        
        if (!subject || !message) {
            event.preventDefault();
            alert('Por favor, preencha o assunto e a mensagem.');
            return false;
        }
        
        const confirmed = confirm('Tem a certeza que deseja enviar este email para todos os destinatários?');
        if (!confirmed) {
            event.preventDefault();
            return false;
        }
        
        return true;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', validateForm);
        }
    });
</script>

<?php
// Get all classrooms for the filter
$salasQuery = "SELECT id, nome FROM salas ORDER BY nome ASC";
$salasResult = $db->query($salasQuery);
$salas = [];
while ($sala = $salasResult->fetch_assoc()) {
    $salas[] = $sala;
}

// Determine the week to check (optional)
$selectedWeek = isset($_POST['week']) ? trim($_POST['week']) : '';
$selectedClassroom = isset($_POST['classroom']) ? $_POST['classroom'] : '';
$emailMode = isset($_POST['email_mode']) ? $_POST['email_mode'] : ''; // no default mode in backend

?>

<form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" class="mt-4" id="filterForm">
    <input type="hidden" id="senderName" value="<?php echo htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="weekInput" name="week" value="<?php echo htmlspecialchars($selectedWeek, ENT_QUOTES, 'UTF-8'); ?>">
    
    <div class="row mb-3">
        <div class="col-md-12">
            <div class="form-floating">
                <select class="form-select" id="email_mode" name="email_mode" onchange="toggleEmailMode()" required>
                    <option value="" disabled <?php echo empty($emailMode) ? 'selected' : ''; ?>>Selecione o modo...</option>
                    <option value="reservations" <?php echo ($emailMode === 'reservations') ? 'selected' : ''; ?>>Email a reservadores</option>
                    <option value="admins" <?php echo ($emailMode === 'admins') ? 'selected' : ''; ?>>Email a administradores</option>
                </select>
                <label for="email_mode">Tipo de Destinatários</label>
            </div>
        </div>
    </div>

    <!-- Filters Section (Only for reservations mode) -->
    <div id="reservationsFilters" style="display: none;">
        <div class="row mb-3">
            <div class="col-md-6">
                <label class="form-label">Selecionar Semana</label>
                <div style="position: relative;">
                    <button type="button" class="btn btn-outline-secondary week-selector-btn" id="weekSelectorBtn" onclick="toggleWeekCalendar()">
                        Selecionar Semana
                    </button>
                    <div id="weekCalendar" class="week-calendar" style="display: none;">
                        <div class="week-calendar-header">
                            <button type="button" class="week-calendar-nav" onclick="changeMonth(-1)">◀</button>
                            <strong id="calendarMonthYear"></strong>
                            <div>
                                <button type="button" class="week-calendar-nav" onclick="changeMonth(1)">▶</button>
                                <button type="button" class="week-calendar-nav" onclick="clearWeekSelection()">Limpar</button>
                            </div>
                        </div>
                        <div class="week-calendar-body" id="weekCalendarBody"></div>
                    </div>
                </div>
                <small class="text-muted">Clique para abrir o calendário e selecionar uma semana.</small>
            </div>
            <div class="col-md-6">
                <div class="form-floating mb-2">
                    <select class="form-select" id="classroom" name="classroom">
                        <option value="">Todas as Salas</option>
                        <?php foreach ($salas as $sala): ?>
                            <option value="<?php echo htmlspecialchars($sala['id'], ENT_QUOTES, 'UTF-8'); ?>" 
                                    <?php echo ($selectedClassroom == $sala['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sala['nome'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label for="classroom">Filtrar por Sala</label>
                </div>
                <small class="text-muted">Deixe em branco para enviar notificações de todas as salas.</small>
            </div>
        </div>
    </div>

    <!-- Common Email Composer Section -->
    <div id="emailComposer" style="display: none;">
        
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="form-floating">
                    <input type="text" class="form-control" id="subject" name="subject" placeholder="Assunto" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                    <label for="subject">Assunto do Email</label>
                </div>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-12">
                <div class="form-floating">
                    <textarea class="form-control" id="message" name="message" placeholder="Mensagem" style="height: 200px;" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
                    <label for="message">Mensagem do Email</label>
                </div>
            </div>
        </div>
        
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="identify_sender" name="identify_sender" value="1" <?php echo (isset($_POST['identify_sender']) && $_POST['identify_sender'] == '1') ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="identify_sender">
                        Identificar-me como remetente (o seu nome será adicionado no final da mensagem)
                    </label>
                </div>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="button" class="btn btn-info btn-lg" onclick="showPreview()">
                Pré-visualizar Email
            </button>
            <button type="submit" class="btn btn-primary btn-lg">
                Enviar Email
            </button>
        </div>
    </div>
</form>

<script>
    function toggleEmailMode() {
        const mode = document.getElementById('email_mode').value;
        const filtersSection = document.getElementById('reservationsFilters');
        const composerSection = document.getElementById('emailComposer');

        if (mode === 'reservations') {
            filtersSection.style.display = 'block';
            composerSection.style.display = 'block';
        } else if (mode === 'admins') {
            filtersSection.style.display = 'none';
            composerSection.style.display = 'block';
        } else {
            filtersSection.style.display = 'none';
            composerSection.style.display = 'none';
        }
    }
    
    // Initialize state on load
    document.addEventListener('DOMContentLoaded', function() {
        initWeekSelector();
        toggleEmailMode();
    });
</script>

<div id="previewContainer" style="display: none; margin-top: 30px;"></div>

<?php
// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subject']) && isset($_POST['message'])) {
    $subject = $_POST['subject'];
    $messageBody = $_POST['message'];
    $identifySender = isset($_POST['identify_sender']) && $_POST['identify_sender'] == '1';
    
    // Determine the week to check (optional)
    $selectedWeek = isset($_POST['week']) ? trim($_POST['week']) : '';
    $selectedClassroom = isset($_POST['classroom']) ? $_POST['classroom'] : '';
    $emailMode = isset($_POST['email_mode']) ? $_POST['email_mode'] : '';

    if (empty($subject) || empty($messageBody) || empty($emailMode)) {
        echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
            <strong>Erro:</strong> O modo, assunto e a mensagem são obrigatórios.
        </div>";
    } else {
        // Build DB query to get recipients
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
        if ($emailMode === 'admins') {
            // Admin-only emails: select from cache table regardless of reservations
            $query = "SELECT id, nome, email FROM cache WHERE admin = 1 ORDER BY nome ASC";
            $stmt = $db->prepare($query);
            if ($stmt) {
                $stmt->execute();
                $result = $stmt->get_result();
            }
        } else {
            // Users with reservations (apply optional date and classroom filters)
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

            $query = "SELECT DISTINCT c.id, c.nome, c.email 
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

        $recipients = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recipients[] = $row;
            }
            if (isset($stmt)) $stmt->close();
        }

        $recipientCount = count($recipients);

        if ($recipientCount == 0) {
            echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
                <strong>Erro:</strong> Não existem destinatários para enviar o email usando os filtros que selecionou.
            </div>";
        } else {
            // Build the email body
            $bodyContent = '<p>' . nl2br(htmlspecialchars($messageBody, ENT_QUOTES, 'UTF-8')) . '</p>';
        
        if ($identifySender) {
            $bodyContent .= '<hr>';
            $bodyContent .= '<p><em>Enviado por: ' . htmlspecialchars($_SESSION['nome'], ENT_QUOTES, 'UTF-8') . '</em></p>';
        }

        // Get classroom name for display and logging
        $classroomName = 'todas as salas';
        if (!empty($selectedClassroom)) {
            foreach ($salas as $sala) {
                if ($sala['id'] == $selectedClassroom) {
                    $classroomName = $sala['nome'];
                    break;
                }
            }
        }
        
        // Prepare week summary for logs
        $weekSummaryPlain = 'todas as datas';
        if (!empty($startOfWeek) && !empty($endOfWeek)) {
            $weekSummaryPlain = date('d/m/Y', strtotime($startOfWeek)) . ' - ' . date('d/m/Y', strtotime($endOfWeek));
        }
        
        // Send email to all recipients in BCC
        try {
            global $mail;
            
            // Check if email is enabled
            if ($mail['ativado'] != true) {
                echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
                    <strong>Erro:</strong> O sistema de email não está ativado.
                </div>";
            } else {
                $mailer = new PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host = $mail['servidor'];
                $mailer->SMTPAuth = $mail['autenticacao'];
                $mailer->Username = $mail['username'];
                $mailer->Password = $mail['password'];
                $mailer->SMTPSecure = $mail['tipodeseguranca'];
                $mailer->Port = $mail['porta'];
                $mailer->CharSet = 'UTF-8';
                $mailer->Encoding = 'base64';
                
                $mailer->setFrom($mail['mailfrom'], $mail['fromname']);
                
                if ($identifySender && !empty($_SESSION['email'])) {
                    $mailer->addReplyTo($_SESSION['email'], $_SESSION['nome']);
                }
                
                // Add all recipients as BCC
                foreach ($recipients as $recipient) {
                    $mailer->addBCC($recipient['email']);
                }
                
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                
                // Build footer sentence depending on mode and week selection
                $footerSentence = "";
                $plainWeekSentence = "";
                if (isset($emailMode) && $emailMode === 'admins') {
                    $footerSentence = "Recebeu este email porque é administrador do sistema ClassLink.";
                    $plainWeekSentence = "Recebeu este email porque é administrador do sistema ClassLink.";
                } else {
                    if (!empty($startOfWeek) && !empty($endOfWeek)) {
                        $footerSentence = "Recebeu este email porque tem uma reserva para a semana de <strong>" . date('d/m/Y', strtotime($startOfWeek)) . " - " . date('d/m/Y', strtotime($endOfWeek)) . "</strong>" . (!empty($selectedClassroom) ? " na sala <strong>" . htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') . "</strong>" : "") . ".";
                        $plainWeekSentence = "Recebeu este email porque tem uma reserva para a semana de " . date('d/m/Y', strtotime($startOfWeek)) . " - " . date('d/m/Y', strtotime($endOfWeek)) . (!empty($selectedClassroom) ? " na sala " . $classroomName : "") . ".";
                    } else {
                        $footerSentence = "Recebeu este email porque tem uma reserva (todas as datas)" . (!empty($selectedClassroom) ? " na sala <strong>" . htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') . "</strong>" : "") . ".";
                        $plainWeekSentence = "Recebeu este email porque tem uma reserva (todas as datas)" . (!empty($selectedClassroom) ? " na sala " . $classroomName : "") . ".";
                    }
                }

                // Build full HTML email
                $htmlBody = "
<!DOCTYPE html>
<html lang='pt'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>" . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, Helvetica, sans-serif;'>
    <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td align='center' style='padding: 40px 20px;'>
                <table role='presentation' cellpadding='0' cellspacing='0' border='0' width='600' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);'>
                    
                    <tr>
                        <td style='background-color: #0d6efd; padding: 30px 40px; text-align: center;'>
                            <h1 style='margin: 0; color: #ffffff; font-size: 24px; font-weight: bold;'>ClassLink - Notificação</h1>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 40px; color: #333333; font-size: 16px; line-height: 1.6;'>
                            {$bodyContent}
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #f8f9fa; padding: 25px 40px; text-align: center; border-top: 1px solid #e9ecef;'>
                            <p style='margin: 0 0 10px 0; color: #6c757d; font-size: 14px;'>
                                " . $footerSentence . "
                            </p>
                            <p style='margin: 0 0 10px 0; color: #6c757d; font-size: 14px;'>
                                Este email foi enviado automaticamente pelo sistema ClassLink. Não responda a este email.
                            </p>
                            <p style='margin: 0; color: #6c757d; font-size: 12px;'>
                                Agrupamento de Escolas Joaquim Inácio da Cruz Sobral
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>";
                
                $mailer->Body = $htmlBody;

                // Plain text alternative
                $plainBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $bodyContent));
                $plainBody .= "\n\n---\n" . $plainWeekSentence . "\nEste email foi enviado automaticamente pelo sistema ClassLink. Não responda a este email.\nAgrupamento de Escolas Joaquim Inácio da Cruz Sobral";
                $mailer->AltBody = $plainBody;
                
                $mailer->send();
                
                // Log the action
                require_once(__DIR__ . '/../../func/logaction.php');
                if (isset($emailMode) && $emailMode === 'admins') {
                    $logMessage = "Email enviado para {$recipientCount} administrador(es)";
                } else {
                    $logMessage = "Email enviado para {$recipientCount} utilizadores com reservas: {$weekSummaryPlain}";
                }
                if (!empty($selectedClassroom)) {
                    $logMessage .= " (Sala: {$classroomName})";
                }
                $logMessage .= ". Assunto: {$subject}";
                logaction($logMessage, $_SESSION['id']);
                
                echo "<div class='mt-3 alert alert-success fade show' role='alert'>
                    <strong>Sucesso!</strong> Email enviado com sucesso para {$recipientCount} destinatário(s) em BCC.
                </div>";
                
                echo "<div class='mt-3 alert alert-info fade show' role='alert'>
                    <strong>Resumo:</strong><br>
                    - Assunto: " . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "<br>
                    - Destinatários: {$recipientCount}<br>";
                if (isset($emailMode) && $emailMode === 'admins') {
                    echo "                    - Tipo: Administradores<br>";
                } else {
                    echo "                    - Semana: " . htmlspecialchars($weekSummaryPlain, ENT_QUOTES, 'UTF-8') . "<br>";
                }
                echo (!empty($selectedClassroom) ? "                    - Sala: " . htmlspecialchars($classroomName, ENT_QUOTES, 'UTF-8') . "<br>" : "")
                    . "                    - Remetente identificado: " . ($identifySender ? 'Sim' : 'Não') . "
                </div>";
            }
        } catch (Exception $e) {
            echo "<div class='mt-3 alert alert-danger fade show' role='alert'>
                <strong>Erro ao enviar email:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "
            </div>";
        }
        }
    }
}
?>
</div>
