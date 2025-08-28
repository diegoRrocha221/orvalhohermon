<?php
require_once '../includes/functions.php';
require_once '../config/database.php';
checkLogin();
checkPermission(4); // Apenas administradores

$db = new Database();
$conn = $db->getConnection();

$type = $_GET['type'] ?? 'all';
$format = $_GET['format'] ?? 'csv';

// Definir nome do arquivo
$filename = 'export_orvalho_hermon_2966_' . date('Y-m-d_H-i-s');

switch ($format) {
    case 'csv':
        exportCSV($conn, $type, $filename);
        break;
    case 'json':
        exportJSON($conn, $type, $filename);
        break;
    case 'xml':
        exportXML($conn, $type, $filename);
        break;
    case 'excel':
        exportExcel($conn, $type, $filename);
        break;
    default:
        exportCSV($conn, $type, $filename);
}

function exportCSV($conn, $type, $filename) {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    switch ($type) {
        case 'irmaos':
            exportIrmaosCSV($conn, $output);
            break;
        case 'financeiro':
            exportFinanceiroCSV($conn, $output);
            break;
        case 'eventos':
            exportEventosCSV($conn, $output);
            break;
        case 'candidatos':
            exportCandidatosCSV($conn, $output);
            break;
        case 'trabalhos':
            exportTrabalhosCSV($conn, $output);
            break;
        case 'all':
        default:
            exportAllDataCSV($conn, $output);
            break;
    }
    
    fclose($output);
}

function exportIrmaosCSV($conn, $output) {
    // Cabeçalho
    fputcsv($output, [
        'ID', 'Nome', 'Email', 'Grau Simbólico', 'Nível de Acesso', 
        'Telefone', 'Data de Iniciação', 'Data de Cadastro', 'Último Login', 'Status'
    ]);
    
    // Dados
    $result = $conn->query("SELECT id, nome, email, grau, nivel_acesso, telefone, 
                           data_iniciacao, data_cadastro, ultimo_login, 
                           CASE WHEN ativo = 1 THEN 'Ativo' ELSE 'Inativo' END as status
                           FROM usuarios ORDER BY nome");
    
    while ($row = $result->fetch_assoc()) {
        $row['grau'] = $row['grau'] . '° - ' . getNivelNome($row['grau']);
        $row['nivel_acesso'] = getNivelNome($row['nivel_acesso']);
        $row['data_iniciacao'] = $row['data_iniciacao'] ? date('d/m/Y', strtotime($row['data_iniciacao'])) : '';
        $row['data_cadastro'] = date('d/m/Y H:i:s', strtotime($row['data_cadastro']));
        $row['ultimo_login'] = $row['ultimo_login'] ? date('d/m/Y H:i:s', strtotime($row['ultimo_login'])) : 'Nunca';
        
        fputcsv($output, $row);
    }
}

function exportFinanceiroCSV($conn, $output) {
    // Cabeçalho
    fputcsv($output, [
        'ID', 'Tipo', 'Valor', 'Descrição', 'Categoria', 
        'Data da Transação', 'Data de Cadastro', 'Usuário Responsável'
    ]);
    
    // Dados
    $result = $conn->query("SELECT f.id, f.tipo, f.valor, f.descricao, f.categoria,
                           f.data_transacao, f.data_cadastro, u.nome as usuario_nome
                           FROM financeiro f
                           LEFT JOIN usuarios u ON f.usuario_id = u.id
                           ORDER BY f.data_transacao DESC");
    
    while ($row = $result->fetch_assoc()) {
        $row['tipo'] = ucfirst($row['tipo']);
        $row['valor'] = 'R$ ' . number_format($row['valor'], 2, ',', '.');
        $row['data_transacao'] = date('d/m/Y', strtotime($row['data_transacao']));
        $row['data_cadastro'] = date('d/m/Y H:i:s', strtotime($row['data_cadastro']));
        
        fputcsv($output, $row);
    }
}

function exportEventosCSV($conn, $output) {
    // Cabeçalho
    fputcsv($output, [
        'ID', 'Título', 'Descrição', 'Tipo', 'Data do Evento', 
        'Hora', 'Local', 'Data de Criação', 'Criado por', 'Status'
    ]);
    
    // Dados
    $result = $conn->query("SELECT e.id, e.titulo, e.descricao, e.tipo, e.data_evento,
                           e.hora_evento, e.local, e.data_criacao, u.nome as criador,
                           CASE WHEN e.ativo = 1 THEN 'Ativo' ELSE 'Inativo' END as status
                           FROM eventos e
                           LEFT JOIN usuarios u ON e.criado_por = u.id
                           ORDER BY e.data_evento DESC");
    
    while ($row = $result->fetch_assoc()) {
        $row['data_evento'] = date('d/m/Y', strtotime($row['data_evento']));
        $row['data_criacao'] = date('d/m/Y H:i:s', strtotime($row['data_criacao']));
        
        fputcsv($output, $row);
    }
}

function exportCandidatosCSV($conn, $output) {
    // Cabeçalho
    fputcsv($output, [
        'ID', 'Nome', 'Email', 'Telefone', 'Data da Sindicância', 
        'Status', 'Observações', 'Data de Cadastro', 'Cadastrado por'
    ]);
    
    // Dados
    $result = $conn->query("SELECT c.id, c.nome, c.email, c.telefone, c.data_sindicancia,
                           c.status, c.observacoes, c.data_cadastro, u.nome as cadastrado_por_nome
                           FROM candidatos c
                           LEFT JOIN usuarios u ON c.cadastrado_por = u.id
                           ORDER BY c.data_cadastro DESC");
    
    while ($row = $result->fetch_assoc()) {
        $row['status'] = ucfirst($row['status']);
        $row['data_sindicancia'] = $row['data_sindicancia'] ? date('d/m/Y', strtotime($row['data_sindicancia'])) : '';
        $row['data_cadastro'] = date('d/m/Y H:i:s', strtotime($row['data_cadastro']));
        
        fputcsv($output, $row);
    }
}

function exportTrabalhosCSV($conn, $output) {
    // Cabeçalho
    fputcsv($output, [
        'ID', 'Título', 'Descrição', 'Grau de Acesso', 'Arquivo', 
        'Data de Upload', 'Autor', 'Status'
    ]);
    
    // Dados
    $result = $conn->query("SELECT t.id, t.titulo, t.descricao, t.grau_acesso, t.arquivo,
                           t.data_upload, u.nome as autor_nome,
                           CASE WHEN t.ativo = 1 THEN 'Ativo' ELSE 'Inativo' END as status
                           FROM trabalhos t
                           LEFT JOIN usuarios u ON t.autor_id = u.id
                           ORDER BY t.data_upload DESC");
    
    while ($row = $result->fetch_assoc()) {
        $row['grau_acesso'] = $row['grau_acesso'] . '° Grau';
        $row['data_upload'] = date('d/m/Y H:i:s', strtotime($row['data_upload']));
        
        fputcsv($output, $row);
    }
}

function exportAllDataCSV($conn, $output) {
    // Exportar resumo de todas as tabelas
    fputcsv($output, ['RELATÓRIO GERAL - LOJA ORVALHO DO HERMON 2966']);
    fputcsv($output, ['Gerado em: ' . date('d/m/Y H:i:s')]);
    fputcsv($output, ['Usuário: ' . $_SESSION['user_name']]);
    fputcsv($output, []);
    
    // Estatísticas gerais
    fputcsv($output, ['ESTATÍSTICAS GERAIS']);
    fputcsv($output, ['Descrição', 'Quantidade']);
    
    $stats = [
        'Total de Irmãos Ativos' => $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE ativo = 1")->fetch_assoc()['count'],
        'Total de Irmãos Inativos' => $conn->query("SELECT COUNT(*) as count FROM usuarios WHERE ativo = 0")->fetch_assoc()['count'],
        'Total de Transações Financeiras' => $conn->query("SELECT COUNT(*) as count FROM financeiro")->fetch_assoc()['count'],
        'Total de Eventos' => $conn->query("SELECT COUNT(*) as count FROM eventos WHERE ativo = 1")->fetch_assoc()['count'],
        'Total de Candidatos' => $conn->query("SELECT COUNT(*) as count FROM candidatos")->fetch_assoc()['count'],
        'Total de Trabalhos Publicados' => $conn->query("SELECT COUNT(*) as count FROM trabalhos WHERE ativo = 1")->fetch_assoc()['count']
    ];
    
    foreach ($stats as $desc => $count) {
        fputcsv($output, [$desc, $count]);
    }
    
    fputcsv($output, []);
    
    // Resumo financeiro
    fputcsv($output, ['RESUMO FINANCEIRO']);
    fputcsv($output, ['Tipo', 'Valor Total']);
    
    $financeiro = $conn->query("SELECT tipo, SUM(valor) as total FROM financeiro GROUP BY tipo");
    while ($row = $financeiro->fetch_assoc()) {
        fputcsv($output, [ucfirst($row['tipo']), 'R$ ' . number_format($row['total'], 2, ',', '.')]);
    }
    
    $saldo = $conn->query("SELECT (SELECT SUM(valor) FROM financeiro WHERE tipo = 'entrada') - (SELECT SUM(valor) FROM financeiro WHERE tipo = 'saida') as saldo")->fetch_assoc()['saldo'];
    fputcsv($output, ['Saldo Atual', 'R$ ' . number_format($saldo, 2, ',', '.')]);
    
    fputcsv($output, []);
    
    // Distribuição por graus
    fputcsv($output, ['DISTRIBUIÇÃO POR GRAUS']);
    fputcsv($output, ['Grau', 'Quantidade']);
    
    $graus = $conn->query("SELECT grau, COUNT(*) as count FROM usuarios WHERE ativo = 1 GROUP BY grau ORDER BY grau");
    while ($row = $graus->fetch_assoc()) {
        $grau_nome = $row['grau'] . '° - ' . getNivelNome($row['grau']);
        fputcsv($output, [$grau_nome, $row['count']]);
    }
}

function exportJSON($conn, $type, $filename) {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
    
    $data = [];
    
    switch ($type) {
        case 'irmaos':
            $data = exportIrmaosJSON($conn);
            break;
        case 'financeiro':
            $data = exportFinanceiroJSON($conn);
            break;
        case 'eventos':
            $data = exportEventosJSON($conn);
            break;
        case 'candidatos':
            $data = exportCandidatosJSON($conn);
            break;
        case 'trabalhos':
            $data = exportTrabalhosJSON($conn);
            break;
        case 'all':
        default:
            $data = exportAllDataJSON($conn);
            break;
    }
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportIrmaosJSON($conn) {
    $result = $conn->query("SELECT * FROM usuarios ORDER BY nome");
    $irmaos = [];
    
    while ($row = $result->fetch_assoc()) {
        $irmaos[] = $row;
    }
    
    return [
        'export_info' => [
            'table' => 'usuarios',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'total_records' => count($irmaos)
        ],
        'data' => $irmaos
    ];
}

function exportFinanceiroJSON($conn) {
    $result = $conn->query("SELECT f.*, u.nome as usuario_nome FROM financeiro f LEFT JOIN usuarios u ON f.usuario_id = u.id ORDER BY f.data_transacao DESC");
    $transacoes = [];
    
    while ($row = $result->fetch_assoc()) {
        $transacoes[] = $row;
    }
    
    return [
        'export_info' => [
            'table' => 'financeiro',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'total_records' => count($transacoes)
        ],
        'data' => $transacoes
    ];
}

function exportEventosJSON($conn) {
    $result = $conn->query("SELECT e.*, u.nome as criador FROM eventos e LEFT JOIN usuarios u ON e.criado_por = u.id ORDER BY e.data_evento DESC");
    $eventos = [];
    
    while ($row = $result->fetch_assoc()) {
        $eventos[] = $row;
    }
    
    return [
        'export_info' => [
            'table' => 'eventos',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'total_records' => count($eventos)
        ],
        'data' => $eventos
    ];
}

function exportCandidatosJSON($conn) {
    $result = $conn->query("SELECT c.*, u.nome as cadastrado_por_nome FROM candidatos c LEFT JOIN usuarios u ON c.cadastrado_por = u.id ORDER BY c.data_cadastro DESC");
    $candidatos = [];
    
    while ($row = $result->fetch_assoc()) {
        $candidatos[] = $row;
    }
    
    return [
        'export_info' => [
            'table' => 'candidatos',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'total_records' => count($candidatos)
        ],
        'data' => $candidatos
    ];
}

function exportTrabalhosJSON($conn) {
    $result = $conn->query("SELECT t.*, u.nome as autor_nome FROM trabalhos t LEFT JOIN usuarios u ON t.autor_id = u.id ORDER BY t.data_upload DESC");
    $trabalhos = [];
    
    while ($row = $result->fetch_assoc()) {
        $trabalhos[] = $row;
    }
    
    return [
        'export_info' => [
            'table' => 'trabalhos',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'total_records' => count($trabalhos)
        ],
        'data' => $trabalhos
    ];
}

function exportAllDataJSON($conn) {
    return [
        'export_info' => [
            'type' => 'complete_export',
            'export_date' => date('Y-m-d H:i:s'),
            'exported_by' => $_SESSION['user_name'],
            'loja' => 'Orvalho do Hermon 2966'
        ],
        'irmaos' => exportIrmaosJSON($conn)['data'],
        'financeiro' => exportFinanceiroJSON($conn)['data'],
        'eventos' => exportEventosJSON($conn)['data'],
        'candidatos' => exportCandidatosJSON($conn)['data'],
        'trabalhos' => exportTrabalhosJSON($conn)['data']
    ];
}

function exportXML($conn, $type, $filename) {
    header('Content-Type: text/xml; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.xml\"");
    
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><export></export>');
    
    $info = $xml->addChild('export_info');
    $info->addChild('export_date', date('Y-m-d H:i:s'));
    $info->addChild('exported_by', htmlspecialchars($_SESSION['user_name']));
    $info->addChild('loja', 'Orvalho do Hermon 2966');
    $info->addChild('type', $type);
    
    switch ($type) {
        case 'all':
            exportAllDataXML($conn, $xml);
            break;
        default:
            // Implementar outros tipos se necessário
            $data = exportAllDataJSON($conn);
            arrayToXML($data, $xml);
            break;
    }
    
    echo $xml->asXML();
}

function exportAllDataXML($conn, $xml) {
    // Adicionar dados de cada tabela
    $tables = ['usuarios', 'financeiro', 'eventos', 'candidatos', 'trabalhos'];
    
    foreach ($tables as $table) {
        $tableNode = $xml->addChild($table);
        $result = $conn->query("SELECT * FROM $table");
        
        while ($row = $result->fetch_assoc()) {
            $recordNode = $tableNode->addChild('record');
            foreach ($row as $key => $value) {
                $recordNode->addChild($key, htmlspecialchars($value));
            }
        }
    }
}

function arrayToXML($array, &$xml) {
    foreach ($array as $key => $value) {
        if (is_array($value)) {
            $subnode = $xml->addChild($key);
            arrayToXML($value, $subnode);
        } else {
            $xml->addChild($key, htmlspecialchars($value));
        }
    }
}
?>