<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if ($_POST) {
    $email = $_POST['email'];
    $senha = $_POST['senha'];
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, nome, email, senha, nivel_acesso, grau FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        if (password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['nome'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['nivel_acesso'] = $user['nivel_acesso'];
            $_SESSION['grau'] = $user['grau'];
            
            // Atualizar último login
            $stmt = $conn->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user['id']);
            $stmt->execute();
            
            header('Location: dashboard.php');
            exit();
        }
    }
    
    header('Location: index.php?error=1');
}
?>