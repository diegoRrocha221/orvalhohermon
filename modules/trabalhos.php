<?php
if ($_POST && $_POST['action'] == 'add') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $grau_acesso = $_POST['grau_acesso'];
    
    // Verificar se o usuário pode publicar trabalhos neste grau
    if ($grau_acesso > $_SESSION['grau'] && $_SESSION['nivel_acesso'] < 3) {
        echo '<div class="alert alert-danger">Você não tem permissão para publicar trabalhos deste grau!</div>';
    } else {
        // Upload do arquivo
        $upload_dir = 'uploads/trabalhos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $arquivo = '';
        if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
            $arquivo = uniqid() . '_' . $_FILES['arquivo']['name'];
            move_uploaded_file($_FILES['arquivo']['tmp_name'], $upload_dir . $arquivo);
            
            $stmt = $conn->prepare("INSERT INTO trabalhos (titulo, descricao, arquivo, grau_acesso, autor_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssii", $titulo, $descricao, $arquivo, $grau_acesso, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                echo '<div class="alert alert-success">Trabalho publicado com sucesso!</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Erro no upload do arquivo!</div>';
        }
    }
}

// Buscar trabalhos conforme o grau do usuário
$grau_usuario = $_SESSION['grau'];
if ($_SESSION['nivel_acesso'] >= 3) {
    $grau_usuario = 3; // Mestres podem ver todos os trabalhos
}

$trabalhos = $conn->query("SELECT t.*, u.nome as autor_nome FROM trabalhos t 
                          LEFT JOIN usuarios u ON t.autor_id = u.id 
                          WHERE t.ativo = 1 AND t.grau_acesso <= $grau_usuario 
                          ORDER BY t.data_upload DESC");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Acervo Digital</h2>
        <?php if ($_SESSION['nivel_acesso'] >= 2): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddTrabalho">
            <i class="fas fa-plus"></i> Publicar Trabalho
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-header">
                <h6>Filtros</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Grau de Acesso</label>
                    <select class="form-control" id="filtroGrau" onchange="filtrarTrabalhos()">
                        <option value="">Todos</option>
                        <option value="1">1° Grau</option>
                        <?php if ($_SESSION['grau'] >= 2): ?>
                        <option value="2">2° Grau</option>
                        <?php endif; ?>
                        <?php if ($_SESSION['grau'] >= 3): ?>
                        <option value="3">3° Grau</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="buscaTrabalho" onkeyup="filtrarTrabalhos()" placeholder="Título ou autor...">
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-9">
        <div id="listaTrabalhos">
            <?php while ($trabalho = $trabalhos->fetch_assoc()): ?>
            <div class="card mb-3 trabalho-item" data-grau="<?= $trabalho['grau_acesso'] ?>">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <h5><?= htmlspecialchars($trabalho['titulo']) ?></h5>
                            <p class="text-muted"><?= htmlspecialchars($trabalho['descricao']) ?></p>
                            <small>
                                <i class="fas fa-user"></i> <?= htmlspecialchars($trabalho['autor_nome']) ?>
                                | <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($trabalho['data_upload'])) ?>
                                | <i class="fas fa-lock"></i> <?= $trabalho['grau_acesso'] ?>° Grau
                            </small>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="uploads/trabalhos/<?= $trabalho['arquivo'] ?>" class="btn btn-primary" target="_blank">
                                <i class="fas fa-download"></i> Baixar
                            </a>
                            <?php if ($_SESSION['user_id'] == $trabalho['autor_id'] || $_SESSION['nivel_acesso'] >= 3): ?>
                            <button class="btn btn-warning" onclick="editarTrabalho(<?= $trabalho['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger" onclick="removerTrabalho(<?= $trabalho['id'] ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<?php if ($_SESSION['nivel_acesso'] >= 2): ?>
<!-- Modal Novo Trabalho -->
<div class="modal fade" id="modalAddTrabalho" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Publicar Trabalho</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Título do Trabalho</label>
                        <input type="text" class="form-control" name="titulo" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Grau de Acesso</label>
                        <select class="form-control" name="grau_acesso" required>
                            <option value="1">1° Grau (Aprendiz)</option>
                            <?php if ($_SESSION['grau'] >= 2 || $_SESSION['nivel_acesso'] >= 3): ?>
                            <option value="2">2° Grau (Companheiro)</option>
                            <?php endif; ?>
                            <?php if ($_SESSION['grau'] >= 3 || $_SESSION['nivel_acesso'] >= 3): ?>
                            <option value="3">3° Grau (Mestre)</option>
                            <?php endif; ?>
                        </select>
                        <small class="form-text text-muted">Selecione o grau mínimo para acessar este trabalho</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Arquivo</label>
                        <input type="file" class="form-control" name="arquivo" accept=".pdf,.doc,.docx,.txt" required>
                        <small class="form-text text-muted">Formatos aceitos: PDF, DOC, DOCX, TXT</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Publicar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function filtrarTrabalhos() {
    const grau = document.getElementById('filtroGrau').value;
    const busca = document.getElementById('buscaTrabalho').value.toLowerCase();
    const trabalhos = document.querySelectorAll('.trabalho-item');
    
    trabalhos.forEach(trabalho => {
        const grauTrabalho = trabalho.getAttribute('data-grau');
        const texto = trabalho.textContent.toLowerCase();
        
        let mostrar = true;
        
        if (grau && grauTrabalho !== grau) {
            mostrar = false;
        }
        
        if (busca && !texto.includes(busca)) {
            mostrar = false;
        }
        
        trabalho.style.display = mostrar ? 'block' : 'none';
    });
}
</script>