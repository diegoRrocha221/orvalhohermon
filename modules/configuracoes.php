<?php
checkPermission(4);

if ($_POST && $_POST['action'] == 'save_config') {
    foreach ($_POST['config'] as $chave => $valor) {
        $stmt = $conn->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
        $stmt->bind_param("sss", $chave, $valor, $valor);
        $stmt->execute();
    }
    echo '<div class="alert alert-success">Configurações salvas com sucesso!</div>';
}

// Buscar configurações atuais
$configs = [];
$result = $conn->query("SELECT chave, valor FROM configuracoes");
while ($config = $result->fetch_assoc()) {
    $configs[$config['chave']] = $config['valor'];
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Configurações do Sistema</h2>
        <p class="text-muted">Configure as informações gerais da loja e do sistema</p>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <form method="POST">
            <input type="hidden" name="action" value="save_config">
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Informações da Loja</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Loja</label>
                        <input type="text" class="form-control" name="config[nome_loja]" 
                               value="<?= $configs['nome_loja'] ?? 'Orvalho do Hermon 2966' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email da Loja</label>
                        <input type="email" class="form-control" name="config[email_loja]" 
                               value="<?= $configs['email_loja'] ?? '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Telefone da Loja</label>
                        <input type="tel" class="form-control" name="config[telefone_loja]" 
                               value="<?= $configs['telefone_loja'] ?? '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Endereço</label>
                        <textarea class="form-control" name="config[endereco_loja]" rows="3"><?= $configs['endereco_loja'] ?? '' ?></textarea>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Configurações de Email</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Servidor SMTP</label>
                                <input type="text" class="form-control" name="config[smtp_host]" 
                                       value="<?= $configs['smtp_host'] ?? 'smtp.gmail.com' ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Porta SMTP</label>
                                <input type="number" class="form-control" name="config[smtp_port]" 
                                       value="<?= $configs['smtp_port'] ?? '587' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Usuário SMTP</label>
                        <input type="text" class="form-control" name="config[smtp_username]" 
                               value="<?= $configs['smtp_username'] ?? '' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Senha SMTP</label>
                        <input type="password" class="form-control" name="config[smtp_password]" 
                               placeholder="••••••••">
                        <small class="form-text text-muted">Deixe em branco para manter a senha atual</small>
                    </div>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Configurações Gerais</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Fuso Horário</label>
                        <select class="form-control" name="config[timezone]">
                            <option value="America/Sao_Paulo" <?= ($configs['timezone'] ?? '') == 'America/Sao_Paulo' ? 'selected' : '' ?>>São Paulo (UTC-3)</option>
                            <option value="America/Manaus" <?= ($configs['timezone'] ?? '') == 'America/Manaus' ? 'selected' : '' ?>>Manaus (UTC-4)</option>
                            <option value="America/Rio_Branco" <?= ($configs['timezone'] ?? '') == 'America/Rio_Branco' ? 'selected' : '' ?>>Rio Branco (UTC-5)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Tamanho Máximo de Upload (MB)</label>
                        <input type="number" class="form-control" name="config[max_upload_size]" 
                               value="<?= $configs['max_upload_size'] ?? '10' ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">URL do Webhook (opcional)</label>
                        <input type="url" class="form-control" name="config[webhook_url]" 
                               value="<?= $configs['webhook_url'] ?? '' ?>"
                               placeholder="https://exemplo.com/webhook">
                        <small class="form-text text-muted">Para integrações externas</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="config[backup_automatico]" value="1" 
                               <?= ($configs['backup_automatico'] ?? '') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">
                            Backup Automático Diário
                        </label>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="config[manutencao]" value="1" 
                               <?= ($configs['manutencao'] ?? '') == '1' ? 'checked' : '' ?>>
                        <label class="form-check-label">
                            Modo Manutenção (apenas administradores podem acessar)
                        </label>
                    </div>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </form>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Ferramentas do Sistema</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-warning" onclick="criarBackup()">
                        <i class="fas fa-database"></i> Criar Backup
                    </button>
                    
                    <button class="btn btn-info" onclick="limparCache()">
                        <i class="fas fa-broom"></i> Limpar Cache
                    </button>
                    
                    <button class="btn btn-secondary" onclick="verLogs()">
                        <i class="fas fa-list-alt"></i> Ver Logs
                    </button>
                    
                    <button class="btn btn-success" onclick="testarEmail()">
                        <i class="fas fa-envelope"></i> Testar Email
                    </button>
                    
                    <hr>
                    
                    <button class="btn btn-outline-danger" onclick="exportarDados()">
                        <i class="fas fa-download"></i> Exportar Dados
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Informações do Sistema</h5>
            </div>
            <div class="card-body">
                <small>
                    <strong>Versão:</strong> <?= $configs['sistema_versao'] ?? '1.0.0' ?><br>
                    <strong>PHP:</strong> <?= phpversion() ?><br>
                    <strong>MySQL:</strong> <?= $conn->server_info ?><br>
                    <strong>Espaço Livre:</strong> <?= formatBytes(disk_free_space('.')) ?><br>
                    <strong>Último Backup:</strong> <?= getLastBackupDate() ?><br>
                    <strong>Total de Irmãos:</strong> <?= $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1")->fetch_assoc()['total'] ?><br>
                    <strong>Total de Eventos:</strong> <?= $conn->query("SELECT COUNT(*) as total FROM eventos WHERE ativo = 1")->fetch_assoc()['total'] ?>
                </small>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h5>Status do Sistema</h5>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <span class="badge bg-success">Sistema Online</span>
                </div>
                <div class="mb-2">
                    <span class="badge bg-<?= ($configs['manutencao'] ?? '0') == '1' ? 'warning' : 'success' ?>">
                        <?= ($configs['manutencao'] ?? '0') == '1' ? 'Em Manutenção' : 'Operacional' ?>
                    </span>
                </div>
                <div class="mb-2">
                    <span class="badge bg-<?= ($configs['backup_automatico'] ?? '0') == '1' ? 'success' : 'warning' ?>">
                        Backup: <?= ($configs['backup_automatico'] ?? '0') == '1' ? 'Ativo' : 'Inativo' ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportarDados() {
    if (confirm('Deseja exportar todos os dados do sistema?')) {
        window.open('reports/export.php?type=all', '_blank');
    }
}
</script>