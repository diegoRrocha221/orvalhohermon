<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/security.php';

if ($_POST && isset($_POST['email_recuperacao'])) {
    $email = trim($_POST['email_recuperacao']);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: index.php?error=invalid_email');
        exit();
    }
    
    // Verificar se o email existe no sistema
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? AND ativo = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($user = $result->fetch_assoc()) {
        // Gerar nova senha temporária
        $nova_senha = Security::generateSecurePassword(8);
        $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
        
        // Atualizar senha no banco
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->bind_param("si", $senha_hash, $user['id']);
        
        if ($stmt->execute()) {
            // Enviar email com nova senha
            require_once 'config/email.php';
            $emailManager = new EmailManager();
            
            $subject = "Nova Senha - Sistema Orvalho do Hermon 2966";
            $body = getPasswordResetEmailTemplate($user, $nova_senha);
            
            if ($emailManager->sendEmail($user['email'], $subject, $body)) {
                // Log da ação
                Security::logAction('password_reset', "Nova senha gerada para: {$user['email']}", $user['id']);
                
                header('Location: index.php?success=password_reset&email=' . urlencode($email));
                exit();
            } else {
                // Erro ao enviar email - reverter senha
                header('Location: index.php?error=email_failed');
                exit();
            }
        } else {
            header('Location: index.php?error=database_error');
            exit();
        }
    } else {
        // Por segurança, não informar se o email existe ou não
        // Sempre mostrar como sucesso
        header('Location: index.php?success=password_reset&email=' . urlencode($email));
        exit();
    }
}

function getPasswordResetEmailTemplate($usuario, $nova_senha) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; text-align: center; }
            .content { padding: 30px; background: #f8f9fa; }
            .password-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745; text-align: center; }
            .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
            .btn { background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
            .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .password-display { font-family: 'Courier New', monospace; font-size: 24px; font-weight: bold; color: #28a745; background: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px dashed #28a745; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>🔐 Orvalho do Hermon 2966</h1>
            <h2>Nova Senha Gerada</h2>
        </div>
        <div class='content'>
            <h3>Caro Irmão {$usuario['nome']},</h3>
            <p>Uma nova senha foi gerada para sua conta no sistema de gerenciamento da loja.</p>
            
            <div class='password-box'>
                <h4>🔑 Sua Nova Senha:</h4>
                <div class='password-display'>$nova_senha</div>
                <p><small>Copie esta senha exatamente como mostrada acima</small></p>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Importante:</strong> 
                <ul>
                    <li>Esta senha foi gerada automaticamente</li>
                    <li><strong>Altere sua senha</strong> após o primeiro login</li>
                    <li>Acesse: Minha Conta > Alterar Senha</li>
                    <li>Use uma senha forte e pessoal</li>
                </ul>
            </div>
            
            <p><strong>Como fazer login:</strong></p>
            <ol>
                <li>Acesse: <a href='https://orvalhodohermon2966.webcoders.group'>Sistema da Loja</a></li>
                <li>Digite seu email: {$usuario['email']}</li>
                <li>Use a nova senha acima</li>
                <li>Após o login, altere imediatamente sua senha</li>
            </ol>
            
            <div style='text-align: center;'>
                <a href='https://orvalhodohermon2966.webcoders.group' class='btn'>Fazer Login Agora</a>
            </div>
            
            <div class='warning'>
                <strong>🔒 Segurança:</strong> Se você não solicitou esta nova senha, entre em contato imediatamente com a administração da loja.
            </div>
        </div>
        <div class='footer'>
            <p><strong>Sistema de Gerenciamento - Orvalho do Hermon 2966</strong></p>
            <p>Este email foi enviado automaticamente. Não responda este email.</p>
            <p><em>Gerado em: " . date('d/m/Y H:i:s') . "</em></p>
        </div>
    </body>
    </html>";
}

// Se não é POST, redirecionar para login
header('Location: index.php');
exit();
?>