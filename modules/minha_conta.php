<?php
if ($_POST && $_POST['action'] == 'change_password') {
    $senha_atual = $_POST['senha_atual'];
    $nova_senha = $_POST['nova_senha'];
    $confirmar_senha = $_POST['confirmar_senha'];
    
    if ($nova_senha !== $confirmar_senha) {
        echo '<div class="alert alert-danger">As senhas não coincidem!</div>';
    } elseif (strlen($nova_senha) < 6) {
        echo '<div class="alert alert-danger">A nova senha deve ter pelo menos 6 caracteres!</div>';
    } else {
        // Verificar senha atual
        $stmt = $conn->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (password_verify($senha_atual, $user['senha'])) {
            // Atualizar senha
            $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
            $stmt->bind_param("si", $nova_senha_hash, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo '<div class="alert alert-success">Senha alterada com sucesso!</div>';
                
                // Log da ação
                require_once 'includes/security.php';
                Security::logAction('change_password', "Usuário alterou sua própria senha");
            } else {
                echo '<div class="alert alert-danger">Erro ao alterar senha!</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Senha atual incorreta!</div>';
        }
    }
}

if ($_POST && $_POST['action'] == 'change_email') {
    $email_atual = $_POST['email_atual'];
    $novo_email = $_POST['novo_email'];
    $senha_confirmacao = $_POST['senha_confirmacao'];
    
    if (!filter_var($novo_email, FILTER_VALIDATE_EMAIL)) {
        echo '<div class="alert alert-danger">Email inválido!</div>';
    } else {
        // Verificar senha para confirmação
        $stmt = $conn->prepare("SELECT senha, email FROM usuarios WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user['email'] !== $email_atual) {
            echo '<div class="alert alert-danger">Email atual incorreto!</div>';
        } elseif (!password_verify($senha_confirmacao, $user['senha'])) {
            echo '<div class="alert alert-danger">Senha incorreta!</div>';
        } else {
            // Verificar se o novo email já está em uso
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $novo_email, $_SESSION['user_id']);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows > 0) {
                echo '<div class="alert alert-danger">Este email já está sendo usado por outro irmão!</div>';
            } else {
                // Atualizar email
                $stmt = $conn->prepare("UPDATE usuarios SET email = ? WHERE id = ?");
                $stmt->bind_param("si", $novo_email, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $_SESSION['user_email'] = $novo_email;
                    echo '<div class="alert alert-success">Email alterado com sucesso!</div>';
                    
                    // Log da ação
                    require_once 'includes/security.php';
                    Security::logAction('change_email', "Usuário alterou email de $email_atual para $novo_email");
                } else {
                    echo '<div class="alert alert-danger">Erro ao alterar email!</div>';
                }
            }
        }
    }
}

if ($_POST && $_POST['action'] == 'update_profile') {
    $telefone = $_POST['telefone'];
    
    $stmt = $conn->prepare("UPDATE usuarios SET telefone = ? WHERE id = ?");
    $stmt->bind_param("si", $telefone, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Perfil atualizado com sucesso!</div>';
        
        // Log da ação
        require_once 'includes/security.php';
        Security::logAction('update_profile', "Usuário atualizou dados do perfil");
    } else {
        echo '<div class="alert alert-danger">Erro ao atualizar perfil!</div>';
    }
}

// Buscar dados do usuário atual
$stmt = $conn->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Buscar estatísticas de atividade do usuário
$stats_downloads = $conn->query("SELECT COUNT(*) as total FROM ata_downloads WHERE usuario_id = {$_SESSION['user_id']}")->fetch_assoc()['total'];
$stats_trabalhos = $conn->query("SELECT COUNT(*) as total FROM trabalhos WHERE autor_id = {$_SESSION['user_id']} AND ativo = 1")->fetch_assoc()['total'];
$stats_eventos_criados = $conn->query("SELECT COUNT(*) as total FROM eventos WHERE criado_por = {$_SESSION['user_id']} AND ativo = 1")->fetch_assoc()['total'];

// Buscar últimas atividades
$atividades = $conn->query("SELECT action, details, created_at FROM logs_sistema WHERE user_id = {$_SESSION['user_id']} ORDER BY created_at DESC LIMIT 10");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-user-circle"></i> Minha Conta</h2>
        <p class="text-muted">Gerencie suas informações pessoais e configurações</p>
    </div>
</div>

<!-- Cards de Estatísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-download fa-2x mb-2"></i>
                <h4><?= $stats_downloads ?></h4>
                <small>Downloads de Atas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-book fa-2x mb-2"></i>
                <h4><?= $stats_trabalhos ?></h4>
                <small>Trabalhos Publicados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-calendar-plus fa-2x mb-2"></i>
                <h4><?= $stats_eventos_criados ?></h4>
                <small>Eventos Criados</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card stats-card">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-2x mb-2"></i>
                <h4><?= $usuario['ultimo_login'] ? date('d/m', strtotime($usuario['ultimo_login'])) : 'Nunca' ?></h4>
                <small>Último Acesso</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Informações do Perfil -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-id-card"></i> Informações do Perfil</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="text-center mb-3">
                        <div class="avatar-placeholder bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                            <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['nome']) ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">CIM</label>
                        <input type="text" class="form-control" value="<?= $usuario['cim'] ?: 'Não informado' ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Grau Simbólico</label>
                        <input type="text" class="form-control" value="<?= $usuario['grau'] ?>° - <?= getNivelNome($usuario['grau']) ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Cargo</label>
                        <input type="text" class="form-control" value="<?= $usuario['cargo'] ?: 'Nenhum' ?>" disabled>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="tel" class="form-control" name="telefone" value="<?= htmlspecialchars($usuario['telefone']) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data de Iniciação</label>
                        <input type="text" class="form-control" value="<?= $usuario['data_iniciacao'] ? date('d/m/Y', strtotime($usuario['data_iniciacao'])) : 'Não informado' ?>" disabled>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save"></i> Atualizar Telefone
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Alterar Senha -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-lock"></i> Alterar Senha</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formAlterarSenha">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="mb-3">
                        <label class="form-label">Senha Atual *</label>
                        <input type="password" class="form-control" name="senha_atual" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Nova Senha *</label>
                        <input type="password" class="form-control" name="nova_senha" required minlength="6">
                        <small class="form-text text-muted">Mínimo 6 caracteres</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar Nova Senha *</label>
                        <input type="password" class="form-control" name="confirmar_senha" required minlength="6">
                    </div>
                    
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-key"></i> Alterar Senha
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Alterar Email -->
        <div class="card mt-3">
            <div class="card-header">
                <h5><i class="fas fa-envelope"></i> Alterar Email</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="formAlterarEmail">
                    <input type="hidden" name="action" value="change_email">
                    
                    <div class="mb-3">
                        <label class="form-label">Email Atual *</label>
                        <input type="email" class="form-control" name="email_atual" value="<?= htmlspecialchars($usuario['email']) ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Novo Email *</label>
                        <input type="email" class="form-control" name="novo_email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirmar com Senha *</label>
                        <input type="password" class="form-control" name="senha_confirmacao" required>
                        <small class="form-text text-muted">Digite sua senha atual para confirmar</small>
                    </div>
                    
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-at"></i> Alterar Email
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Atividades Recentes -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Atividades Recentes</h5>
            </div>
            <div class="card-body">
                <div class="activity-list">
                    <?php if ($atividades->num_rows > 0): ?>
                    <?php while ($atividade = $atividades->fetch_assoc()): ?>
                    <div class="activity-item mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between">
                            <strong><?= getActionIcon($atividade['action']) ?> <?= getActionName($atividade['action']) ?></strong>
                            <small class="text-muted"><?= date('d/m H:i', strtotime($atividade['created_at'])) ?></small>
                        </div>
                        <?php if ($atividade['details']): ?>
                        <small class="text-muted"><?= htmlspecialchars(substr($atividade['details'], 0, 60)) ?><?= strlen($atividade['details']) > 60 ? '...' : '' ?></small>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                    <?php else: ?>
                    <p class="text-muted text-center">Nenhuma atividade registrada</p>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-3">
                    <a href="dashboard.php?page=logs&user_filter=<?= urlencode($usuario['nome']) ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-list"></i> Ver Todas as Atividades
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('formAlterarSenha').addEventListener('submit', function(e) {
    const novaSenha = this.nova_senha.value;
    const confirmarSenha = this.confirmar_senha.value;
    
    if (novaSenha !== confirmarSenha) {
        e.preventDefault();
        alert('As senhas não coincidem!');
        return false;
    }
    
    if (novaSenha.length < 6) {
        e.preventDefault();
        alert('A nova senha deve ter pelo menos 6 caracteres!');
        return false;
    }
});

document.getElementById('formAlterarEmail').addEventListener('submit', function(e) {
    const emailAtual = this.email_atual.value;
    const novoEmail = this.novo_email.value;
    
    if (emailAtual === novoEmail) {
        e.preventDefault();
        alert('O novo email deve ser diferente do atual!');
        return false;
    }
    
    if (!confirm('Tem certeza que deseja alterar seu email? Isso afetará seu login no sistema.')) {
        e.preventDefault();
        return false;
    }
});

// Máscara para telefone
document.querySelector('input[name="telefone"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length <= 11) {
        if (value.length === 11) {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (value.length === 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }
    }
    e.target.value = value;
});
</script>

<?php
function getActionIcon($action) {
    $icons = [
        'login_success' => '<i class="fas fa-sign-in-alt text-success"></i>',
        'logout' => '<i class="fas fa-sign-out-alt text-secondary"></i>',
        'create_usuario' => '<i class="fas fa-user-plus text-primary"></i>',
        'edit_usuario' => '<i class="fas fa-user-edit text-warning"></i>',
        'upload_ata' => '<i class="fas fa-file-upload text-info"></i>',
        'download_ata' => '<i class="fas fa-download text-success"></i>',
        'change_password' => '<i class="fas fa-key text-warning"></i>',
        'change_email' => '<i class="fas fa-at text-info"></i>',
        'update_profile' => '<i class="fas fa-user-edit text-primary"></i>'
    ];
    
    return $icons[$action] ?? '<i class="fas fa-circle text-secondary"></i>';
}

function getActionName($action) {
    $names = [
        'login_success' => 'Login realizado',
        'logout' => 'Logout',
        'create_usuario' => 'Usuário cadastrado',
        'edit_usuario' => 'Usuário editado',
        'upload_ata' => 'Ata enviada',
        'download_ata' => 'Ata baixada',
        'change_password' => 'Senha alterada',
        'change_email' => 'Email alterado',
        'update_profile' => 'Perfil atualizado'
    ];
    
    return $names[$action] ?? ucfirst(str_replace('_', ' ', $action));
}
?>