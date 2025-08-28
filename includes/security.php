<?php
class Security {
    
    public static function logAction($action, $details = '', $user_id = null) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $user_id = $user_id ?? ($_SESSION['user_id'] ?? null);
        $ip = self::getClientIP();
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $conn->prepare("INSERT INTO logs_sistema (user_id, action, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("issss", $user_id, $action, $details, $ip, $user_agent);
        $stmt->execute();
    }
    
    public static function checkBruteForce($email) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Verificar tentativas de login nos últimos 15 minutos
        $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                               WHERE email = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND success = 0");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        
        return $data['attempts'] >= 5; // Máximo 5 tentativas em 15 minutos
    }
    
    public static function logLoginAttempt($email, $success) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $ip = self::getClientIP();
        $success = $success ? 1 : 0;
        
        $stmt = $conn->prepare("INSERT INTO login_attempts (email, ip_address, success, attempt_time) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $email, $ip, $success);
        $stmt->execute();
        
        // Log da ação
        self::logAction($success ? 'login_success' : 'login_failed', "Email: $email, IP: $ip");
    }
    
    public static function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function generateSecurePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        return substr(str_shuffle(str_repeat($chars, ceil($length / strlen($chars)))), 0, $length);
    }
    
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function getClientIP() {
        $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    public static function isSecureConnection() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
               $_SERVER['SERVER_PORT'] == 443 ||
               (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }
    
    public static function validateFileUpload($file, $allowed_types = [], $max_size = null) {
        $errors = [];
        
        // Verificar se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Erro no upload do arquivo';
            return $errors;
        }
        
        // Verificar tamanho máximo
        $max_size = $max_size ?? (10 * 1024 * 1024); // 10MB padrão
        if ($file['size'] > $max_size) {
            $errors[] = 'Arquivo muito grande. Máximo: ' . formatBytes($max_size);
        }
        
        // Verificar tipo de arquivo
        if (!empty($allowed_types)) {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($file_info, $file['tmp_name']);
            finfo_close($file_info);
            
            if (!in_array($mime_type, $allowed_types)) {
                $errors[] = 'Tipo de arquivo não permitido';
            }
        }
        
        // Verificar extensão
        $allowed_extensions = ['pdf', 'doc', 'docx', 'txt', 'jpg', 'jpeg', 'png', 'gif'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = 'Extensão de arquivo não permitida';
        }
        
        // Verificar nome do arquivo
        if (strlen($file['name']) > 255) {
            $errors[] = 'Nome do arquivo muito longo';
        }
        
        return $errors;
    }
    
    public static function cleanOldLogs($days = 90) {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Limpar logs antigos
        $stmt = $conn->prepare("DELETE FROM logs_sistema WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $affected_logs = $stmt->execute() ? $conn->affected_rows : 0;
        
        // Limpar tentativas de login antigas
        $stmt = $conn->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        $affected_attempts = $stmt->execute() ? $conn->affected_rows : 0;
        
        self::logAction('clean_logs', "Removidos $affected_logs logs e $affected_attempts tentativas de login");
        
        return $affected_logs + $affected_attempts;
    }
    
    public static function detectSuspiciousActivity() {
        $db = new Database();
        $conn = $db->getConnection();
        
        $alerts = [];
        $current_ip = self::getClientIP();
        
        // Verificar múltiplas tentativas de login de diferentes IPs
        $suspicious_logins = $conn->query("
            SELECT COUNT(DISTINCT ip_address) as unique_ips, COUNT(*) as total_attempts
            FROM login_attempts 
            WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR) AND success = 0
        ")->fetch_assoc();
        
        if ($suspicious_logins['unique_ips'] > 10 && $suspicious_logins['total_attempts'] > 50) {
            $alerts[] = [
                'type' => 'brute_force_attack',
                'message' => 'Possível ataque de força bruta detectado',
                'details' => "IPs únicos: {$suspicious_logins['unique_ips']}, Tentativas: {$suspicious_logins['total_attempts']}"
            ];
        }
        
        // Verificar logins em horários suspeitos (madrugada)
        $night_logins = $conn->query("
            SELECT COUNT(*) as count 
            FROM login_attempts 
            WHERE HOUR(attempt_time) BETWEEN 0 AND 5 
            AND DATE(attempt_time) = CURDATE() 
            AND success = 1
        ")->fetch_assoc();
        
        if ($night_logins['count'] > 5) {
            $alerts[] = [
                'type' => 'unusual_hours',
                'message' => 'Logins em horários incomuns detectados',
                'details' => "Logins entre 00h-05h: {$night_logins['count']}"
            ];
        }
        
        // Verificar tentativas de acesso a páginas administrativas
        $admin_attempts = $conn->query("
            SELECT COUNT(*) as count 
            FROM logs_sistema 
            WHERE action LIKE '%admin%' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND user_id NOT IN (SELECT id FROM usuarios WHERE nivel_acesso >= 3)
        ")->fetch_assoc();
        
        if ($admin_attempts['count'] > 20) {
            $alerts[] = [
                'type' => 'unauthorized_admin_access',
                'message' => 'Tentativas de acesso não autorizado a área administrativa',
                'details' => "Tentativas: {$admin_attempts['count']}"
            ];
        }
        
        // Log dos alertas encontrados
        if (!empty($alerts)) {
            foreach ($alerts as $alert) {
                self::logAction('security_alert', "{$alert['type']}: {$alert['message']} - {$alert['details']}");
            }
        }
        
        return $alerts;
    }
    
    public static function blockSuspiciousIP($ip, $reason = '', $duration_hours = 24) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $unblock_time = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
        
        $stmt = $conn->prepare("INSERT INTO blocked_ips (ip_address, reason, blocked_until, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE blocked_until = ?, reason = ?");
        $stmt->bind_param("sssss", $ip, $reason, $unblock_time, $unblock_time, $reason);
        $stmt->execute();
        
        self::logAction('ip_blocked', "IP $ip bloqueado por $duration_hours horas. Motivo: $reason");
        
        return true;
    }
    
    public static function isIPBlocked($ip = null) {
        $ip = $ip ?? self::getClientIP();
        
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM blocked_ips WHERE ip_address = ? AND blocked_until > NOW()");
        $stmt->bind_param("s", $ip);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc()['count'] > 0;
    }
    
    public static function validateSessionSecurity() {
        // Verificar se a sessão é válida
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Verificar timeout da sessão (8 horas)
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 28800) {
            session_destroy();
            return false;
        }
        
        // Verificar mudança de IP (opcional - pode causar problemas com alguns provedores)
        if (isset($_SESSION['user_ip']) && $_SESSION['user_ip'] !== self::getClientIP()) {
            // Log da mudança de IP mas não destruir sessão automaticamente
            self::logAction('ip_changed', 'IP da sessão alterado de ' . $_SESSION['user_ip'] . ' para ' . self::getClientIP());
        }
        
        // Atualizar última atividade
        $_SESSION['last_activity'] = time();
        $_SESSION['user_ip'] = self::getClientIP();
        
        return true;
    }
    
    public static function encryptSensitiveData($data, $key = null) {
        $key = $key ?? getConfig('encryption_key', 'orvalho_hermon_2966_default_key');
        $method = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
        $encrypted = openssl_encrypt($data, $method, $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public static function decryptSensitiveData($data, $key = null) {
        $key = $key ?? getConfig('encryption_key', 'orvalho_hermon_2966_default_key');
        $method = 'AES-256-CBC';
        
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, $method, $key, 0, $iv);
    }
    
    public static function auditTrail($action, $table, $record_id, $old_data = null, $new_data = null) {
        $db = new Database();
        $conn = $db->getConnection();
        
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = self::getClientIP();
        
        $changes = [];
        if ($old_data && $new_data) {
            foreach ($new_data as $field => $new_value) {
                if (isset($old_data[$field]) && $old_data[$field] != $new_value) {
                    $changes[] = "$field: '{$old_data[$field]}' -> '$new_value'";
                }
            }
        }
        
        $change_details = implode(', ', $changes);
        
        $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, table_name, record_id, changes, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $change_details, $ip);
        $stmt->execute();
    }
    
    // Middleware de segurança para verificar requisições
    public static function securityMiddleware() {
        // Verificar se IP está bloqueado
        if (self::isIPBlocked()) {
            http_response_code(403);
            die('Acesso bloqueado temporariamente');
        }
        
        // Verificar se é uma requisição segura (HTTPS) em produção
        if (!self::isSecureConnection() && getConfig('force_https', '0') == '1') {
            $redirect_url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            header("Location: $redirect_url", true, 301);
            exit;
        }
        
        // Validar sessão
        if (isset($_SESSION['user_id']) && !self::validateSessionSecurity()) {
            header('Location: index.php?expired=1');
            exit;
        }
        
        // Headers de segurança
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (self::isSecureConnection()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

// Auto-executar middleware de segurança
if (php_sapi_name() !== 'cli') {
    Security::securityMiddleware();
}
?>