<?php
require_once '../includes/functions.php';
require_once '../config/database.php';

// Verificar se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado']);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'get';
$user_id = $_SESSION['user_id'];
$nivel_acesso = $_SESSION['nivel_acesso'];

switch ($action) {
    case 'get':
        $notifications = getNotifications($conn, $user_id, $nivel_acesso);
        echo json_encode($notifications);
        break;
        
    case 'mark_read':
        $notification_id = $_POST['id'] ?? null;
        if ($notification_id) {
            markAsRead($conn, $notification_id, $user_id);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'ID não fornecido']);
        }
        break;
        
    case 'mark_all_read':
        markAllAsRead($conn, $user_id);
        echo json_encode(['success' => true]);
        break;
        
    case 'create':
        if ($_SESSION['nivel_acesso'] >= 3) {
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';
            $target_users = $_POST['target_users'] ?? 'all';
            
            $result = createNotification($conn, $title, $message, $type, $target_users, $user_id);
            echo json_encode(['success' => $result]);
        } else {
            echo json_encode(['error' => 'Sem permissão']);
        }
        break;
        
    default:
        echo json_encode(['error' => 'Ação não reconhecida']);
}

function getNotifications($conn, $user_id, $nivel_acesso) {
    $notifications = [];
    $total_count = 0;
    $unread_count = 0;
    
    // Notificações de sistema
    $system_notifications = getSystemNotifications($conn, $nivel_acesso);
    
    // Notificações de eventos próximos
    $event_notifications = getEventNotifications($conn);
    
    // Notificações de candidatos (apenas para mestres)
    $candidate_notifications = [];
    if ($nivel_acesso >= 3) {
        $candidate_notifications = getCandidateNotifications($conn);
    }
    
    // Notificações personalizadas do usuário
    $user_notifications = getUserNotifications($conn, $user_id);
    
    // Combinar todas as notificações
    $all_notifications = array_merge(
        $system_notifications,
        $event_notifications,
        $candidate_notifications,
        $user_notifications
    );
    
    // Ordenar por data
    usort($all_notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limitar a 20 notificações mais recentes
    $notifications = array_slice($all_notifications, 0, 20);
    
    // Contar total e não lidas
    $total_count = count($all_notifications);
    $unread_count = count(array_filter($all_notifications, function($n) {
        return !$n['read'];
    }));
    
    return [
        'notifications' => $notifications,
        'total_count' => $total_count,
        'unread_count' => $unread_count
    ];
}

function getSystemNotifications($conn, $nivel_acesso) {
    $notifications = [];
    
    // Verificar se há backups antigos (apenas admins)
    if ($nivel_acesso >= 4) {
        $last_backup = $conn->query("SELECT MAX(created_at) as last_backup FROM logs_sistema WHERE action = 'backup_created'")->fetch_assoc();
        
        if (!$last_backup['last_backup'] || strtotime($last_backup['last_backup']) < strtotime('-7 days')) {
            $notifications[] = [
                'id' => 'backup_warning',
                'type' => 'warning',
                'title' => 'Backup Atrasado',
                'message' => 'Não há backup há mais de 7 dias. Considere criar um backup do sistema.',
                'icon' => 'fas fa-database',
                'created_at' => date('Y-m-d H:i:s'),
                'read' => false,
                'actionable' => true,
                'action_url' => 'dashboard.php?page=configuracoes',
                'priority' => 'high'
            ];
        }
    }
    
    // Verificar tentativas de login suspeitas (mestres e admins)
    if ($nivel_acesso >= 3) {
        $suspicious_attempts = $conn->query("SELECT COUNT(*) as count FROM login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR) AND success = 0")->fetch_assoc();
        
        if ($suspicious_attempts['count'] > 20) {
            $notifications[] = [
                'id' => 'security_alert',
                'type' => 'danger',
                'title' => 'Atividade Suspeita',
                'message' => "Detectadas {$suspicious_attempts['count']} tentativas de login falhadas nas últimas 24 horas.",
                'icon' => 'fas fa-shield-alt',
                'created_at' => date('Y-m-d H:i:s'),
                'read' => false,
                'actionable' => true,
                'action_url' => 'dashboard.php?page=logs',
                'priority' => 'high'
            ];
        }
    }
    
    return $notifications;
}

function getEventNotifications($conn) {
    $notifications = [];
    
    // Eventos nos próximos 7 dias
    $upcoming_events = $conn->query("SELECT * FROM eventos WHERE data_evento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND ativo = 1 ORDER BY data_evento ASC");
    
    while ($event = $upcoming_events->fetch_assoc()) {
        $days_until = (strtotime($event['data_evento']) - strtotime(date('Y-m-d'))) / (60 * 60 * 24);
        
        $message = '';
        if ($days_until == 0) {
            $message = "Hoje às {$event['hora_evento']}";
        } elseif ($days_until == 1) {
            $message = "Amanhã às {$event['hora_evento']}";
        } else {
            $message = "Em " . (int)$days_until . " dias - " . date('d/m/Y', strtotime($event['data_evento'])) . " às {$event['hora_evento']}";
        }
        
        $notifications[] = [
            'id' => 'event_' . $event['id'],
            'type' => 'info',
            'title' => $event['titulo'],
            'message' => $message,
            'icon' => 'fas fa-calendar',
            'created_at' => $event['data_criacao'],
            'read' => false,
            'actionable' => true,
            'action_url' => 'dashboard.php?page=eventos',
            'priority' => $days_until <= 1 ? 'high' : 'normal'
        ];
    }
    
    return $notifications;
}

function getCandidateNotifications($conn) {
    $notifications = [];
    
    // Candidatos em sindicância há mais de 30 dias
    $old_candidates = $conn->query("SELECT * FROM candidatos WHERE status = 'sindicancia' AND data_sindicancia < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    
    while ($candidate = $old_candidates->fetch_assoc()) {
        $days_passed = (strtotime(date('Y-m-d')) - strtotime($candidate['data_sindicancia'])) / (60 * 60 * 24);
        
        $notifications[] = [
            'id' => 'candidate_' . $candidate['id'],
            'type' => 'warning',
            'title' => 'Sindicância Pendente',
            'message' => "{$candidate['nome']} está em sindicância há " . (int)$days_passed . " dias.",
            'icon' => 'fas fa-user-clock',
            'created_at' => $candidate['data_cadastro'],
            'read' => false,
            'actionable' => true,
            'action_url' => 'dashboard.php?page=candidatos',
            'priority' => 'normal'
        ];
    }
    
    // Novos candidatos nas últimas 24 horas
    $new_candidates = $conn->query("SELECT * FROM candidatos WHERE data_cadastro > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    
    while ($candidate = $new_candidates->fetch_assoc()) {
        $notifications[] = [
            'id' => 'new_candidate_' . $candidate['id'],
            'type' => 'success',
            'title' => 'Novo Candidato',
            'message' => "{$candidate['nome']} foi adicionado à prancha de candidatos.",
            'icon' => 'fas fa-user-plus',
            'created_at' => $candidate['data_cadastro'],
            'read' => false,
            'actionable' => true,
            'action_url' => 'dashboard.php?page=candidatos',
            'priority' => 'normal'
        ];
    }
    
    return $notifications;
}

function getUserNotifications($conn, $user_id) {
    $notifications = [];
    
    // Buscar notificações personalizadas (implementar tabela se necessário)
    $stmt = $conn->prepare("SELECT * FROM user_notifications WHERE user_id = ? OR user_id IS NULL ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($notification = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => 'user_' . $notification['id'],
            'type' => $notification['type'] ?? 'info',
            'title' => $notification['title'],
            'message' => $notification['message'],
            'icon' => $notification['icon'] ?? 'fas fa-info-circle',
            'created_at' => $notification['created_at'],
            'read' => (bool)$notification['read_at'],
            'actionable' => !empty($notification['action_url']),
            'action_url' => $notification['action_url'] ?? null,
            'priority' => $notification['priority'] ?? 'normal'
        ];
    }
    
    return $notifications;
}

function markAsRead($conn, $notification_id, $user_id) {
    // Para notificações do usuário na tabela
    if (strpos($notification_id, 'user_') === 0) {
        $id = str_replace('user_', '', $notification_id);
        $stmt = $conn->prepare("UPDATE user_notifications SET read_at = NOW() WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->bind_param("ii", $id, $user_id);
        return $stmt->execute();
    }
    
    // Para notificações do sistema, criar registro de leitura
    $stmt = $conn->prepare("INSERT INTO notification_reads (notification_id, user_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = NOW()");
    $stmt->bind_param("si", $notification_id, $user_id);
    return $stmt->execute();
}

function markAllAsRead($conn, $user_id) {
    // Marcar todas as notificações do usuário como lidas
    $conn->query("UPDATE user_notifications SET read_at = NOW() WHERE user_id = $user_id AND read_at IS NULL");
    
    // Para notificações do sistema, seria necessário uma abordagem diferente
    // Por simplicidade, apenas retornamos true
    return true;
}

function createNotification($conn, $title, $message, $type, $target_users, $created_by) {
    // Criar notificação para usuários específicos ou todos
    if ($target_users === 'all') {
        // Notificação para todos os usuários
        $stmt = $conn->prepare("INSERT INTO user_notifications (title, message, type, created_by, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->bind_param("sssi", $title, $message, $type, $created_by);
        return $stmt->execute();
    } else {
        // Notificação para usuários específicos
        $user_ids = explode(',', $target_users);
        $success = true;
        
        foreach ($user_ids as $user_id) {
            $stmt = $conn->prepare("INSERT INTO user_notifications (user_id, title, message, type, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("isssi", $user_id, $title, $message, $type, $created_by);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        
        return $success;
    }
}

// Função para limpar notificações antigas (chamada via cron)
function cleanOldNotifications($conn, $days = 30) {
    $conn->query("DELETE FROM user_notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
    $conn->query("DELETE FROM notification_reads WHERE read_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
    return $conn->affected_rows;
}
?>