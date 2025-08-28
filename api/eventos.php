<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$eventos = $conn->query("SELECT id, titulo as title, data_evento as start, 
                        CONCAT(data_evento, 'T', hora_evento) as start,
                        tipo, local FROM eventos WHERE ativo = 1");

$calendar_events = [];
while ($evento = $eventos->fetch_assoc()) {
    $color = '#2a5298'; // Cor padrão
    
    // Definir cores por tipo de evento
    switch($evento['tipo']) {
        case 'loja':
            $color = '#2a5298';
            break;
        case 'evento':
            $color = '#28a745';
            break;
        case 'palestra':
            $color = '#17a2b8';
            break;
        case 'beneficente':
            $color = '#ffc107';
            break;
        case 'social':
            $color = '#6f42c1';
            break;
    }
    
    $calendar_events[] = [
        'id' => $evento['id'],
        'title' => $evento['title'],
        'start' => $evento['start'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff'
    ];
}

echo json_encode($calendar_events);
?>