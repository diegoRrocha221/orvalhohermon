<?php
checkPermission(3);

if ($_POST && $_POST['action'] == 'add') {
    $nome = $_POST['nome'];
    $email = $_POST['email'];
    $telefone = $_POST['telefone'];
    $data_sindicancia = $_POST['data_sindicancia'];
    $observacoes = $_POST['observacoes'];
    
    $stmt = $conn->prepare("INSERT INTO candidatos (nome, email, telefone, data_sindicancia, observacoes, cadastrado_por) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $nome, $email, $telefone, $data_sindicancia, $observacoes, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Candidato adicionado com sucesso!</div>';
    }
}

if ($_POST && $_POST['action'] == 'update_status') {
    $id = $_POST['candidato_id'];
    $status = $_POST['status'];
    $observacoes = $_POST['observacoes'];
    
    $stmt = $conn->prepare("UPDATE candidatos SET status = ?, observacoes = ? WHERE id = ?");
    $stmt->bind_param("ssi", $status, $observacoes, $id);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Status atualizado com sucesso!</div>';
    }
}

$candidatos = $conn->query("SELECT c.*, u.nome as cadastrado_por_nome FROM candidatos c 
                           LEFT JOIN usuarios u ON c.cadastrado_por = u.id 
                           ORDER BY c.data_cadastro DESC");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Prancha de Candidatos</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddCandidato">
            <i class="fas fa-plus"></i> Novo Candidato
        </button>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Telefone</th>
                        <th>Data Sindicância</th>
                        <th>Status</th>
                        <th>Cadastrado por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($candidato = $candidatos->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($candidato['nome']) ?></td>
                        <td><?= htmlspecialchars($candidato['email']) ?></td>
                        <td><?= htmlspecialchars($candidato['telefone']) ?></td>
                        <td><?= $candidato['data_sindicancia'] ? date('d/m/Y', strtotime($candidato['data_sindicancia'])) : '-' ?></td>
                        <td>
                            <?php 
                            $status_colors = [
                                'sindicancia' => 'warning',
                                'aprovado' => 'success', 
                                'reprovado' => 'danger'
                            ];
                            ?>
                            <span class="badge bg-<?= $status_colors[$candidato['status']] ?>">
                                <?= ucfirst($candidato['status']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($candidato['cadastrado_por_nome']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" onclick="verDetalhes(<?= $candidato['id'] ?>)" data-bs-toggle="modal" data-bs-target="#modalDetalhesCandidato">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-sm btn-warning" onclick="editarStatus(<?= $candidato['id'] ?>)" data-bs-toggle="modal" data-bs-target="#modalEditStatus">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="removerCandidato(<?= $candidato['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Novo Candidato -->
<div class="modal fade" id="modalAddCandidato" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Candidato</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Nome Completo</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Telefone</label>
                        <input type="tel" class="form-control" name="telefone">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data de Início da Sindicância</label>
                        <input type="date" class="form-control" name="data_sindicancia">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observações Iniciais</label>
                        <textarea class="form-control" name="observacoes" rows="3"></textarea>
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

<!-- Modal Detalhes do Candidato -->
<div class="modal fade" id="modalDetalhesCandidato" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Candidato</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhesCandidato">
                <!-- Conteúdo será preenchido via JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Status -->
<div class="modal fade" id="modalEditStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="candidato_id" id="editCandidatoId">
                    
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select class="form-control" name="status" id="editStatus" required>
                            <option value="sindicancia">Sindicância</option>
                            <option value="aprovado">Aprovado</option>
                            <option value="reprovado">Reprovado</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" name="observacoes" id="editObservacoes" rows="4"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>