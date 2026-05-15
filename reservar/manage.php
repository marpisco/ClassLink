<?php
require_once(__DIR__ . '/../func/logaction.php');
require_once(__DIR__ . '/../func/email_helper.php');
require_once(__DIR__ . '/../src/db.php');
session_start();
if (!isset($_SESSION['validity']) || $_SESSION['validity'] < time()) {
    http_response_code(403);
    header("Location: /login");
    die("A reencaminhar para iniciar sessão...");
}

// Helper function to save selected materials for a reservation
function saveReservationMaterials($db, $sala, $tempo, $data, $materiais) {
    if (isset($materiais) && is_array($materiais)) {
        foreach ($materiais as $materialId) {
            $matStmt = $db->prepare("INSERT INTO reservas_materiais (reserva_sala, reserva_tempo, reserva_data, material_id) VALUES (?, ?, ?, ?)");
            $matStmt->bind_param("ssss", $sala, $tempo, $data, $materialId);
            $matStmt->execute();
            $matStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Tempo | ClassLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <link href="/assets/index.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/reservar.css">
    <link rel='icon' href='/assets/logo.png'>
    <script src="/assets/theme-switcher.js"></script>
    <script>
        // User selection modal functions for single reservations
        function filterUsers() {
            const searchInput = document.getElementById('userSearchInput');
            const filter = searchInput.value.toLowerCase();
            const userItems = document.querySelectorAll('.user-item');
            
            userItems.forEach(item => {
                const name = item.getAttribute('data-user-name').toLowerCase();
                const email = item.getAttribute('data-user-email').toLowerCase();
                if (name.includes(filter) || email.includes(filter)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        function selectUser(element) {
            const userId = element.getAttribute('data-user-id');
            const userName = element.getAttribute('data-user-name');
            const userEmail = element.getAttribute('data-user-email');
            
            document.getElementById('requisitor_id').value = userId;
            document.getElementById('selectedUserDisplay').value = userName + ' (' + userEmail + ')';
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('userSelectModal'));
            modal.hide();
        }
        
        function clearUserSelection() {
            document.getElementById('requisitor_id').value = '';
            document.getElementById('selectedUserDisplay').value = '';
            document.getElementById('selectedUserDisplay').placeholder = 'Reservar para mim mesmo';
        }
    </script>
</head>

<body>
    <?php require_once(__DIR__ . '/../func/navbar.php'); ?>
    <div class="container mt-5 mb-5">
        <?php
        $id = $_SESSION['id'];
        $today = date("Y-m-d");
        
        // Handle bulk reservation separately since it doesn't require tempo/data/sala in GET
        if (isset($_GET['subaction']) && $_GET['subaction'] === 'bulk') {
            if (!isset($_POST['motivo']) || empty($_POST['motivo'])) {
                echo "<div class='alert alert-danger show' role='alert'>Motivo é obrigatório.</div>";
                echo "<a href='" . htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, 'UTF-8') . "' class='btn btn-primary'>Voltar</a>";
            } elseif (!isset($_POST['slots']) || !is_array($_POST['slots']) || count($_POST['slots']) == 0) {
                echo "<div class='alert alert-danger show' role='alert'>Nenhum tempo foi selecionado.</div>";
                echo "<a href='" . htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, 'UTF-8') . "' class='btn btn-primary'>Voltar</a>";
            } else {
                $motivo = $_POST['motivo'];
                $extra = $_POST['extra'] ?? '';
                $successCount = 0;
                $failedSlots = [];
                $isAutonomous = false;
                $bulkRequisitor = $id; // Track the requisitor for email
                $lastSlotSala = null; // Track the last sala for email
                
                foreach ($_POST['slots'] as $slot) {
                    $parts = explode('|', $slot);
                    if (count($parts) !== 3) continue;
                    
                    $slotTempo = urldecode($parts[0]);
                    $slotSala = urldecode($parts[1]);
                    $slotData = urldecode($parts[2]);
                    
                    // Check if slot is still available
                    $checkStmt = $db->prepare("SELECT * FROM reservas WHERE sala=? AND tempo=? AND data=? AND aprovado!=-1");
                    $checkStmt->bind_param("sss", $slotSala, $slotTempo, $slotData);
                    $checkStmt->execute();
                    $existing = $checkStmt->get_result()->fetch_assoc();
                    $checkStmt->close();
                    
                    if (!$existing) {
                        // Check if room has autonomous reservation (tipo_sala = 2) and if it's locked
                        $salaStmt = $db->prepare("SELECT tipo_sala, bloqueado FROM salas WHERE id = ?");
                        $salaStmt->bind_param("s", $slotSala);
                        $salaStmt->execute();
                        $salaInfo = $salaStmt->get_result()->fetch_assoc();
                        $salaStmt->close();
                        
                        if (!$salaInfo) {
                            $failedSlots[] = htmlspecialchars($slotData, ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($slotTempo, ENT_QUOTES, 'UTF-8') . " (sala não encontrada)";
                            continue;
                        }
                        
                        // Check if room is locked and user is not admin
                        if ($salaInfo['bloqueado'] == 1 && !$_SESSION['admin']) {
                            $failedSlots[] = htmlspecialchars($slotData, ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($slotTempo, ENT_QUOTES, 'UTF-8') . " (sala bloqueada)";
                            continue;
                        }
                        
                        // Check if date is in the past and user is not admin
                        if ($slotData < $today && !$_SESSION['admin']) {
                            $failedSlots[] = htmlspecialchars($slotData, ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($slotTempo, ENT_QUOTES, 'UTF-8') . " (data no passado)";
                            continue;
                        }
                        
                        // Determine requisitor: for admins, use selected user or current user
                        $requisitor = $id;
                        if ($_SESSION['admin'] && isset($_POST['requisitor_id']) && !empty($_POST['requisitor_id'])) {
                            // Validate that the requisitor_id exists in the database
                            $userCheckStmt = $db->prepare("SELECT id FROM cache WHERE id = ?");
                            $userCheckStmt->bind_param("s", $_POST['requisitor_id']);
                            $userCheckStmt->execute();
                            $userExists = $userCheckStmt->get_result()->fetch_assoc();
                            $userCheckStmt->close();
                            if ($userExists) {
                                $requisitor = $_POST['requisitor_id'];
                            }
                        }
                        $bulkRequisitor = $requisitor; // Track for email
                        $lastSlotSala = $slotSala; // Track for email
                        
                        // Get the email of the requisitor to check if they're an internal user
                        $requisitorEmail = '';
                        if ($requisitor === $id) {
                            // Using current user's email
                            $requisitorEmail = $_SESSION['email'];
                        } else {
                            // Admin is reserving for another user, fetch their email
                            $emailStmt = $db->prepare("SELECT email FROM cache WHERE id = ?");
                            $emailStmt->bind_param("s", $requisitor);
                            $emailStmt->execute();
                            $emailResult = $emailStmt->get_result()->fetch_assoc();
                            $emailStmt->close();
                            $requisitorEmail = $emailResult['email'] ?? '';
                        }
                        
                        // Check if user has an internal @aejics.org email
                        $isInternalUser = str_ends_with(strtolower($requisitorEmail), '@aejics.org');
                        
                        // Auto-approve if tipo_sala is 2 (autonomous) AND user is internal, otherwise set to 0 (pending)
                        $aprovado = ($salaInfo['tipo_sala'] == 2 && $isInternalUser) ? 1 : 0;
                        if ($aprovado == 1) {
                            $isAutonomous = true;
                        }
                        
                        $stmt = $db->prepare("INSERT INTO reservas (sala, tempo, requisitor, data, aprovado, motivo, extra) VALUES (?, ?, ?, ?, ?, ?, ?);");
                        $stmt->bind_param("sssssss", $slotSala, $slotTempo, $requisitor, $slotData, $aprovado, $motivo, $extra);
                        if ($stmt->execute()) {
                            $successCount++;
                            
                            // Save selected materials if any
                            saveReservationMaterials($db, $slotSala, $slotTempo, $slotData, $_POST['materiais'] ?? null);
                            
                            // Log reservation creation
                            $salaStmt = $db->prepare("SELECT nome FROM salas WHERE id = ?");
                            $salaStmt->bind_param("s", $slotSala);
                            $salaStmt->execute();
                            $salaNome = $salaStmt->get_result()->fetch_assoc()['nome'] ?? $slotSala;
                            $salaStmt->close();
                            
                            $tempoStmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id = ?");
                            $tempoStmt->bind_param("s", $slotTempo);
                            $tempoStmt->execute();
                            $tempoNome = $tempoStmt->get_result()->fetch_assoc()['horashumanos'] ?? $slotTempo;
                            $tempoStmt->close();
                            
                            if ($_SESSION['admin'] && $requisitor != $id) {
                                // Admin creating reservation for another user
                                $userStmt = $db->prepare("SELECT nome FROM cache WHERE id = ?");
                                $userStmt->bind_param("s", $requisitor);
                                $userStmt->execute();
                                $userName = $userStmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
                                $userStmt->close();
                                logaction("Criou uma reserva para o utilizador '{$userName}': sala '{$salaNome}' no dia {$slotData} às {$tempoNome}", $_SESSION['id']);
                            } else {
                                // User creating their own reservation
                                logaction("Criou uma reserva: sala '{$salaNome}' no dia {$slotData} às {$tempoNome}", $requisitor);
                            }
                        } else {
                            $failedSlots[] = htmlspecialchars($slotData, ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($slotTempo, ENT_QUOTES, 'UTF-8');
                        }
                        $stmt->close();
                    } else {
                        $failedSlots[] = htmlspecialchars($slotData, ENT_QUOTES, 'UTF-8') . " - " . htmlspecialchars($slotTempo, ENT_QUOTES, 'UTF-8') . " (já reservado)";
                    }
                }
                
                // Send bulk reservation email if any successful
                if ($successCount > 0) {
                    sendBulkReservationsEmail($db, $bulkRequisitor, $successCount, count($failedSlots), $lastSlotSala, $isAutonomous);
                }
                
                echo "<div class='row justify-content-center'>";
                echo "<div class='col-md-10 col-lg-8'>";
                
                if ($isAutonomous) {
                    echo "<div class='alert alert-success'><h4 class='alert-heading'>Reservas Aprovadas!</h4><p class='mb-0'>{$successCount} reserva(s) criada(s) com sucesso e aprovadas automaticamente.</p></div>";
                } else {
                    echo "<div class='alert alert-success'><h4 class='alert-heading'>Reservas Submetidas!</h4><p class='mb-0'>{$successCount} reserva(s) criada(s) com sucesso e submetidas para aprovação.</p></div>";
                }
                if (count($failedSlots) > 0) {
                    echo "<div class='alert alert-warning'><strong>Algumas reservas falharam:</strong><br>" . implode('<br>', $failedSlots) . "</div>";
                }
                
                // Get the room info and post-reservation content for the first successful reservation
                if ($successCount > 0 && isset($slotSala)) {
                    $stmt = $db->prepare("SELECT nome, post_reservation_content FROM salas WHERE id=?");
                    $stmt->bind_param("s", $slotSala);
                    $stmt->execute();
                    $salaData = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    // Display post-reservation content if available
                    if (!empty($salaData['post_reservation_content'])) {
                        echo "<div class='card mb-3'>";
                        echo "<div class='card-body'>";
                        echo "<h5 class='card-title'>Informações Importantes - " . htmlspecialchars($salaData['nome'], ENT_QUOTES, 'UTF-8') . "</h5>";
                        echo "<div class='post-reservation-content'>";
                        echo $salaData['post_reservation_content']; // Content is already HTML from CKEditor
                        echo "</div>";
                        echo "</div>";
                        echo "</div>";
                    }
                }
                
                echo "<div class='d-grid gap-2 d-md-block'>";
                echo "<a href='/reservar' class='btn btn-success me-md-2 mb-2 mb-md-0'>Voltar à página de reserva de salas</a> ";
                echo "<a href='/reservas' class='btn btn-primary'>Ver as minhas reservas</a>";
                echo "</div>";
                
                echo "</div></div>";
            }
        } elseif (isset($_GET['tempo']) && isset($_GET['data']) && isset($_GET['sala'])) {
            $tempo = $_GET['tempo'];
            $data = $_GET['data'];
            $sala = $_GET['sala'];
            $motivo = $_POST['motivo'] ?? '';
            $extra = $_POST['extra'] ?? '';
            switch (isset($_GET['subaction']) ? $_GET['subaction'] : null) {
                case "reservar":
                    if (!isset($_POST['motivo'])) {
                        echo "<div class='alert alert-danger show' role='alert'>Motivo é obrigatório.</div>";
                        break;
                    }
                    // Check if room has autonomous reservation (tipo_sala = 2) and if it's locked
                    $stmt = $db->prepare("SELECT tipo_sala, bloqueado FROM salas WHERE id = ?");
                    $stmt->bind_param("s", $sala);
                    $stmt->execute();
                    $salaInfo = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$salaInfo) {
                        http_response_code(404);
                        die("Sala não encontrada.");
                    }
                    
                    // Check if room is locked and user is not admin
                    if ($salaInfo['bloqueado'] == 1 && !$_SESSION['admin']) {
                        http_response_code(403);
                        die("Esta sala está bloqueada. Apenas os administradores podem criar reservas.");
                    }
                    
                    // Check if date is in the past and user is not admin
                    if ($data < $today && !$_SESSION['admin']) {
                        http_response_code(403);
                        die("Não é possível criar reservas no passado. Apenas os administradores podem criar reservas em datas passadas.");
                    }
                    
                    // Determine requisitor: for admins, use selected user if provided
                    $requisitor = $id;
                    if ($_SESSION['admin'] && isset($_POST['requisitor_id']) && !empty($_POST['requisitor_id'])) {
                        // Validate that the requisitor_id exists in the database
                        $userCheckStmt = $db->prepare("SELECT id FROM cache WHERE id = ?");
                        $userCheckStmt->bind_param("s", $_POST['requisitor_id']);
                        $userCheckStmt->execute();
                        $userExists = $userCheckStmt->get_result()->fetch_assoc();
                        $userCheckStmt->close();
                        if ($userExists) {
                            $requisitor = $_POST['requisitor_id'];
                        }
                    }
                    
                    // Get the email of the requisitor to check if they're an internal user
                    $requisitorEmail = '';
                    if ($requisitor === $id) {
                        // Using current user's email
                        $requisitorEmail = $_SESSION['email'];
                    } else {
                        // Admin is reserving for another user, fetch their email
                        $emailStmt = $db->prepare("SELECT email FROM cache WHERE id = ?");
                        $emailStmt->bind_param("s", $requisitor);
                        $emailStmt->execute();
                        $emailResult = $emailStmt->get_result()->fetch_assoc();
                        $emailStmt->close();
                        $requisitorEmail = $emailResult['email'] ?? '';
                    }
                    
                    // Check if user has an internal @aejics.org email
                    $isInternalUser = str_ends_with(strtolower($requisitorEmail), '@aejics.org');
                    
                    // Auto-approve if tipo_sala is 2 (autonomous) AND user is internal, otherwise set to 0 (pending)
                    $aprovado = ($salaInfo['tipo_sala'] == 2 && $isInternalUser) ? 1 : 0;
                    
                    $stmt = $db->prepare("INSERT INTO reservas (sala, tempo, requisitor, data, aprovado, motivo, extra) VALUES (?, ?, ?, ?, ?, ?, ?);");
                    $stmt->bind_param("sssssss", $sala, $tempo, $requisitor, $data, $aprovado, $motivo, $extra);
                    if (!$stmt->execute()) {
                        http_response_code(500);
                        die("Houve um problema a reservar a sala. Contacte um administrador, ou tente novamente mais tarde.");
                    }
                    $stmt->close();
                    
                    // Log reservation creation
                    $salaStmt = $db->prepare("SELECT nome FROM salas WHERE id = ?");
                    $salaStmt->bind_param("s", $sala);
                    $salaStmt->execute();
                    $salaNome = $salaStmt->get_result()->fetch_assoc()['nome'] ?? $sala;
                    $salaStmt->close();
                    
                    $tempoStmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id = ?");
                    $tempoStmt->bind_param("s", $tempo);
                    $tempoStmt->execute();
                    $tempoNome = $tempoStmt->get_result()->fetch_assoc()['horashumanos'] ?? $tempo;
                    $tempoStmt->close();
                    
                    if ($_SESSION['admin'] && $requisitor != $id) {
                        // Admin creating reservation for another user
                        $userStmt = $db->prepare("SELECT nome FROM cache WHERE id = ?");
                        $userStmt->bind_param("s", $requisitor);
                        $userStmt->execute();
                        $userName = $userStmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
                        $userStmt->close();
                        logaction("Criou uma reserva para o utilizador '{$userName}': sala '{$salaNome}' no dia {$data} às {$tempoNome}", $_SESSION['id']);
                    } else {
                        // User creating their own reservation
                        logaction("Criou uma reserva: sala '{$salaNome}' no dia {$data} às {$tempoNome}", $requisitor);
                    }
                    
                    // Save selected materials if any
                    saveReservationMaterials($db, $sala, $tempo, $data, $_POST['materiais'] ?? null);
                    
                    // Send confirmation email to the requisitor
                    // The reservation is only auto-approved if it's autonomous AND user is internal
                    $isAutonomousReservation = ($salaInfo['tipo_sala'] == 2 && $isInternalUser);
                    sendReservationCreatedEmail($db, $requisitor, $sala, $tempo, $data, $motivo, $isAutonomousReservation);
                    
                    header("Location: /reservar/manage.php?sala=" . urlencode($sala) . "&tempo=" . urlencode($tempo) . "&data=" . urlencode($data));
                    exit();
                    break;
                case "apagar":
                    $stmt = $db->prepare("SELECT * FROM reservas WHERE sala=? AND tempo=? AND data=?");
                    $stmt->bind_param("sss", $sala, $tempo, $data);
                    $stmt->execute();
                    $reserva = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!($_SESSION['admin']) && ($_SESSION['id'] != $reserva['requisitor'])) {
                        http_response_code(403);
                        die("Não tem permissão para apagar esta reserva.");
                    }
                    
                    // Check if reservation is in the past and user is not admin
                    if (!($_SESSION['admin']) && ($data < $today)) {
                        http_response_code(403);
                        die("Não é possível apagar reservas no passado. Apenas os administradores podem apagar reservas em datas passadas.");
                    }
                    
                    if (true) {
                        // Get room name and time for log
                        $stmt = $db->prepare("SELECT nome FROM salas WHERE id=?");
                        $stmt->bind_param("s", $sala);
                        $stmt->execute();
                        $salaextenso = $stmt->get_result()->fetch_assoc()['nome'];
                        $stmt->close();
                        
                        $stmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                        $stmt->bind_param("s", $tempo);
                        $stmt->execute();
                        $tempoNome = $stmt->get_result()->fetch_assoc()['horashumanos'] ?? $tempo;
                        $stmt->close();
                        
                        // Determine if deleted by admin (someone other than the requisitor)
                        $deletedByAdmin = ($_SESSION['id'] != $reserva['requisitor']);
                        
                        // Log the deletion with improved message
                        if ($deletedByAdmin) {
                            // Admin deleting someone else's reservation
                            $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
                            $stmt->bind_param("s", $reserva['requisitor']);
                            $stmt->execute();
                            $requisitorNome = $stmt->get_result()->fetch_assoc()['nome'] ?? 'Utilizador';
                            $stmt->close();
                            logAction("Eliminou a reserva do utilizador '{$requisitorNome}': sala '{$salaextenso}' no dia {$data} às {$tempoNome}", $_SESSION['id']);
                        } else {
                            // User deleting their own reservation
                            logAction("Eliminou a sua reserva: sala '{$salaextenso}' no dia {$data} às {$tempoNome}", $_SESSION['id']);
                        }
                        
                        // Send email to the person who made the reservation (not the current user)
                        $emailResult = sendReservationDeletedEmail($db, $reserva['requisitor'], $sala, $tempo, $data, $deletedByAdmin);
                        
                        $stmt = $db->prepare("DELETE FROM reservas WHERE sala=? AND tempo=? AND data=?");
                        $stmt->bind_param("sss", $sala, $tempo, $data);
                        if (!$stmt->execute()) {
                            http_response_code(500);
                            die("Houve um problema a apagar a reserva. Contacte um administrador, ou tente novamente mais tarde.");
                        }
                        $stmt->close();
                        
                        header("Location: /reservar/?sala=" . urlencode($sala));
                        break;
                    }
                case null:
                    $stmt = $db->prepare("SELECT * FROM reservas WHERE sala=? AND tempo=? AND data=? AND aprovado!=-1");
                    $stmt->bind_param("sss", $sala, $tempo, $data);
                    $stmt->execute();
                    $detalhesreserva = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    if (!$detalhesreserva) {
                        $stmt = $db->prepare("SELECT nome, tipo_sala, bloqueado FROM salas WHERE id=?");
                        $stmt->bind_param("s", $sala);
                        $stmt->execute();
                        $salaData = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        $salaextenso = $salaData['nome'];
                        $isAutonomous = ($salaData['tipo_sala'] == 2);
                        $isLocked = ($salaData['bloqueado'] == 1);
                        $canCreateReservation = (!$isLocked || $_SESSION['admin']);
                        
                        echo "<div class='row justify-content-center'>";
                        echo "<div class='col-md-8 col-lg-6'>";
                        
                        if (!$canCreateReservation) {
                            // Non-admin trying to access a locked room
                            echo "<h2 class='mb-4'>Sala Bloqueada</h2>";
                            echo "<div class='alert alert-danger mb-3'><strong>Sala Bloqueada:</strong> Esta sala está bloqueada. Apenas os administradores podem criar reservas.</div>";
                            echo "<a href='/reservar/?sala=" . urlencode($sala) . "' class='btn btn-secondary w-100'>Voltar</a>";
                            echo "</div></div>";
                        } else {
                            echo "<h2 class='mb-4'>Reservar Sala</h2>";
                            
                            // Show locked room notice for admins
                            if ($isLocked && $_SESSION['admin']) {
                                echo "<div class='alert alert-warning mb-3'><strong>Sala Bloqueada:</strong> Esta sala está bloqueada.</div>";
                            }
                            
                            // Check if user has an internal @aejics.org email for autonomous reservations
                            $isInternalUser = str_ends_with(strtolower($_SESSION['email']), '@aejics.org');
                            
                            if ($isAutonomous) {
                                if ($isInternalUser) {
                                    echo "<div class='alert alert-info mb-3'><strong>Reserva Autónoma:</strong> Esta sala é de reserva autónoma. A sua reserva será aprovada automaticamente.</div>";
                                } else {
                                    echo "<div class='alert alert-warning mb-3'><strong>Reserva Autónoma:</strong> Esta sala é de reserva autónoma, mas como utilizador externo, a sua reserva necessita de aprovação por um administrador.</div>";
                                }
                            }
                            // Get materials for this room
                            $materiaisStmt = $db->prepare("SELECT id, nome, descricao FROM materiais WHERE sala_id = ? ORDER BY nome ASC");
                            $materiaisStmt->bind_param("s", $sala);
                            $materiaisStmt->execute();
                            $materiaisResult = $materiaisStmt->get_result();
                            $materiaisStmt->close();
                            
                            echo "<form action='/reservar/manage.php?subaction=reservar&tempo=" . urlencode($tempo) . "&data=" . urlencode($data) . "&sala=" . urlencode($sala) . "' method='POST'>
                        <div class='form-floating mb-3'>
                        <input type='text' class='form-control' id='sala' name='sala' placeholder='Sala' value='" . htmlspecialchars($salaextenso, ENT_QUOTES, 'UTF-8') . "' disabled>
                        <label for='sala'>Sala</label>
                        </div>";
                            
                            // Show user selection for admins with modal lookup
                            if ($_SESSION['admin']) {
                                $usersStmt = $db->query("SELECT id, nome, email FROM cache ORDER BY nome ASC");
                                $usersData = [];
                                while ($user = $usersStmt->fetch_assoc()) {
                                    $usersData[] = $user;
                                }
                                echo "<input type='hidden' id='requisitor_id' name='requisitor_id' value=''>
                                <div class='mb-3'>
                                    <label class='form-label'><strong>Reservar para utilizador (<span style='color: red'>ADMIN</span>):</strong></label>
                                    <div class='input-group'>
                                        <input type='text' class='form-control' id='selectedUserDisplay' placeholder='Reservar para mim mesmo' readonly>
                                        <button class='btn btn-outline-secondary' type='button' data-bs-toggle='modal' data-bs-target='#userSelectModal'>
                                            Procurar
                                        </button>
                                        <button class='btn btn-outline-danger' type='button' onclick='clearUserSelection()'>
                                            Limpar
                                        </button>
                                    </div>
                                </div>";
                                
                                // User selection modal
                                echo "<div class='modal fade' id='userSelectModal' tabindex='-1' aria-labelledby='userSelectModalLabel' aria-hidden='true'>
                                    <div class='modal-dialog modal-dialog-scrollable'>
                                        <div class='modal-content'>
                                            <div class='modal-header'>
                                                <h5 class='modal-title' id='userSelectModalLabel'>Selecionar Utilizador</h5>
                                                <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Fechar'></button>
                                            </div>
                                            <div class='modal-body'>
                                                <div class='mb-3'>
                                                    <input type='text' class='form-control' id='userSearchInput' placeholder='Pesquisar por nome ou email...' oninput='filterUsers()'>
                                                </div>
                                                <div class='list-group' id='userList'>";
                                foreach ($usersData as $user) {
                                    $userId = htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8');
                                    $userName = htmlspecialchars($user['nome'], ENT_QUOTES, 'UTF-8');
                                    $userEmail = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
                                    $isPreRegistered = str_starts_with($user['id'], PRE_REGISTERED_PREFIX);
                                    $preRegBadge = $isPreRegistered ? " <span class='badge bg-warning text-dark'>Pré-registado</span>" : "";
                                    echo "<button type='button' class='list-group-item list-group-item-action user-item' 
                                        data-user-id='{$userId}' 
                                        data-user-name='{$userName}' 
                                        data-user-email='{$userEmail}'
                                        onclick='selectUser(this)'>
                                        <strong>{$userName}</strong>{$preRegBadge}<br>
                                        <small class='text-muted'>{$userEmail}</small>
                                    </button>";
                                }
                                echo "</div>
                                            </div>
                                            <div class='modal-footer'>
                                                <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancelar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>";
                            }
                            
                            echo "<div class='form-floating mb-3'>
                        <input type='text' class='form-control' id='motivo' name='motivo' placeholder='Motivo da Reserva' required>
                        <label for='motivo'>Motivo da Reserva</label>
                        </div>
                        <div class='form-floating mb-3'>
                        <textarea class='form-control' id='extra' name='extra' placeholder='Informação Extra' rows='6' style='height: 150px;'></textarea>
                        <label for='extra'>Informação Extra</label>
                        </div>";
                            
                            // Show materials selection if available
                            if ($materiaisResult->num_rows > 0) {
                                echo "<div class='mb-3'>";
                                echo "<label class='form-label'><strong>Materiais Disponíveis (opcional):</strong></label>";
                                echo "<div class='border rounded p-3' style='max-height: 200px; overflow-y: auto;'>";
                                while ($material = $materiaisResult->fetch_assoc()) {
                                    $materialId = htmlspecialchars($material['id'], ENT_QUOTES, 'UTF-8');
                                    $materialNome = htmlspecialchars($material['nome'], ENT_QUOTES, 'UTF-8');
                                    $materialDesc = htmlspecialchars($material['descricao'], ENT_QUOTES, 'UTF-8');
                                    echo "<div class='form-check'>";
                                    echo "<input class='form-check-input' type='checkbox' name='materiais[]' value='{$materialId}' id='material_{$materialId}'>";
                                    echo "<label class='form-check-label' for='material_{$materialId}'>";
                                    echo "<strong>{$materialNome}</strong>";
                                    if (!empty($materialDesc)) {
                                        echo "<br><small class='text-muted'>{$materialDesc}</small>";
                                    }
                                    echo "</label>";
                                    echo "</div>";
                                }
                                echo "</div>";
                                echo "</div>";
                            }
                            
                            if (!$isAutonomous || !$isInternalUser) {
                                echo "<p class='text-muted small mb-3'>Nota: A reserva será submetida para aprovação.</p>";
                            }
                            echo "<button type='submit' class='btn btn-success w-100 mb-2'>Reservar</button>
                        </form>";
                            echo "<a href='" . htmlspecialchars($_SERVER['HTTP_REFERER'], ENT_QUOTES, 'UTF-8') . "' class='btn btn-secondary w-100'>Voltar</a>";
                            echo "</div></div>";
                        }
                    } else {
                        echo "<div class='row justify-content-center'>";
                        echo "<div class='col-md-10 col-lg-8'>";
                        
                        // Display appropriate message based on approval status
                        if ($detalhesreserva['aprovado'] == 1) {
                            echo "<div class='alert alert-success'><h4 class='alert-heading mb-0'>Reserva Aprovada!</h4></div>";
                        } else if ($detalhesreserva['aprovado'] == 0) {
                            echo "<div class='alert alert-info'><h4 class='alert-heading'>Reserva Submetida!</h4><p class='mb-0'>A reserva foi submetida e está a aguardar aprovação.</p></div>";
                        } else {
                            echo "<div class='alert alert-warning'><h4 class='alert-heading mb-0'>Reserva Cancelada</h4></div>";
                        }
                        
                        echo "<div class='card mb-3'>";
                        echo "<div class='card-body'>";
                        echo "<h5 class='card-title'>Detalhes da Reserva</h5>";
                        
                        $stmt = $db->prepare("SELECT nome, post_reservation_content FROM salas WHERE id=?");
                        $stmt->bind_param("s", $sala);
                        $stmt->execute();
                        $salaData = $stmt->get_result()->fetch_assoc();
                        $stmt->close();
                        
                        echo "<p class='mb-2'><strong>Sala:</strong> " . htmlspecialchars($salaData['nome'], ENT_QUOTES, 'UTF-8') . "</p>";
                        
                        $stmt = $db->prepare("SELECT nome FROM cache WHERE id=?");
                        $stmt->bind_param("s", $detalhesreserva['requisitor']);
                        $stmt->execute();
                        $requisitorextenso = $stmt->get_result()->fetch_assoc()['nome'];
                        $stmt->close();
                        
                        echo "<p class='mb-2'><strong>Requisitada por:</strong> " . htmlspecialchars($requisitorextenso, ENT_QUOTES, 'UTF-8') . "</p>";
                        
                        $stmt = $db->prepare("SELECT horashumanos FROM tempos WHERE id=?");
                        $stmt->bind_param("s", $tempo);
                        $stmt->execute();
                        $horastempo = $stmt->get_result()->fetch_assoc()['horashumanos'];
                        $stmt->close();
                        
                        echo "<p class='mb-2'><strong>Tempo:</strong> " . htmlspecialchars($horastempo, ENT_QUOTES, 'UTF-8') . "</p>";
                        echo "<p class='mb-2'><strong>Data:</strong> " . htmlspecialchars($data, ENT_QUOTES, 'UTF-8') . "</p>";
                        echo "<p class='mb-2'><strong>Motivo:</strong> " . htmlspecialchars($detalhesreserva['motivo'], ENT_QUOTES, 'UTF-8') . "</p>";
                        
                        if (!empty($detalhesreserva['extra'])) {
                            echo "<p class='mb-2'><strong>Informação Extra:</strong></p>";
                            echo "<div class='border rounded p-3'>" . nl2br(htmlspecialchars($detalhesreserva['extra'], ENT_QUOTES, 'UTF-8')) . "</div>";
                        }
                        
                        // Display reserved materials
                        $matStmt = $db->prepare("SELECT m.nome, m.descricao FROM reservas_materiais rm JOIN materiais m ON rm.material_id = m.id WHERE rm.reserva_sala = ? AND rm.reserva_tempo = ? AND rm.reserva_data = ?");
                        $matStmt->bind_param("sss", $sala, $tempo, $data);
                        $matStmt->execute();
                        $matResult = $matStmt->get_result();
                        
                        if ($matResult->num_rows > 0) {
                            echo "<p class='mb-2'><strong>Materiais Reservados:</strong></p>";
                            echo "<div class='border rounded p-3 bg-light'>";
                            echo "<ul class='mb-0'>";
                            while ($mat = $matResult->fetch_assoc()) {
                                $matNome = htmlspecialchars($mat['nome'], ENT_QUOTES, 'UTF-8');
                                $matDesc = htmlspecialchars($mat['descricao'], ENT_QUOTES, 'UTF-8');
                                echo "<li><strong>{$matNome}</strong>";
                                if (!empty($matDesc)) {
                                    echo " - <small class='text-muted'>{$matDesc}</small>";
                                }
                                echo "</li>";
                            }
                            echo "</ul>";
                            echo "</div>";
                        }
                        $matStmt->close();
                        
                        echo "</div>";
                        echo "</div>";
                        
                        // Display post-reservation content if available
                        if (!empty($salaData['post_reservation_content'])) {
                            echo "<div class='card mb-3'>";
                            echo "<div class='card-body'>";
                            echo "<h5 class='card-title'>Informações Importantes</h5>";
                            echo "<div class='post-reservation-content'>";
                            echo $salaData['post_reservation_content']; // Content is already HTML from CKEditor
                            echo "</div>";
                            echo "</div>";
                            echo "</div>";
                        }
                        
                        echo "<div class='d-grid gap-2 d-md-block'>";
                        // Show delete button only if user is the requisitor or admin, AND (reservation is not in past OR user is admin)
                        $isPastReservation = ($data < $today);
                        $canDeleteReservation = ($_SESSION['id'] == $detalhesreserva['requisitor'] || $_SESSION['admin']) && (!$isPastReservation || $_SESSION['admin']);
                        if ($canDeleteReservation) {
                            echo "<a href='/reservar/manage.php?subaction=apagar&tempo=" . urlencode($tempo) . "&data=" . urlencode($data) . "&sala=" . urlencode($sala) . "' class='btn btn-danger me-md-2 mb-2 mb-md-0' onclick='return confirm(\"Tem a certeza que pretende apagar esta reserva?\");'>Apagar Reserva</a> ";
                        }
                        echo "<a href='/reservar' class='btn btn-success me-md-2 mb-2 mb-md-0'>Voltar à página de reserva de salas</a> ";
                        if (strpos($_SERVER['HTTP_REFERER'], '/admin/pedidos.php') !== false) {
                            echo "<a href='/admin/pedidos.php' class='btn btn-primary'>Voltar aos pedidos</a>";
                        } else {
                            echo "<a href='/reservas' class='btn btn-primary'>Ver todas as minhas reservas</a>";
                        }
                        echo "</div>";
                        
                        echo "</div></div>";
                    }
            }
        }
        ?>
    </div>
</body>

</html>