<?php
checkPermission(3);

if ($_POST && $_POST['action'] == 'add') {
    $tipo = $_POST['tipo'];
    $valor = str_replace(',', '.', str_replace('.', '', $_POST['valor']));
    $descricao = $_POST['descricao'];
    $categoria = $_POST['categoria'];
    $data_transacao = $_POST['data_transacao'];
    
    $stmt = $conn->prepare("INSERT INTO financeiro (tipo, valor, descricao, categoria, data_transacao, usuario_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sdsssi", $tipo, $valor, $descricao, $categoria, $data_transacao, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Transação registrada com sucesso!</div>';
    } else {
        echo '<div class="alert alert-danger">Erro ao registrar transação!</div>';
    }
}

// Buscar saldo atual
$saldo_query = $conn->query("SELECT 
    (SELECT COALESCE(SUM(valor), 0) FROM financeiro WHERE tipo = 'entrada') as entradas,
    (SELECT COALESCE(SUM(valor), 0) FROM financeiro WHERE tipo = 'saida') as saidas"
);
$saldo_data = $saldo_query->fetch_assoc();
$saldo_atual = $saldo_data['entradas'] - $saldo_data['saidas'];

// Buscar transações
$transacoes = $conn->query("SELECT f.*, u.nome as usuario_nome FROM financeiro f 
                          LEFT JOIN usuarios u ON f.usuario_id = u.id 
                          ORDER BY f.data_transacao DESC LIMIT 50");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Gestão Financeira</h2>
        <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#modalAddTransacao" onclick="setTipoTransacao('entrada')">
            <i class="fas fa-plus"></i> Entrada
        </button>
        <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#modalAddTransacao" onclick="setTipoTransacao('saida')">
            <i class="fas fa-minus"></i> Saída
        </button>
        <button class="btn btn-info" onclick="gerarRelatorio()">
            <i class="fas fa-file-pdf"></i> Relatório
        </button>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5>Total Entradas</h5>
                <h3><?= formatMoney($saldo_data['entradas']) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <h5>Total Saídas</h5>
                <h3><?= formatMoney($saldo_data['saidas']) ?></h3>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card text-white <?= $saldo_atual >= 0 ? 'bg-primary' : 'bg-warning' ?>">
            <div class="card-body">
                <h5>Saldo Atual</h5>
                <h3><?= formatMoney($saldo_atual) ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Transações Recentes</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Categoria</th>
                        <th>Valor</th>
                        <th>Usuário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($transacao = $transacoes->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($transacao['data_transacao'])) ?></td>
                        <td>
                            <span class="badge bg-<?= $transacao['tipo'] == 'entrada' ? 'success' : 'danger' ?>">
                                <?= ucfirst($transacao['tipo']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($transacao['descricao']) ?></td>
                        <td><?= htmlspecialchars($transacao['categoria']) ?></td>
                        <td><?= formatMoney($transacao['valor']) ?></td>
                        <td><?= htmlspecialchars($transacao['usuario_nome']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarTransacao(<?= $transacao['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="removerTransacao(<?= $transacao['id'] ?>)">
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

<!-- Modal Nova Transação -->
<div class="modal fade" id="modalAddTransacao" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTransacaoTitle">Nova Transação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tipo" id="tipoTransacao">
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <input type="text" class="form-control" name="descricao" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Valor (R$)</label>
                        <input type="text" class="form-control money" name="valor" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Categoria</label>
                        <select class="form-control" name="categoria" required>
                            <option value="beneficencia">Beneficência</option>
                            <option value="eventos">Eventos</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="material">Material</option>
                            <option value="doacao">Doação</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Data da Transação</label>
                        <input type="date" class="form-control" name="data_transacao" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function setTipoTransacao(tipo) {
    document.getElementById('tipoTransacao').value = tipo;
    document.getElementById('modalTransacaoTitle').textContent = tipo === 'entrada' ? 'Nova Entrada' : 'Nova Saída';
}

// Máscara para valores monetários
document.querySelectorAll('.money').forEach(function(element) {
    element.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        value = (value / 100).toFixed(2) + '';
        value = value.replace('.', ',');
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        e.target.value = value;
    });
});
</script>