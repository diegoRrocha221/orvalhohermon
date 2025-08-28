<?php
// Verificar se já está logado
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Buscar configuração do logo
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();
$logo_config = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'logo_url'")->fetch_assoc();
$logo_url = $logo_config['valor'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Orvalho do Hermon 2966</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
            max-width: 400px;
            width: 100%;
        }
        .masonic-symbol {
            font-size: 4rem;
            color: #2a5298;
            margin-bottom: 20px;
        }
        .logo-container {
            max-height: 120px;
            margin-bottom: 20px;
        }
        .logo-container img {
            max-height: 100px;
            width: auto;
            filter: drop-shadow(2px 2px 4px rgba(0,0,0,0.1));
        }
        .form-control:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #2a5298, #667eea);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #1e3c72, #2a5298);
            transform: translateY(-1px);
        }
        .version-info {
            position: absolute;
            bottom: 20px;
            right: 20px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
        }
        .login-attempts-warning {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card login-card">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <?php if ($logo_url && file_exists($logo_url)): ?>
                                <div class="logo-container">
                                    <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo Orvalho do Hermon 2966" class="img-fluid">
                                </div>
                            <?php else: ?>
                                <i class="fas fa-chess-rook masonic-symbol"></i>
                            <?php endif; ?>
                            
                            <h3>Orvalho do Hermon 2966</h3>
                            <p class="text-muted">Sistema de Gerenciamento</p>
                        </div>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <?php if ($_GET['error'] == 'blocked'): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-ban"></i>
                                    <strong>Acesso Bloqueado!</strong><br>
                                    Muitas tentativas de login falharam. Tente novamente em alguns minutos.
                                </div>
                            <?php elseif ($_GET['error'] == 'expired'): ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-clock"></i>
                                    Sua sessão expirou. Faça login novamente.
                                </div>
                            <?php else: ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Credenciais inválidas ou usuário inativo!
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['success']) && $_GET['success'] == 'password_reset'): ?>
                            <div class="alert alert-success">
                                <i class="fas fa-check-circle"></i>
                                Nova senha enviada por email com sucesso!
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="auth.php" id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="senha" class="form-label">Senha</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                                        <i class="fas fa-eye" id="toggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt"></i> Entrar
                                </button>
                                
                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEsqueciSenha">
                                    <i class="fas fa-key"></i> Esqueci minha senha
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Informações do Sistema -->
                <div class="text-center mt-4">
                    <small class="text-light">
                        <i class="fas fa-shield-alt"></i> Conexão Segura
                        <?php if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'): ?>
                        <i class="fas fa-check text-success ms-2"></i> SSL Ativo
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Esqueci Senha -->
    <div class="modal fade" id="modalEsqueciSenha" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recuperar Senha</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="recuperar_senha.php">
                    <div class="modal-body">
                        <p>Digite seu email para receber uma nova senha:</p>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email_recuperacao" required>
                        </div>
                        <div class="alert alert-info">
                            <small>
                                <i class="fas fa-info-circle"></i>
                                Uma nova senha será gerada e enviada para seu email cadastrado.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Enviar Nova Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Informações da versão -->
    <div class="version-info">
        <i class="fas fa-code-branch"></i> v1.1.0
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const senhaInput = document.getElementById('senha');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                senhaInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Auto-focus no campo email
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (!emailInput.value) {
                emailInput.focus();
            } else {
                document.getElementById('senha').focus();
            }
        });
        
        // Validação do formulário
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const senha = document.getElementById('senha').value;
            
            if (!email || !senha) {
                e.preventDefault();
                alert('Por favor, preencha todos os campos.');
                return false;
            }
            
            // Mostrar loading
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Entrando...';
            submitBtn.disabled = true;
        });
        
        // Detecção de tentativas suspeitas (lado cliente)
        let loginAttempts = parseInt(localStorage.getItem('loginAttempts') || '0');
        let lastAttempt = localStorage.getItem('lastAttempt');
        
        if (loginAttempts >= 3 && lastAttempt) {
            const timeDiff = Date.now() - parseInt(lastAttempt);
            const minutesLeft = Math.ceil((900000 - timeDiff) / 60000); // 15 minutos
            
            if (minutesLeft > 0) {
                const warningDiv = document.createElement('div');
                warningDiv.className = 'alert login-attempts-warning';
                warningDiv.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Atenção:</strong> Você fez várias tentativas de login. 
                    Aguarde ${minutesLeft} minuto(s) antes de tentar novamente.
                `;
                document.querySelector('.card-body').insertBefore(warningDiv, document.querySelector('form'));
            }
        }
        
        // Registrar tentativa de login (apenas no lado cliente)
        if (window.location.search.includes('error=1')) {
            loginAttempts++;
            localStorage.setItem('loginAttempts', loginAttempts.toString());
            localStorage.setItem('lastAttempt', Date.now().toString());
        }
        
        // Limpar tentativas após login bem-sucedido
        if (window.location.pathname.includes('dashboard')) {
            localStorage.removeItem('loginAttempts');
            localStorage.removeItem('lastAttempt');
        }
    </script>
</body>
</html>