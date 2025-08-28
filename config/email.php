<?php
class EmailManager {
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $from_name;
    private $from_email;
    
    public function __construct() {
        // Carregar configurações do banco de dados
        require_once 'database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $configs = [];
        $result = $conn->query("SELECT chave, valor FROM configuracoes WHERE chave IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'email_loja', 'nome_loja')");
        while ($config = $result->fetch_assoc()) {
            $configs[$config['chave']] = $config['valor'];
        }
        
        $this->smtp_host = $configs['smtp_host'] ?? 'smtp.titan.email';
        $this->smtp_port = $configs['smtp_port'] ?? 465;
        $this->smtp_username = $configs['smtp_username'] ?? 'contato@orvalhodohermon2966.webcoders.group';
        $this->smtp_password = $configs['smtp_password'] ?? 'Orvalho@2966';
        $this->from_email = $configs['email_loja'] ?? 'contato@orvalhodohermon2966.webcoders.group';
        $this->from_name = $configs['nome_loja'] ?? 'Orvalho do Hermon 2966';
    }
    
    public function sendEmail($to, $subject, $body, $isHTML = true) {
        // Implementação básica com função mail() do PHP
        // Para produção, recomenda-se usar PHPMailer ou similar
        
        $headers = "From: {$this->from_name} <{$this->from_email}>\r\n";
        $headers .= "Reply-To: {$this->from_email}\r\n";
        $headers .= "X-Mailer: Sistema Orvalho do Hermon 2966\r\n";
        
        if ($isHTML) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }
        
        // Log do email
        $this->logEmail($to, $subject, 'sent');
        
        return mail($to, $subject, $body, $headers);
    }
    
    public function sendEventNotification($evento, $usuarios) {
        $subject = "Novo Evento: " . $evento['titulo'];
        $body = $this->getEventEmailTemplate($evento);
        
        $sent_count = 0;
        foreach ($usuarios as $usuario) {
            if ($this->sendEmail($usuario['email'], $subject, $body)) {
                $sent_count++;
            }
        }
        
        return $sent_count;
    }
    
    public function sendWelcomeEmail($usuario, $senha_temporaria) {
        $subject = "Bem-vindo ao Sistema - Orvalho do Hermon 2966";
        $body = $this->getWelcomeEmailTemplate($usuario, $senha_temporaria);
        
        return $this->sendEmail($usuario['email'], $subject, $body);
    }
    
    public function sendPasswordReset($usuario, $reset_token) {
        $subject = "Redefinição de Senha - Orvalho do Hermon 2966";
        $body = $this->getPasswordResetTemplate($usuario, $reset_token);
        
        return $this->sendEmail($usuario['email'], $subject, $body);
    }
    
    public function sendSystemNotification($tipo, $dados) {
        switch ($tipo) {
            case 'backup_created':
                $subject = "Backup Criado - " . $this->from_name;
                $body = $this->getBackupNotificationTemplate($dados);
                break;
                
            case 'system_error':
                $subject = "Erro no Sistema - " . $this->from_name;
                $body = $this->getErrorNotificationTemplate($dados);
                break;
                
            case 'new_candidate':
                $subject = "Novo Candidato em Sindicância - " . $this->from_name;
                $body = $this->getCandidateNotificationTemplate($dados);
                break;
                
            default:
                return false;
        }
        
        // Enviar para administradores
        require_once 'database.php';
        $db = new Database();
        $conn = $db->getConnection();
        
        $admins = $conn->query("SELECT email FROM usuarios WHERE nivel_acesso >= 3 AND ativo = 1");
        $sent_count = 0;
        
        while ($admin = $admins->fetch_assoc()) {
            if ($this->sendEmail($admin['email'], $subject, $body)) {
                $sent_count++;
            }
        }
        
        return $sent_count;
    }
    
    private function getEventEmailTemplate($evento) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .event-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2a5298; }
                .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .btn { background: #2a5298; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                .symbol { font-size: 2em; margin-bottom: 15px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='symbol'>⚒️</div>
                <h1>{$this->from_name}</h1>
                <h2>Notificação de Evento</h2>
            </div>
            <div class='content'>
                <h3>Caro Irmão,</h3>
                <p>Foi agendado um novo evento em nossa loja:</p>
                
                <div class='event-details'>
                    <h4>{$evento['titulo']}</h4>
                    <p><strong>📅 Data:</strong> " . date('d/m/Y', strtotime($evento['data_evento'])) . "</p>
                    <p><strong>🕐 Horário:</strong> {$evento['hora_evento']}</p>
                    <p><strong>📍 Local:</strong> {$evento['local']}</p>
                    <p><strong>📋 Tipo:</strong> " . ucfirst($evento['tipo']) . "</p>
                    " . (!empty($evento['descricao']) ? "<p><strong>Descrição:</strong><br>{$evento['descricao']}</p>" : "") . "
                </div>
                
                <p>Sua presença é importante para o fortalecimento de nossa loja.</p>
                
                <a href='https://orvalhodohermon2966.webcoders.group/dashboard.php?page=eventos' class='btn'>Ver no Sistema</a>
            </div>
            <div class='footer'>
                <p>Este é um email automático do Sistema de Gerenciamento.</p>
                <p><strong>{$this->from_name}</strong></p>
                <p>Não responda este email.</p>
            </div>
        </body>
        </html>";
    }
    
    private function getWelcomeEmailTemplate($usuario, $senha_temporaria) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .credentials { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #28a745; }
                .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .btn { background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>⚒️ {$this->from_name}</h1>
                <h2>Bem-vindo ao Sistema</h2>
            </div>
            <div class='content'>
                <h3>Caro Irmão {$usuario['nome']},</h3>
                <p>Seja bem-vindo ao sistema de gerenciamento da nossa loja. Sua conta foi criada com sucesso.</p>
                
                <div class='credentials'>
                    <h4>🔐 Seus Dados de Acesso:</h4>
                    <p><strong>URL:</strong> https://orvalhodohermon2966.webcoders.group</p>
                    <p><strong>Email:</strong> {$usuario['email']}</p>
                    <p><strong>Senha:</strong> $senha_temporaria</p>
                    <p><strong>Grau:</strong> {$usuario['grau']}° - " . $this->getGrauNome($usuario['grau']) . "</p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Importante:</strong> Por motivos de segurança, altere sua senha no primeiro acesso.
                </div>
                
                <p>Através do sistema você poderá:</p>
                <ul>
                    <li>Acompanhar eventos e atividades da loja</li>
                    <li>Receber avisos importantes</li>
                    <li>Acessar o acervo digital de trabalhos</li>
                    <li>Manter seus dados atualizados</li>
                </ul>
                
                <a href='https://orvalhodohermon2966.webcoders.group' class='btn'>Fazer Login</a>
            </div>
            <div class='footer'>
                <p><strong>{$this->from_name}</strong></p>
                <p>Em caso de dúvidas, entre em contato com a administração da loja.</p>
            </div>
        </body>
        </html>";
    }
    
    private function getPasswordResetTemplate($usuario, $reset_token) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; background: #f8f9fa; }
                .reset-box { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 2px solid #ffc107; }
                .footer { background: #343a40; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .btn { background: #ffc107; color: #333; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px; font-weight: bold; }
                .warning { background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 15px 0; color: #721c24; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>🔐 {$this->from_name}</h1>
                <h2>Redefinição de Senha</h2>
            </div>
            <div class='content'>
                <h3>Caro Irmão {$usuario['nome']},</h3>
                <p>Recebemos uma solicitação de redefinição de senha para sua conta no sistema.</p>
                
                <div class='reset-box'>
                    <h4>🔑 Token de Redefinição:</h4>
                    <p style='font-family: monospace; font-size: 18px; background: #f8f9fa; padding: 10px; border-radius: 3px;'>$reset_token</p>
                    <p><small>Este token é válido por 1 hora.</small></p>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Segurança:</strong> Se você não solicitou esta redefinição, ignore este email. Sua conta permanece segura.
                </div>
                
                <p>Para redefinir sua senha:</p>
                <ol>
                    <li>Acesse a página de login do sistema</li>
                    <li>Clique em \"Esqueci minha senha\"</li>
                    <li>Digite o token acima</li>
                    <li>Defina sua nova senha</li>
                </ol>
                
                <a href='https://orvalhodohermon2966.webcoders.group/reset-password.php?token=$reset_token' class='btn'>Redefinir Senha</a>
            </div>
            <div class='footer'>
                <p><strong>{$this->from_name}</strong></p>
                <p>Este link expira em 1 hora por motivos de segurança.</p>
            </div>
        </body>
        </html>";
    }
    
    private function getBackupNotificationTemplate($dados) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>✅ Backup Realizado com Sucesso</h2>
            </div>
            <div class='content'>
                <p><strong>Sistema:</strong> {$this->from_name}</p>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Arquivo:</strong> {$dados['filename']}</p>
                <p><strong>Tamanho:</strong> {$dados['filesize']}</p>
                <p>O backup foi criado automaticamente e está disponível para download no painel administrativo.</p>
            </div>
        </body>
        </html>";
    }
    
    private function getErrorNotificationTemplate($dados) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .error-details { background: #f8f9fa; padding: 15px; border-left: 4px solid #dc3545; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>🚨 Erro no Sistema</h2>
            </div>
            <div class='content'>
                <p><strong>Sistema:</strong> {$this->from_name}</p>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                
                <div class='error-details'>
                    <h4>Detalhes do Erro:</h4>
                    <p><strong>Tipo:</strong> {$dados['type']}</p>
                    <p><strong>Mensagem:</strong> {$dados['message']}</p>
                    <p><strong>Arquivo:</strong> {$dados['file']}</p>
                    <p><strong>Linha:</strong> {$dados['line']}</p>
                    <p><strong>IP:</strong> {$dados['ip']}</p>
                </div>
                
                <p>Por favor, verifique o sistema o quanto antes.</p>
            </div>
        </body>
        </html>";
    }
    
    private function getCandidateNotificationTemplate($dados) {
        return "
        <!DOCTYPE html>
        <html>
        <body>
            <h2>👤 Novo Candidato em Sindicância</h2>
            <p><strong>Nome:</strong> {$dados['nome']}</p>
            <p><strong>Email:</strong> {$dados['email']}</p>
            <p><strong>Telefone:</strong> {$dados['telefone']}</p>
            <p><strong>Data da Sindicância:</strong> " . date('d/m/Y', strtotime($dados['data_sindicancia'])) . "</p>
            <p><strong>Cadastrado por:</strong> {$dados['cadastrado_por']}</p>
        </body>
        </html>";
    }
    
    private function logEmail($to, $subject, $status) {
        try {
            require_once 'database.php';
            $db = new Database();
            $conn = $db->getConnection();
            
            $stmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, status, sent_at) VALUES (?, ?, ?, NOW())");
            $stmt->bind_param("sss", $to, $subject, $status);
            $stmt->execute();
        } catch (Exception $e) {
            // Log silencioso - não interromper o fluxo principal
            error_log("Erro ao registrar log de email: " . $e->getMessage());
        }
    }
    
    private function getGrauNome($grau) {
        $graus = [1 => 'Aprendiz', 2 => 'Companheiro', 3 => 'Mestre'];
        return $graus[$grau] ?? 'Desconhecido';
    }
    
    public function testConfiguration() {
        // Testar configurações de email
        $test_subject = "Teste de Configuração - " . $this->from_name;
        $test_body = "Este é um email de teste para verificar se as configurações estão corretas.";
        
        return $this->sendEmail($this->from_email, $test_subject, $test_body);
    }
}
?>