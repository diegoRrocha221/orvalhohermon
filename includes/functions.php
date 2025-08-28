<?php
session_start();

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }
}

function checkPermission($required_level) {
    if (!isset($_SESSION['nivel_acesso']) || $_SESSION['nivel_acesso'] < $required_level) {
        header('Location: dashboard.php?error=permission');
        exit();
    }
}

function getNivelNome($nivel) {
    $niveis = [
        1 => 'Aprendiz',
        2 => 'Companheiro', 
        3 => 'Mestre',
        4 => 'Administrador'
    ];
    return $niveis[$nivel] ?? 'Desconhecido';
}

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function sendEmail($to, $subject, $body) {
    // Implementar integração com PHPMailer ou similar
    $headers = "From: noreply@orvalhodohermon2966.webcoders.group\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    return mail($to, $subject, $body, $headers);
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

function getLastBackupDate() {
    $backup_dir = 'backups/';
    if (!is_dir($backup_dir)) {
        return 'Nunca';
    }
    
    $files = glob($backup_dir . '*.sql');
    if (empty($files)) {
        return 'Nunca';
    }
    
    $latest = max(array_map('filemtime', $files));
    return date('d/m/Y H:i:s', $latest);
}

function getConfig($key, $default = null) {
    static $configs = null;
    
    if ($configs === null) {
        $db = new Database();
        $conn = $db->getConnection();
        $result = $conn->query("SELECT chave, valor FROM configuracoes");
        $configs = [];
        while ($row = $result->fetch_assoc()) {
            $configs[$row['chave']] = $row['valor'];
        }
    }
    
    return $configs[$key] ?? $default;
}
?>