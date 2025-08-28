<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/security.php';

checkLogin();

$ata_id = $_GET['id'] ?? null;

if (!$ata_id) {
    http_response_code(404);
    die('Ata não encontrada');
}

$db = new Database();
$conn = $db->getConnection();

// Buscar informações da ata
$stmt = $conn->prepare("SELECT * FROM atas WHERE id = ? AND ativo = 1");
$stmt->bind_param("i", $ata_id);
$stmt->execute();
$result = $stmt->get_result();
$ata = $result->fetch_assoc();

if (!$ata) {
    http_response_code(404);
    die('Ata não encontrada ou não disponível');
}

$arquivo_path = 'uploads/atas/' . $ata['arquivo'];

// Verificar se o arquivo existe
if (!file_exists($arquivo_path)) {
    http_response_code(404);
    die('Arquivo não encontrado no servidor');
}

// Registrar o download
$user_id = $_SESSION['user_id'];
$ip = Security::getClientIP();

$stmt = $conn->prepare("INSERT INTO ata_downloads (ata_id, usuario_id, ip_address) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $ata_id, $user_id, $ip);
$stmt->execute();

// Incrementar contador de downloads
$conn->query("UPDATE atas SET downloads = downloads + 1 WHERE id = $ata_id");

// Log da ação
Security::logAction('download_ata', "Download da ata: {$ata['titulo']} (ID: $ata_id)");

// Definir headers para download
$file_extension = pathinfo($ata['arquivo'], PATHINFO_EXTENSION);
$file_name = sanitizeFileName($ata['titulo']) . '_' . date('Y-m-d', strtotime($ata['data_sessao'])) . '.' . $file_extension;

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Headers de download
switch (strtolower($file_extension)) {
    case 'pdf':
        header('Content-Type: application/pdf');
        break;
    case 'doc':
        header('Content-Type: application/msword');
        break;
    case 'docx':
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        break;
    default:
        header('Content-Type: application/octet-stream');
}

header('Content-Disposition: attachment; filename="' . $file_name . '"');
header('Content-Length: ' . filesize($arquivo_path));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Enviar arquivo
readfile($arquivo_path);

function sanitizeFileName($filename) {
    // Remove caracteres especiais e acentos
    $filename = preg_replace('/[áàâãä]/ui', 'a', $filename);
    $filename = preg_replace('/[éèêë]/ui', 'e', $filename);
    $filename = preg_replace('/[íìîï]/ui', 'i', $filename);
    $filename = preg_replace('/[óòôõö]/ui', 'o', $filename);
    $filename = preg_replace('/[úùûü]/ui', 'u', $filename);
    $filename = preg_replace('/[ç]/ui', 'c', $filename);
    $filename = preg_replace('/[ñ]/ui', 'n', $filename);
    
    // Remove caracteres especiais
    $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $filename);
    
    // Substitui espaços por underscores
    $filename = preg_replace('/\s+/', '_', trim($filename));
    
    // Remove underscores múltiplos
    $filename = preg_replace('/_+/', '_', $filename);
    
    return $filename;
}

exit;
?>