<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
require_once '../includes/security.php';
checkLogin();

$db = new Database();
$conn = $db->getConnection();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ===== AÇÕES DE USUÁRIOS =====
    case 'get_usuario':
        checkPermission(3);
        $id = $_POST['id'] ?? $_GET['id'];
        $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        echo json_encode($result->fetch_assoc());
        break;
        
    case 'remove_irmao':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Verificar se não é o próprio usuário
        if ($id == $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Não é possível remover sua própria conta']);
            break;
        }
        
        $stmt = $conn->prepare("UPDATE usuarios SET ativo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if ($success) {
            // Buscar nome do usuário para log
            $user_stmt = $conn->prepare("SELECT nome FROM usuarios WHERE id = ?");
            $user_stmt->bind_param("i", $id);
            $user_stmt->execute();
            $user_data = $user_stmt->get_result()->fetch_assoc();
            
            Security::logAction('remove_usuario', "Usuário removido: {$user_data['nome']} (ID: $id)");
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'reset_password':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados do usuário
        $stmt = $conn->prepare("SELECT nome, email FROM usuarios WHERE id = ? AND ativo = 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            // Gerar nova senha
            $nova_senha = Security::generateSecurePassword(8);
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            
            // Atualizar no banco
            $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->bind_param("si", $senha_hash, $id);
            
            if ($stmt->execute()) {
                // Enviar por email
                require_once '../config/email.php';
                $emailManager = new EmailManager();
                
                $subject = "Senha Resetada - Sistema Orvalho do Hermon 2966";
                $body = "
                <h3>Senha Resetada</h3>
                <p>Caro Irmão {$user['nome']},</p>
                <p>Sua senha foi resetada por um administrador.</p>
                <p><strong>Nova senha:</strong> <code>$nova_senha</code></p>
                <p>Por favor, altere sua senha após o próximo login.</p>
                ";
                
                $email_sent = $emailManager->sendEmail($user['email'], $subject, $body);
                
                Security::logAction('reset_password', "Senha resetada para usuário: {$user['nome']} (ID: $id)");
                
                echo json_encode([
                    'success' => true,
                    'email_sent' => $email_sent,
                    'message' => 'Senha resetada e enviada por email'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erro ao atualizar senha']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Usuário não encontrado']);
        }
        break;
        
    // ===== AÇÕES FINANCEIRAS =====
    case 'remove_transacao':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados da transação para log
        $stmt = $conn->prepare("SELECT descricao, valor, tipo FROM financeiro WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $transacao = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM financeiro WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if ($success) {
            Security::logAction('remove_transacao', "Transação removida: {$transacao['descricao']} - " . formatMoney($transacao['valor']));
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'edit_transacao':
        checkPermission(3);
        if (isset($_POST['save'])) {
            $id = $_POST['id'];
            $descricao = $_POST['descricao'];
            $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
            $categoria = $_POST['categoria'];
            
            $stmt = $conn->prepare("UPDATE financeiro SET descricao = ?, valor = ?, categoria = ? WHERE id = ?");
            $stmt->bind_param("sdsi", $descricao, $valor, $categoria, $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('edit_transacao', "Transação editada: $descricao - " . formatMoney($valor));
            }
            
            echo json_encode(['success' => $success]);
        } else {
            $id = $_POST['id'] ?? $_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM financeiro WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $transacao = $result->fetch_assoc();
            
            // Formatar valor para exibição
            if ($transacao) {
                $transacao['valor_formatado'] = number_format($transacao['valor'], 2, ',', '.');
            }
            
            echo json_encode($transacao);
        }
        break;
        
    // ===== AÇÕES DE EVENTOS =====
    case 'remove_evento':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados do evento para log
        $stmt = $conn->prepare("SELECT titulo FROM eventos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $evento = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE eventos SET ativo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if ($success) {
            Security::logAction('remove_evento', "Evento removido: {$evento['titulo']} (ID: $id)");
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'edit_evento':
        checkPermission(3);
        if (isset($_POST['save'])) {
            $id = $_POST['id'];
            $titulo = $_POST['titulo'];
            $descricao = $_POST['descricao'];
            $data_evento = $_POST['data_evento'];
            $hora_evento = $_POST['hora_evento'];
            $tipo = $_POST['tipo'];
            $local = $_POST['local'];
            
            $stmt = $conn->prepare("UPDATE eventos SET titulo = ?, descricao = ?, data_evento = ?, hora_evento = ?, tipo = ?, local = ? WHERE id = ?");
            $stmt->bind_param("ssssssi", $titulo, $descricao, $data_evento, $hora_evento, $tipo, $local, $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('edit_evento', "Evento editado: $titulo");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            $id = $_POST['id'] ?? $_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM eventos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc());
        }
        break;
        
    // ===== AÇÕES DE AVISOS =====
    case 'remove_aviso':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados do aviso para log
        $stmt = $conn->prepare("SELECT titulo FROM avisos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $aviso = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE avisos SET ativo = 0 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if ($success) {
            Security::logAction('remove_aviso', "Aviso removido: {$aviso['titulo']} (ID: $id)");
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'edit_aviso':
        checkPermission(3);
        if (isset($_POST['save'])) {
            $id = $_POST['id'];
            $titulo = $_POST['titulo'];
            $conteudo = $_POST['conteudo'];
            $data_expiracao = $_POST['data_expiracao'] ?: null;
            
            $stmt = $conn->prepare("UPDATE avisos SET titulo = ?, conteudo = ?, data_expiracao = ? WHERE id = ?");
            $stmt->bind_param("sssi", $titulo, $conteudo, $data_expiracao, $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('edit_aviso', "Aviso editado: $titulo");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            $id = $_POST['id'] ?? $_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM avisos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc());
        }
        break;
        
    // ===== AÇÕES DE CANDIDATOS =====
    case 'get_candidato':
        checkPermission(3);
        $id = $_GET['id'];
        $stmt = $conn->prepare("SELECT c.*, u.nome as cadastrado_por_nome FROM candidatos c LEFT JOIN usuarios u ON c.cadastrado_por = u.id WHERE c.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $candidato = $result->fetch_assoc();
        
        if ($candidato) {
            $candidato['data_sindicancia_formatada'] = $candidato['data_sindicancia'] ? date('d/m/Y', strtotime($candidato['data_sindicancia'])) : '';
            $candidato['data_cadastro_formatada'] = date('d/m/Y H:i', strtotime($candidato['data_cadastro']));
        }
        
        echo json_encode($candidato);
        break;
        
    case 'update_candidato_status':
        checkPermission(3);
        $id = $_POST['id'];
        $status = $_POST['status'];
        $observacoes = $_POST['observacoes'];
        
        // Buscar nome do candidato para log
        $stmt = $conn->prepare("SELECT nome FROM candidatos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $candidato = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("UPDATE candidatos SET status = ?, observacoes = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $observacoes, $id);
        $success = $stmt->execute();
        
        if ($success) {
            Security::logAction('update_candidato', "Status do candidato {$candidato['nome']} alterado para: $status");
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    case 'remove_candidato':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados do candidato para log
        $stmt = $conn->prepare("SELECT nome FROM candidatos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $candidato = $stmt->get_result()->fetch_assoc();
        
        $stmt = $conn->prepare("DELETE FROM candidatos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        
        if ($success) {
            Security::logAction('remove_candidato', "Candidato removido: {$candidato['nome']} (ID: $id)");
        }
        
        echo json_encode(['success' => $success]);
        break;
        
    // ===== AÇÕES DE TRABALHOS =====
    case 'remove_trabalho':
        $id = $_POST['id'];
        
        // Verificar se o usuário pode remover (autor ou admin/mestre)
        $stmt = $conn->prepare("SELECT autor_id, arquivo, titulo FROM trabalhos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $trabalho = $stmt->get_result()->fetch_assoc();
        
        if ($trabalho['autor_id'] == $_SESSION['user_id'] || $_SESSION['nivel_acesso'] >= 3) {
            // Remover arquivo físico
            $arquivo_path = '../uploads/trabalhos/' . $trabalho['arquivo'];
            if (file_exists($arquivo_path)) {
                unlink($arquivo_path);
            }
            
            // Remover do banco
            $stmt = $conn->prepare("DELETE FROM trabalhos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('remove_trabalho', "Trabalho removido: {$trabalho['titulo']} (ID: $id)");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Sem permissão']);
        }
        break;
        
    case 'edit_trabalho':
        $id = $_POST['id'] ?? $_GET['id'];
        
        // Verificar permissão (autor ou admin/mestre)
        $stmt = $conn->prepare("SELECT autor_id FROM trabalhos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $trabalho = $stmt->get_result()->fetch_assoc();
        
        if ($trabalho['autor_id'] != $_SESSION['user_id'] && $_SESSION['nivel_acesso'] < 3) {
            echo json_encode(['success' => false, 'error' => 'Sem permissão']);
            break;
        }
        
        if (isset($_POST['save'])) {
            $titulo = $_POST['titulo'];
            $descricao = $_POST['descricao'];
            $grau_acesso = $_POST['grau_acesso'];
            
            $stmt = $conn->prepare("UPDATE trabalhos SET titulo = ?, descricao = ?, grau_acesso = ? WHERE id = ?");
            $stmt->bind_param("ssii", $titulo, $descricao, $grau_acesso, $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('edit_trabalho', "Trabalho editado: $titulo");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            $stmt = $conn->prepare("SELECT * FROM trabalhos WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc());
        }
        break;
        
    // ===== AÇÕES DE ATAS =====
    case 'remove_ata':
        checkPermission(3);
        $id = $_POST['id'];
        
        // Buscar dados da ata para log e remoção do arquivo
        $stmt = $conn->prepare("SELECT titulo, arquivo FROM atas WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $ata = $stmt->get_result()->fetch_assoc();
        
        if ($ata) {
            // Remover arquivo físico
            $arquivo_path = '../uploads/atas/' . $ata['arquivo'];
            if (file_exists($arquivo_path)) {
                unlink($arquivo_path);
            }
            
            // Remover do banco
            $stmt = $conn->prepare("DELETE FROM atas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            
            if ($success) {
                // Remover registros de download relacionados
                $conn->query("DELETE FROM ata_downloads WHERE ata_id = $id");
                
                Security::logAction('remove_ata', "Ata removida: {$ata['titulo']} (ID: $id)");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Ata não encontrada']);
        }
        break;
        
    case 'edit_ata':
        checkPermission(3);
        if (isset($_POST['save'])) {
            $id = $_POST['id'];
            $titulo = $_POST['titulo'];
            $descricao = $_POST['descricao'];
            $data_sessao = $_POST['data_sessao'];
            $tipo_sessao = $_POST['tipo_sessao'];
            $numero_sessao = $_POST['numero_sessao'] ?: null;
            
            $stmt = $conn->prepare("UPDATE atas SET titulo = ?, descricao = ?, data_sessao = ?, tipo_sessao = ?, numero_sessao = ? WHERE id = ?");
            $stmt->bind_param("ssssii", $titulo, $descricao, $data_sessao, $tipo_sessao, $numero_sessao, $id);
            $success = $stmt->execute();
            
            if ($success) {
                Security::logAction('edit_ata', "Ata editada: $titulo");
            }
            
            echo json_encode(['success' => $success]);
        } else {
            $id = $_POST['id'] ?? $_GET['id'];
            $stmt = $conn->prepare("SELECT * FROM atas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            echo json_encode($result->fetch_assoc());
        }
        break;
        
    // ===== AÇÕES ADMINISTRATIVAS =====
    case 'backup_database':
        checkPermission(4);
        $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $backup_path = '../backups/' . $backup_file;
        
        if (!is_dir('../backups/')) {
            mkdir('../backups/', 0755, true);
        }
        
        // Configurações do banco (ajustar conforme necessário)
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database = 'orvalho_hermon_2966';
        
        // Comando mysqldump
        $command = "mysqldump --host=$host --user=$username";
        if ($password) {
            $command .= " --password=$password";
        }
        $command .= " $database > $backup_path";
        
        $output = null;
        $result = system($command, $output);
        
        if ($output === 0 && file_exists($backup_path)) {
            $filesize = formatBytes(filesize($backup_path));
            
            Security::logAction('backup_created', "Backup criado: $backup_file ($filesize)");
            
            // Notificar por email se configurado
            $notify_config = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'backup_email_notification'")->fetch_assoc();
            if ($notify_config['valor'] == '1') {
                require_once '../config/email.php';
                $emailManager = new EmailManager();
                $emailManager->sendSystemNotification('backup_created', [
                    'filename' => $backup_file,
                    'filesize' => $filesize
                ]);
            }
            
            echo json_encode(['success' => true, 'file' => $backup_file, 'size' => $filesize]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erro ao criar backup']);
        }
        break;
        
    case 'clean_old_logs':
        checkPermission(4);
        $days = $_POST['days'] ?? 90;
        $affected = Security::cleanOldLogs($days);
        
        echo json_encode([
            'success' => true,
            'affected_rows' => $affected,
            'message' => "$affected registros antigos removidos"
        ]);
        break;
        
    case 'get_stats_dashboard':
        // Estatísticas para gráficos do dashboard
        $stats = [];
        
        // Irmãos por grau
        $graus = $conn->query("SELECT grau, COUNT(*) as total FROM usuarios WHERE ativo = 1 GROUP BY grau ORDER BY grau");
        $stats['irmaos_por_grau'] = [];
        while ($row = $graus->fetch_assoc()) {
            $stats['irmaos_por_grau'][] = [
                'grau' => $row['grau'] . '° Grau',
                'total' => (int)$row['total']
            ];
        }
        
        // Financeiro dos últimos 6 meses
        $financeiro = $conn->query("SELECT 
            DATE_FORMAT(data_transacao, '%Y-%m') as mes,
            SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas,
            SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas
            FROM financeiro 
            WHERE data_transacao >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(data_transacao, '%Y-%m')
            ORDER BY mes");
            
        $stats['financeiro_mensal'] = [];
        while ($row = $financeiro->fetch_assoc()) {
            $stats['financeiro_mensal'][] = [
                'mes' => $row['mes'],
                'entradas' => (float)$row['entradas'],
                'saidas' => (float)$row['saidas']
            ];
        }
        
        // Atividade por tipo nos últimos 30 dias
        $atividades = $conn->query("SELECT 
            action,
            COUNT(*) as total
            FROM logs_sistema 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY action
            ORDER BY total DESC
            LIMIT 10");
            
        $stats['atividades_recentes'] = [];
        while ($row = $atividades->fetch_assoc()) {
            $stats['atividades_recentes'][] = [
                'action' => $row['action'],
                'total' => (int)$row['total']
            ];
        }
        
        // Downloads de atas por mês
        $downloads_atas = $conn->query("SELECT 
            DATE_FORMAT(data_download, '%Y-%m') as mes,
            COUNT(*) as total
            FROM ata_downloads 
            WHERE data_download >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(data_download, '%Y-%m')
            ORDER BY mes");
            
        $stats['downloads_atas'] = [];
        while ($row = $downloads_atas->fetch_assoc()) {
            $stats['downloads_atas'][] = [
                'mes' => $row['mes'],
                'total' => (int)$row['total']
            ];
        }
        
        echo json_encode($stats);
        break;
        
    case 'check_notifications':
        // Verificar notificações pendentes
        $notifications = [];
        
        // Eventos próximos (próximos 7 dias)
        $eventos_proximos = $conn->query("SELECT COUNT(*) as total FROM eventos 
                                         WHERE data_evento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                                         AND ativo = 1");
        $count_eventos = $eventos_proximos->fetch_assoc()['total'];
        
        // Candidatos em sindicância há mais de 30 dias
        $candidatos_pendentes = $conn->query("SELECT COUNT(*) as total FROM candidatos 
                                            WHERE status = 'sindicancia' 
                                            AND data_sindicancia < DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
        $count_candidatos = $candidatos_pendentes->fetch_assoc()['total'];
        
        // Trabalhos pendentes de aprovação (se houver sistema de aprovação)
        $trabalhos_pendentes = 0; // Implementar se necessário
        
        // Atas não baixadas (atas novas)
        $atas_novas = $conn->query("SELECT COUNT(*) as total FROM atas 
                                   WHERE data_upload >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                                   AND ativo = 1");
        $count_atas = $atas_novas->fetch_assoc()['total'];
        
        $notifications = [
            'eventos_proximos' => (int)$count_eventos,
            'candidatos_pendentes' => (int)$count_candidatos,
            'trabalhos_pendentes' => (int)$trabalhos_pendentes,
            'atas_novas' => (int)$count_atas,
            'total' => (int)($count_eventos + $count_candidatos + $trabalhos_pendentes + $count_atas)
        ];
        
        echo json_encode($notifications);
        break;
        
    case 'test_email':
        checkPermission(4);
        $email_teste = $_POST['email'] ?? $_SESSION['user_email'];
        
        require_once '../config/email.php';
        $emailManager = new EmailManager();
        
        $subject = "Teste de Email - Sistema Orvalho do Hermon 2966";
        $body = "
        <h3>Teste de Configuração de Email</h3>
        <p>Este é um email de teste enviado em: " . date('d/m/Y H:i:s') . "</p>
        <p>Se você recebeu este email, as configurações estão funcionando corretamente!</p>
        <p><strong>Sistema:</strong> Orvalho do Hermon 2966</p>
        ";
        
        $success = $emailManager->sendEmail($email_teste, $subject, $body);
        
        Security::logAction('test_email', "Email de teste enviado para: $email_teste (Sucesso: " . ($success ? 'Sim' : 'Não') . ")");
        
        echo json_encode([
            'success' => $success,
            'email' => $email_teste,
            'message' => $success ? 'Email de teste enviado com sucesso!' : 'Falha ao enviar email de teste'
        ]);
        break;
        
    default:
        echo json_encode(['error' => 'Ação não reconhecida: ' . $action]);
}
?>