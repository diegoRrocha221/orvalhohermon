<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
checkLogin();
checkPermission(3);

$db = new Database();
$conn = $db->getConnection();

$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-t');

// Buscar dados financeiros
$entradas = $conn->query("SELECT * FROM financeiro WHERE tipo = 'entrada' AND data_transacao BETWEEN '$data_inicio' AND '$data_fim' ORDER BY data_transacao");
$saidas = $conn->query("SELECT * FROM financeiro WHERE tipo = 'saida' AND data_transacao BETWEEN '$data_inicio' AND '$data_fim' ORDER BY data_transacao");

$total_entradas = $conn->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'entrada' AND data_transacao BETWEEN '$data_inicio' AND '$data_fim'")->fetch_assoc()['total'] ?? 0;
$total_saidas = $conn->query("SELECT SUM(valor) as total FROM financeiro WHERE tipo = 'saida' AND data_transacao BETWEEN '$data_inicio' AND '$data_fim'")->fetch_assoc()['total'] ?? 0;

// Relatório por categoria
$categorias = $conn->query("SELECT categoria, 
    SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas,
    SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas
    FROM financeiro 
    WHERE data_transacao BETWEEN '$data_inicio' AND '$data_fim' 
    GROUP BY categoria");

if (isset($_GET['formato']) && $_GET['formato'] == 'pdf') {
    // Gerar PDF
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .header { text-align: center; margin-bottom: 30px; }
            .summary { background-color: #f8f9fa; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
            .logo { text-align: center; margin-bottom: 20px; }
            .total-row { font-weight: bold; background-color: #e9ecef; }
            .positive { color: #28a745; }
            .negative { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="logo">
            <h1>🏛️ LOJA MAÇÔNICA ORVALHO DO HERMON 2966</h1>
        </div>
        
        <div class="header">
            <h2>RELATÓRIO FINANCEIRO</h2>
            <p><strong>Período:</strong> <?= date('d/m/Y', strtotime($data_inicio)) ?> a <?= date('d/m/Y', strtotime($data_fim)) ?></p>
            <p><strong>Gerado em:</strong> <?= date('d/m/Y H:i:s') ?></p>
        </div>
        
        <div class="summary">
            <h3>RESUMO EXECUTIVO</h3>
            <table>
                <tr>
                    <td><strong>Total de Entradas:</strong></td>
                    <td class="positive"><?= formatMoney($total_entradas) ?></td>
                </tr>
                <tr>
                    <td><strong>Total de Saídas:</strong></td>
                    <td class="negative"><?= formatMoney($total_saidas) ?></td>
                </tr>
                <tr class="total-row">
                    <td><strong>Saldo do Período:</strong></td>
                    <td class="<?= ($total_entradas - $total_saidas) >= 0 ? 'positive' : 'negative' ?>">
                        <?= formatMoney($total_entradas - $total_saidas) ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <h3>DETALHAMENTO DAS ENTRADAS</h3>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $entradas->data_seek(0);
                $subtotal_entradas = 0;
                while ($entrada = $entradas->fetch_assoc()): 
                    $subtotal_entradas += $entrada['valor'];
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($entrada['data_transacao'])) ?></td>
                    <td><?= htmlspecialchars($entrada['descricao']) ?></td>
                    <td><?= htmlspecialchars($entrada['categoria']) ?></td>
                    <td><?= formatMoney($entrada['valor']) ?></td>
                </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL DAS ENTRADAS</strong></td>
                    <td><strong><?= formatMoney($subtotal_entradas) ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <h3>DETALHAMENTO DAS SAÍDAS</h3>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Descrição</th>
                    <th>Categoria</th>
                    <th>Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $saidas->data_seek(0);
                $subtotal_saidas = 0;
                while ($saida = $saidas->fetch_assoc()): 
                    $subtotal_saidas += $saida['valor'];
                ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($saida['data_transacao'])) ?></td>
                    <td><?= htmlspecialchars($saida['descricao']) ?></td>
                    <td><?= htmlspecialchars($saida['categoria']) ?></td>
                    <td><?= formatMoney($saida['valor']) ?></td>
                </tr>
                <?php endwhile; ?>
                <tr class="total-row">
                    <td colspan="3"><strong>TOTAL DAS SAÍDAS</strong></td>
                    <td><strong><?= formatMoney($subtotal_saidas) ?></strong></td>
                </tr>
            </tbody>
        </table>
        
        <h3>ANÁLISE POR CATEGORIA</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Entradas</th>
                    <th>Saídas</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($categoria = $categorias->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($categoria['categoria']) ?></td>
                    <td class="positive"><?= formatMoney($categoria['entradas']) ?></td>
                    <td class="negative"><?= formatMoney($categoria['saidas']) ?></td>
                    <td class="<?= ($categoria['entradas'] - $categoria['saidas']) >= 0 ? 'positive' : 'negative' ?>">
                        <?= formatMoney($categoria['entradas'] - $categoria['saidas']) ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <div style="margin-top: 50px; text-align: center; font-size: 12px; color: #666;">
            <hr>
            <p><strong>Relatório gerado por:</strong> <?= $_SESSION['user_name'] ?></p>
            <p><strong>Sistema de Gerenciamento - Loja Orvalho do Hermon 2966</strong></p>
            <p>Este documento é confidencial e destinado exclusivamente aos membros da loja.</p>
        </div>
    </body>
    </html>
    <?php
    
    $html = ob_get_clean();
    
    // Headers para download do PDF
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: inline; filename="relatorio_financeiro_' . date('Y-m-d') . '.html"');
    
    echo $html;
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro - Orvalho do Hermon 2966</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-12">
                <h2>
                    <i class="fas fa-chart-line"></i> Relatório Financeiro
                    <small class="text-muted">- Loja Orvalho do Hermon 2966</small>
                </h2>
                
                <form method="GET" class="row g-3 mt-3">
                    <div class="col-md-3">
                        <label class="form-label">Data Início</label>
                        <input type="date" class="form-control" name="data_inicio" value="<?= $data_inicio ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Data Fim</label>
                        <input type="date" class="form-control" name="data_fim" value="<?= $data_fim ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Atualizar
                            </button>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <a href="?<?= http_build_query(array_merge($_GET, ['formato' => 'pdf'])) ?>" 
                               class="btn btn-danger" target="_blank">
                                <i class="fas fa-file-pdf"></i> Gerar PDF
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Cards de Resumo -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Entradas</h6>
                                <h3><?= formatMoney($total_entradas) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-arrow-up fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Total Saídas</h6>
                                <h3><?= formatMoney($total_saidas) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-arrow-down fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card text-white <?= ($total_entradas - $total_saidas) >= 0 ? 'bg-primary' : 'bg-warning' ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Saldo do Período</h6>
                                <h3><?= formatMoney($total_entradas - $total_saidas) ?></h3>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-balance-scale fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Gráfico -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5>Análise por Categoria</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="chartFinanceiro" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Botão Voltar -->
        <div class="row">
            <div class="col-md-12">
                <a href="../dashboard.php?page=financeiro" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Voltar ao Financeiro
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    // Gráfico financeiro por categoria
    const ctx = document.getElementById('chartFinanceiro').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: [<?php 
                $categorias->data_seek(0);
                $labels = [];
                while ($cat = $categorias->fetch_assoc()) {
                    $labels[] = "'" . addslashes($cat['categoria']) . "'";
                }
                echo implode(',', $labels);
            ?>],
            datasets: [{
                label: 'Entradas',
                data: [<?php 
                    $categorias->data_seek(0);
                    $entradas_data = [];
                    while ($cat = $categorias->fetch_assoc()) {
                        $entradas_data[] = $cat['entradas'];
                    }
                    echo implode(',', $entradas_data);
                ?>],
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }, {
                label: 'Saídas',
                data: [<?php 
                    $categorias->data_seek(0);
                    $saidas_data = [];
                    while ($cat = $categorias->fetch_assoc()) {
                        $saidas_data[] = $cat['saidas'];
                    }
                    echo implode(',', $saidas_data);
                ?>],
                backgroundColor: 'rgba(220, 53, 69, 0.8)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                title: {
                    display: true,
                    text: 'Entradas vs Saídas por Categoria'
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                }
            }
        }
    });
    </script>
</body>
</html>