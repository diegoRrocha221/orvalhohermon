<?php
if ($_POST && $_POST['action'] == 'upload' && $_SESSION['nivel_acesso'] >= 3) {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data_sessao = $_POST['data_sessao'];
    $tipo_sessao = $_POST['tipo_sessao'];
    $numero_sessao = $_POST['numero_sessao'];
    
    // Upload do arquivo
    $upload_dir = 'uploads/atas/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] == 0) {
        // Validar arquivo
        $allowed_types = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $file_type = $_FILES['arquivo']['type'];
        $file_size = $_FILES['arquivo']['size'];
        $max_size = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($file_type, $allowed_types)) {
            echo '<div class="alert alert-danger">Apenas arquivos PDF, DOC e DOCX são permitidos!</div>';
        } elseif ($file_size > $max_size) {
            echo '<div class="alert alert-danger">Arquivo muito grande! Máximo: 10MB</div>';
        } else {
            $arquivo_nome = date('Y-m-d_H-i-s') . '_ata_' . uniqid() . '_' . $_FILES['arquivo']['name'];
            $arquivo_caminho = $upload_dir . $arquivo_nome;
            
            if (move_uploaded_file($_FILES['arquivo']['tmp_name'], $arquivo_caminho)) {
                $stmt = $conn->prepare("INSERT INTO atas (titulo, descricao, arquivo, data_sessao, tipo_sessao, numero_sessao, enviado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssis", $titulo, $descricao, $arquivo_nome, $data_sessao, $tipo_sessao, $numero_sessao, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo '<div class="alert alert-success">Ata enviada com sucesso!</div>';
                    
                    // Log da ação
                    require_once 'includes/security.php';
                    Security::logAction('upload_ata', "Nova ata enviada: $titulo (Data: $data_sessao)");
                    
                    // Notificar outros mestres (opcional)
                    $stmt_notify = $conn->prepare("INSERT INTO user_notifications (title, message, type, created_by) VALUES (?, ?, 'info', ?)");
                    $notification_title = "Nova Ata Disponível";
                    $notification_message = "A ata '{$titulo}' foi enviada e está disponível para download.";
                    $stmt_notify->bind_param("ssi", $notification_title, $notification_message, $_SESSION['user_id']);
                    $stmt_notify->execute();
                } else {
                    echo '<div class="alert alert-danger">Erro ao salvar no banco de dados!</div>';
                    unlink($arquivo_caminho); // Remove arquivo se erro no banco
                }
            } else {
                echo '<div class="alert alert-danger">Erro no upload do arquivo!</div>';
            }
        }
    } else {
        echo '<div class="alert alert-danger">Nenhum arquivo foi enviado!</div>';
    }
}

// Buscar atas
$atas = $conn->query("SELECT a.*, u.nome as enviado_por_nome FROM atas a 
                     LEFT JOIN usuarios u ON a.enviado_por = u.id 
                     WHERE a.ativo = 1 
                     ORDER BY a.data_sessao DESC");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-file-alt"></i> Atas das Sessões</h2>
        <p class="text-muted">Acervo digital das atas das sessões da loja</p>
        
        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUploadAta">
            <i class="fas fa-upload"></i> Enviar Nova Ata
        </button>
        <?php endif; ?>
        
        <button class="btn btn-info" onclick="exportarAtas()">
            <i class="fas fa-file-export"></i> Exportar Lista
        </button>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Filtrar por Tipo</label>
                <select class="form-control" id="filtroTipo" onchange="filtrarAtas()">
                    <option value="">Todos os tipos</option>
                    <option value="ordinaria">Ordinária</option>
                    <option value="extraordinaria">Extraordinária</option>
                    <option value="magna">Magna</option>
                    <option value="especial">Especial</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Ano</label>
                <select class="form-control" id="filtroAno" onchange="filtrarAtas()">
                    <option value="">Todos os anos</option>
                    <?php
                    $anos = $conn->query("SELECT DISTINCT YEAR(data_sessao) as ano FROM atas WHERE ativo = 1 ORDER BY ano DESC");
                    while ($ano = $anos->fetch_assoc()):
                    ?>
                    <option value="<?= $ano['ano'] ?>"><?= $ano['ano'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" class="form-control" id="buscaAta" onkeyup="filtrarAtas()" placeholder="Título ou descrição...">
            </div>
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button class="btn btn-outline-secondary" onclick="limparFiltros()">
                        <i class="fas fa-times"></i> Limpar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Atas -->
<div class="row" id="listaAtas">
    <?php while ($ata = $atas->fetch_assoc()): ?>
    <div class="col-md-6 mb-4 ata-item" 
         data-tipo="<?= $ata['tipo_sessao'] ?>" 
         data-ano="<?= date('Y', strtotime($ata['data_sessao'])) ?>"
         data-titulo="<?= strtolower($ata['titulo']) ?>"
         data-descricao="<?= strtolower($ata['descricao']) ?>">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><?= htmlspecialchars($ata['titulo']) ?></h6>
                <span class="badge bg-<?= getTipoSessionColor($ata['tipo_sessao']) ?>">
                    <?= ucfirst($ata['tipo_sessao']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <strong><i class="fas fa-calendar"></i> Data:</strong><br>
                        <?= date('d/m/Y', strtotime($ata['data_sessao'])) ?>
                    </div>
                    <div class="col-6">
                        <strong><i class="fas fa-hashtag"></i> Número:</strong><br>
                        <?= $ata['numero_sessao'] ?: '-' ?>
                    </div>
                </div>
                
                <?php if ($ata['descricao']): ?>
                <p class="text-muted"><?= htmlspecialchars(substr($ata['descricao'], 0, 100)) ?><?= strlen($ata['descricao']) > 100 ? '...' : '' ?></p>
                <?php endif; ?>
                
                <small class="text-muted">
                    <i class="fas fa-user"></i> Enviado por: <?= htmlspecialchars($ata['enviado_por_nome']) ?><br>
                    <i class="fas fa-clock"></i> Em: <?= date('d/m/Y H:i', strtotime($ata['data_upload'])) ?><br>
                    <i class="fas fa-download"></i> Downloads: <?= $ata['downloads'] ?>
                </small>
            </div>
            <div class="card-footer">
                <div class="d-grid gap-2 d-md-flex">
                    <a href="download_ata.php?id=<?= $ata['id'] ?>" class="btn btn-primary btn-sm flex-fill">
                        <i class="fas fa-download"></i> Baixar Ata
                    </a>
                    <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                    <button class="btn btn-warning btn-sm" onclick="editarAta(<?= $ata['id'] ?>)">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="removerAta(<?= $ata['id'] ?>)">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<?php if ($_SESSION['nivel_acesso'] >= 3): ?>
<!-- Modal Upload Ata -->
<div class="modal fade" id="modalUploadAta" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar Nova Ata</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="upload">
                    
                    <div class="mb-3">
                        <label class="form-label">Título da Ata *</label>
                        <input type="text" class="form-control" name="titulo" required 
                               placeholder="Ex: Ata da Sessão Ordinária Nº 001/2025">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Data da Sessão *</label>
                                <input type="date" class="form-control" name="data_sessao" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Sessão *</label>
                                <select class="form-control" name="tipo_sessao" required>
                                    <option value="ordinaria">Ordinária</option>
                                    <option value="extraordinaria">Extraordinária</option>
                                    <option value="magna">Magna</option>
                                    <option value="especial">Especial</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">Número da Sessão</label>
                                <input type="number" class="form-control" name="numero_sessao" min="1" max="999">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição (Opcional)</label>
                        <textarea class="form-control" name="descricao" rows="3" 
                                  placeholder="Breve descrição sobre os assuntos tratados na sessão..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Arquivo da Ata *</label>
                        <input type="file" class="form-control" name="arquivo" accept=".pdf,.doc,.docx" required>
                        <small class="form-text text-muted">
                            Formatos aceitos: PDF, DOC, DOCX. Tamanho máximo: 10MB
                        </small>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong><i class="fas fa-info-circle"></i> Importante:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Certifique-se de que a ata está devidamente assinada</li>
                            <li>Remova informações pessoais sensíveis se necessário</li>
                            <li>O arquivo ficará disponível para download por todos os irmãos</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Enviar Ata
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function filtrarAtas() {
    const tipo = document.getElementById('filtroTipo').value.toLowerCase();
    const ano = document.getElementById('filtroAno').value;
    const busca = document.getElementById('buscaAta').value.toLowerCase();
    const atas = document.querySelectorAll('.ata-item');
    
    atas.forEach(ata => {
        const ataTipo = ata.getAttribute('data-tipo');
        const ataAno = ata.getAttribute('data-ano');
        const ataTitulo = ata.getAttribute('data-titulo');
        const ataDescricao = ata.getAttribute('data-descricao');
        
        let mostrar = true;
        
        if (tipo && ataTipo !== tipo) {
            mostrar = false;
        }
        
        if (ano && ataAno !== ano) {
            mostrar = false;
        }
        
        if (busca && !ataTitulo.includes(busca) && !ataDescricao.includes(busca)) {
            mostrar = false;
        }
        
        ata.style.display = mostrar ? 'block' : 'none';
    });
}

function limparFiltros() {
    document.getElementById('filtroTipo').value = '';
    document.getElementById('filtroAno').value = '';
    document.getElementById('buscaAta').value = '';
    filtrarAtas();
}

function editarAta(id) {
    // Implementar edição de ata
    alert('Funcionalidade em desenvolvimento: Editar Ata #' + id);
}

function removerAta(id) {
    if (confirm('Tem certeza que deseja remover esta ata? Esta ação não pode ser desfeita.')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_ata"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover ata: ' + (data.error || 'Erro desconhecido'));
            }
        });
    }
}

function exportarAtas() {
    window.open('reports/export.php?type=atas&format=csv', '_blank');
}

// Auto-sugerir número da sessão baseado no tipo e ano
document.querySelector('select[name="tipo_sessao"]').addEventListener('change', function() {
    const tipo = this.value;
    const ano = new Date().getFullYear();
    
    // Buscar próximo número (implementar via AJAX se necessário)
    // Por enquanto, deixar manual
});

document.querySelector('input[name="data_sessao"]').addEventListener('change', function() {
    const data = this.value;
    if (data) {
        const ano = new Date(data).getFullYear();
        // Sugerir título baseado na data
        const titulo = document.querySelector('input[name="titulo"]');
        if (!titulo.value) {
            titulo.placeholder = `Ata da Sessão Ordinária - ${new Date(data).toLocaleDateString('pt-BR')}`;
        }
    }
});
</script>

<?php
function getTipoSessionColor($tipo) {
    switch ($tipo) {
        case 'ordinaria': return 'primary';
        case 'extraordinaria': return 'warning';
        case 'magna': return 'danger';
        case 'especial': return 'info';
        default: return 'secondary';
    }
}
?>