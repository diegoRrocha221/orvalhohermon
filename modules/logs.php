<?php
checkPermission(4);

$page = $_GET['log_page'] ?? 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtros
$user_filter = $_GET['user_filter'] ?? '';
$action_filter = $_GET['action_filter'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];
$types = '';

if ($user_filter) {
    $where_conditions[] = "u.nome LIKE ?";
    $params[] = "%$user_filter%";
    $types .= 's';
}

if ($action_filter) {
    $where_conditions[] = "l.action LIKE ?";
    $params[] = "%$action_filter%";
    $types .= 's';
}

if ($date_from) {
    $where_conditions[] = "DATE(l.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}

if ($date_to) {
    $where_conditions[] = "DATE(l.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Query principal
$sql = "SELECT l.*, u.nome as user_name FROM logs_sistema l 
        LEFT JOIN usuarios u ON l.user_id = u.id 
        $where_clause
        ORDER BY l.created_at DESC 
        LIMIT $per_page OFFSET $offset";

if (!empty($params)) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $logs = $stmt->get_result();
} else {
    $logs = $conn->query($sql);
}

// Total de registros para paginação
$count_sql = "SELECT COUNT(*) as total FROM logs_sistema l 
              LEFT JOIN usuarios u ON l.user_id = u.id 
              $where_clause";

if (!empty($params)) {
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total_logs = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total_logs = $conn->query($count_sql)->fetch_assoc()['total'];
}

$total_pages = ceil($total_logs / $per_page);

// Estatísticas dos logs
$stats_today = $conn->query("SELECT COUNT(*) as total FROM logs_sistema WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$stats_week = $conn->query("SELECT COUNT(*) as total FROM logs_sistema WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'];
$stats_month = $conn->query("SELECT COUNT(*) as total FROM logs_sistema WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['total'];
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Logs do Sistema</h2>
        <p class="text-muted">Registro de atividades dos usuários</p>
    </div>
</div>

<!-- Estatísticas -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6>Atividades Hoje</h6>
                <h3><?= $stats_today ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6>Últimos 7 Dias</h6>
                <h3><?= $stats_week ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6>Últimos 30 Dias</h6>
                <h3><?= $stats_month ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <h5>Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="logs">
            
            <div class="col-md-3">
                <label class="form-label">Usuário</label>
                <input type="text" class="form-control" name="user_filter" value="<?= htmlspecialchars($user_filter) ?>" placeholder="Nome do usuário">
            </div>
            
            <div class="col-md-3">
                <label class="form-label">Ação</label>
                <select class="form-control" name="action_filter">
                    <option value="">Todas as ações</option>
                    <option value="login" <?= $action_filter == 'login' ? 'selected' : '' ?>>Login</option>
                    <option value="create" <?= $action_filter == 'create' ? 'selected' : '' ?>>Criar</option>
                    <option value="edit" <?= $action_filter == 'edit' ? 'selected' : '' ?>>Editar</option>
                    <option value="delete" <?= $action_filter == 'delete' ? 'selected' : '' ?>>Excluir</option>
                    <option value="view" <?= $action_filter == 'view' ? 'selected' : '' ?>>Visualizar</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Data Inicial</label>
                <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Data Final</label>
                <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Logs -->
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5>Registros de Atividades</h5>
        <button class="btn btn-sm btn-outline-danger" onclick="limparLogs()">
            <i class="fas fa-trash"></i> Limpar Logs Antigos
        </button>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Usuário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>IP</th>
                        <th>User Agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = $logs->fetch_assoc()): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                        <td><?= $log['user_name'] ?? 'Sistema' ?></td>
                        <td>
                            <?php
                            $action_colors = [
                                'login' => 'success',
                                'logout' => 'secondary',
                                'create' => 'primary',
                                'edit' => 'warning',
                                'delete' => 'danger',
                                'view' => 'info'
                            ];
                            $color = $action_colors[$log['action']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($log['action']) ?></span>
                        </td>
                        <td>
                            <span class="text-truncate" style="max-width: 200px; display: block;" 
                                  title="<?= htmlspecialchars($log['details']) ?>">
                                <?= htmlspecialchars(substr($log['details'], 0, 50)) ?><?= strlen($log['details']) > 50 ? '...' : '' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td>
                            <span class="text-truncate" style="max-width: 150px; display: block;" 
                                  title="<?= htmlspecialchars($log['user_agent']) ?>">
                                <?= htmlspecialchars(substr($log['user_agent'], 0, 30)) ?><?= strlen($log['user_agent']) > 30 ? '...' : '' ?>
                            </span>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Paginação -->
        <?php if ($total_pages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=logs&log_page=<?= $page - 1 ?>&<?= http_build_query($_GET) ?>">Anterior</a>
                </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                    <a class="page-link" href="?page=logs&log_page=<?= $i ?>&<?= http_build_query($_GET) ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=logs&log_page=<?= $page + 1 ?>&<?= http_build_query($_GET) ?>">Próximo</a>
                </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
function limparLogs() {
    if (confirm('Deseja limpar logs com mais de 90 dias? Esta ação não pode ser desfeita.')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="clean_old_logs">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Logs antigos removidos com sucesso!');
                location.reload();
            } else {
                alert('Erro ao limpar logs');
            }
        });
    }
}
</script>