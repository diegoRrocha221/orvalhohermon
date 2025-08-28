<?php
if ($_POST && $_POST['action'] == 'add' && $_SESSION['nivel_acesso'] >= 3) {
    $titulo = $_POST['titulo'];
    $conteudo = $_POST['conteudo'];
    $data_expiracao = $_POST['data_expiracao'] ?: null;
    
    $stmt = $conn->prepare("INSERT INTO avisos (titulo, conteudo, data_expiracao, criado_por) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $titulo, $conteudo, $data_expiracao, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Aviso publicado com sucesso!</div>';
    }
}

$avisos = $conn->query("SELECT a.*, u.nome as criador FROM avisos a 
                       LEFT JOIN usuarios u ON a.criado_por = u.id 
                       WHERE a.ativo = 1 
                       ORDER BY a.data_criacao DESC");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Painel de Avisos</h2>
        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddAviso">
            <i class="fas fa-plus"></i> Novo Aviso
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <?php while ($aviso = $avisos->fetch_assoc()): ?>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <h6 class="mb-0"><?= htmlspecialchars($aviso['titulo']) ?></h6>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($aviso['data_criacao'])) ?></small>
            </div>
            <div class="card-body">
                <p><?= nl2br(htmlspecialchars($aviso['conteudo'])) ?></p>
                <small class="text-muted">
                    Por: <?= htmlspecialchars($aviso['criador']) ?>
                    <?php if ($aviso['data_expiracao']): ?>
                    | Expira em: <?= date('d/m/Y', strtotime($aviso['data_expiracao'])) ?>
                    <?php endif; ?>
                </small>
            </div>
            <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
            <div class="card-footer">
                <button class="btn btn-sm btn-warning" onclick="editarAviso(<?= $aviso['id'] ?>)">
                    <i class="fas fa-edit"></i> Editar
                </button>
                <button class="btn btn-sm btn-danger" onclick="removerAviso(<?= $aviso['id'] ?>)">
                    <i class="fas fa-trash"></i> Remover
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php if ($_SESSION['nivel_acesso'] >= 3): ?>
<!-- Modal Novo Aviso -->
<div class="modal fade" id="modalAddAviso" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Aviso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Título do Aviso</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Conteúdo</label>
                        <textarea class="form-control" name="conteudo" rows="5" required></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data de Expiração (opcional)</label>
                        <input type="date" class="form-control" name="data_expiracao">
                        <small class="form-text text-muted">Deixe em branco para aviso permanente</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Publicar Aviso</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>