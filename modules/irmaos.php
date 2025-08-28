<?php
checkPermission(2);

if ($_POST && $_POST['action'] == 'add') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $grau = $_POST['grau'];
    $nivel_acesso = $_POST['nivel_acesso'];
    $telefone = $_POST['telefone'];
    $cim = $_POST['cim'];
    $cargo = $_POST['cargo'];
    $data_iniciacao = $_POST['data_iniciacao'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);
    
    // Validar CIM (6 dígitos)
    if ($cim && !preg_match('/^\d{6}$/', $cim)) {
        echo '<div class="alert alert-danger">CIM deve conter exatamente 6 dígitos!</div>';
    } else {
        // Verificar se CIM já existe
        if ($cim) {
            $cim_check = $conn->prepare("SELECT id FROM usuarios WHERE cim = ? AND id != 0");
            $cim_check->bind_param("s", $cim);
            $cim_check->execute();
            if ($cim_check->get_result()->num_rows > 0) {
                echo '<div class="alert alert-danger">CIM já está sendo usado por outro irmão!</div>';
            } else {
                $insert_success = true;
            }
        } else {
            $insert_success = true;
        }
        
        if (isset($insert_success)) {
            $stmt = $conn->prepare("INSERT INTO usuarios (nome, email, senha, grau, nivel_acesso, telefone, cim, cargo, data_iniciacao) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssissss", $nome, $email, $senha, $grau, $nivel_acesso, $telefone, $cim, $cargo, $data_iniciacao);
            
            if ($stmt->execute()) {
                echo '<div class="alert alert-success">Irmão cadastrado com sucesso!</div>';
                
                // Log da ação
                require_once 'includes/security.php';
                Security::logAction('create_usuario', "Novo usuário cadastrado: $nome (CIM: $cim)");
                
                // Enviar email de boas-vindas
                require_once 'config/email.php';
                $emailManager = new EmailManager();
                $senha_temporaria = $_POST['senha'];
                $usuario_data = [
                    'nome' => $nome,
                    'email' => $email,
                    'grau' => $grau
                ];
                $emailManager->sendWelcomeEmail($usuario_data, $senha_temporaria);
            } else {
                echo '<div class="alert alert-danger">Erro ao cadastrar irmão!</div>';
            }
        }
    }
}

if ($_POST && $_POST['action'] == 'edit') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $grau = $_POST['grau'];
    $nivel_acesso = $_POST['nivel_acesso'];
    $telefone = $_POST['telefone'];
    $cim = $_POST['cim'];
    $cargo = $_POST['cargo'];
    $data_iniciacao = $_POST['data_iniciacao'];
    
    // Validar CIM
    if ($cim && !preg_match('/^\d{6}$/', $cim)) {
        echo '<div class="alert alert-danger">CIM deve conter exatamente 6 dígitos!</div>';
    } else {
        // Verificar se CIM já existe (exceto para o próprio usuário)
        if ($cim) {
            $cim_check = $conn->prepare("SELECT id FROM usuarios WHERE cim = ? AND id != ?");
            $cim_check->bind_param("si", $cim, $id);
            $cim_check->execute();
            if ($cim_check->get_result()->num_rows > 0) {
                echo '<div class="alert alert-danger">CIM já está sendo usado por outro irmão!</div>';
            } else {
                $update_success = true;
            }
        } else {
            $update_success = true;
        }
        
        if (isset($update_success)) {
            $stmt = $conn->prepare("UPDATE usuarios SET nome = ?, email = ?, grau = ?, nivel_acesso = ?, telefone = ?, cim = ?, cargo = ?, data_iniciacao = ? WHERE id = ?");
            $stmt->bind_param("ssisssssi", $nome, $email, $grau, $nivel_acesso, $telefone, $cim, $cargo, $data_iniciacao, $id);
            
            if ($stmt->execute()) {
                echo '<div class="alert alert-success">Dados atualizados com sucesso!</div>';
                
                // Log da ação
                require_once 'includes/security.php';
                Security::logAction('edit_usuario', "Usuário editado: $nome (ID: $id)");
            } else {
                echo '<div class="alert alert-danger">Erro ao atualizar dados!</div>';
            }
        }
    }
}

$irmaos = $conn->query("SELECT * FROM usuarios WHERE ativo = 1 ORDER BY nome");

// Buscar lista de cargos disponíveis
$cargos_config = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'cargos_disponiveis'")->fetch_assoc();
$cargos_disponiveis = explode(',', $cargos_config['valor'] ?? '');
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-users"></i> Gestão de Irmãos</h2>
        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddIrmao">
            <i class="fas fa-plus"></i> Novo Irmão
        </button>
        <button class="btn btn-info" onclick="exportarIrmaos()">
            <i class="fas fa-file-export"></i> Exportar Lista
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>CIM</th>
                        <th>Email</th>
                        <th>Grau</th>
                        <th>Nível</th>
                        <th>Cargo</th>
                        <th>Data Iniciação</th>
                        <th>Telefone</th>
                        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($irmao = $irmaos->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($irmao['nome']) ?></strong>
                            <?php if ($irmao['cargo']): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($irmao['cargo']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($irmao['cim']): ?>
                                <span class="badge bg-primary"><?= htmlspecialchars($irmao['cim']) ?></span>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($irmao['email']) ?></td>
                        <td><?= $irmao['grau'] ?>°</td>
                        <td>
                            <span class="badge bg-<?= getBadgeColor($irmao['nivel_acesso']) ?>">
                                <?= getNivelNome($irmao['nivel_acesso']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($irmao['cargo']) ?: '-' ?></td>
                        <td><?= $irmao['data_iniciacao'] ? date('d/m/Y', strtotime($irmao['data_iniciacao'])) : '-' ?></td>
                        <td><?= htmlspecialchars($irmao['telefone']) ?></td>
                        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarIrmao(<?= $irmao['id'] ?>)" 
                                    data-bs-toggle="modal" data-bs-target="#modalEditIrmao">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="removerIrmao(<?= $irmao['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <button class="btn btn-sm btn-info" onclick="resetarSenha(<?= $irmao['id'] ?>)">
                                <i class="fas fa-key"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($_SESSION['nivel_acesso'] >= 3): ?>
<!-- Modal Novo Irmão -->
<div class="modal fade" id="modalAddIrmao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Irmão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">CIM</label>
                                <input type="text" class="form-control" name="cim" maxlength="6" placeholder="000000">
                                <small class="form-text text-muted">6 dígitos</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="tel" class="form-control" name="telefone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Senha Inicial *</label>
                                <input type="password" class="form-control" name="senha" required minlength="6">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Data de Iniciação</label>
                                <input type="date" class="form-control" name="data_iniciacao">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Grau Simbólico *</label>
                                <select class="form-control" name="grau" required>
                                    <option value="1">1° - Aprendiz</option>
                                    <option value="2">2° - Companheiro</option>
                                    <option value="3">3° - Mestre</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nível de Acesso *</label>
                                <select class="form-control" name="nivel_acesso" required>
                                    <option value="1">Aprendiz</option>
                                    <option value="2">Companheiro</option>
                                    <option value="3">Mestre</option>
                                    <option value="4">Administrador</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Cargo (Mestres)</label>
                                <select class="form-control" name="cargo">
                                    <option value="">Selecione...</option>
                                    <?php foreach ($cargos_disponiveis as $cargo): ?>
                                    <option value="<?= trim($cargo) ?>"><?= trim($cargo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Irmão -->
<div class="modal fade" id="modalEditIrmao" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" id="formEditIrmao">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Irmão</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="editId">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Nome Completo *</label>
                                <input type="text" class="form-control" name="nome" id="editNome" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">CIM</label>
                                <input type="text" class="form-control" name="cim" id="editCim" maxlength="6">
                                <small class="form-text text-muted">6 dígitos</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" id="editEmail" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Telefone</label>
                                <input type="tel" class="form-control" name="telefone" id="editTelefone">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Grau Simbólico *</label>
                                <select class="form-control" name="grau" id="editGrau" required>
                                    <option value="1">1° - Aprendiz</option>
                                    <option value="2">2° - Companheiro</option>
                                    <option value="3">3° - Mestre</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Nível de Acesso *</label>
                                <select class="form-control" name="nivel_acesso" id="editNivelAcesso" required>
                                    <option value="1">Aprendiz</option>
                                    <option value="2">Companheiro</option>
                                    <option value="3">Mestre</option>
                                    <option value="4">Administrador</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Cargo</label>
                                <select class="form-control" name="cargo" id="editCargo">
                                    <option value="">Nenhum</option>
                                    <?php foreach ($cargos_disponiveis as $cargo): ?>
                                    <option value="<?= trim($cargo) ?>"><?= trim($cargo) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data de Iniciação</label>
                        <input type="date" class="form-control" name="data_iniciacao" id="editDataIniciacao">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editarIrmao(id) {
    fetch('api/actions.php', {
        method: 'POST',
        body: new FormData(Object.assign(document.createElement('form'), {
            innerHTML: '<input name="action" value="get_usuario"><input name="id" value="' + id + '">'
        }))
    })
    .then(response => response.json())
    .then(data => {
        document.getElementById('editId').value = data.id;
        document.getElementById('editNome').value = data.nome;
        document.getElementById('editCim').value = data.cim || '';
        document.getElementById('editEmail').value = data.email;
        document.getElementById('editTelefone').value = data.telefone || '';
        document.getElementById('editGrau').value = data.grau;
        document.getElementById('editNivelAcesso').value = data.nivel_acesso;
        document.getElementById('editCargo').value = data.cargo || '';
        document.getElementById('editDataIniciacao').value = data.data_iniciacao || '';
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao carregar dados do irmão');
    });
}

function resetarSenha(id) {
    if (confirm('Deseja resetar a senha deste irmão? Uma nova senha será gerada e enviada por email.')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="reset_password"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Nova senha gerada e enviada por email!');
            } else {
                alert('Erro ao resetar senha: ' + (data.error || 'Erro desconhecido'));
            }
        });
    }
}

function exportarIrmaos() {
    window.open('reports/export.php?type=irmaos&format=csv', '_blank');
}

// Máscara para CIM (apenas números)
document.querySelectorAll('input[name="cim"]').forEach(function(input) {
    input.addEventListener('input', function(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substr(0, 6);
    });
});
</script>

<?php
function getBadgeColor($nivel) {
    switch ($nivel) {
        case 1: return 'secondary';
        case 2: return 'primary';
        case 3: return 'success';
        case 4: return 'danger';
        default: return 'secondary';
    }
}
?>