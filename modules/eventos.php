<?php
if ($_POST && $_POST['action'] == 'add') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $data_evento = $_POST['data_evento'];
    $hora_evento = $_POST['hora_evento'];
    $tipo = $_POST['tipo'];
    $local = $_POST['local'];
    
    $stmt = $conn->prepare("INSERT INTO eventos (titulo, descricao, data_evento, hora_evento, tipo, local, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $titulo, $descricao, $data_evento, $hora_evento, $tipo, $local, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        echo '<div class="alert alert-success">Evento cadastrado com sucesso!</div>';
        
        // Enviar notificações por email
        if (isset($_POST['notificar_irmaos'])) {
            $irmaos = $conn->query("SELECT email, nome FROM usuarios WHERE ativo = 1");
            while ($irmao = $irmaos->fetch_assoc()) {
                $subject = "Novo Evento: " . $titulo;
                $body = "
                <h3>Novo Evento Agendado</h3>
                <p><strong>Título:</strong> $titulo</p>
                <p><strong>Data:</strong> " . date('d/m/Y', strtotime($data_evento)) . " às $hora_evento</p>
                <p><strong>Local:</strong> $local</p>
                <p><strong>Descrição:</strong> $descricao</p>
                ";
                sendEmail($irmao['email'], $subject, $body);
            }
        }
    }
}

$eventos = $conn->query("SELECT e.*, u.nome as criador FROM eventos e 
                        LEFT JOIN usuarios u ON e.criado_por = u.id 
                        ORDER BY e.data_evento DESC");
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2>Gestão de Eventos</h2>
        <?php if ($_SESSION['nivel_acesso'] >= 2): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddEvento">
            <i class="fas fa-plus"></i> Novo Evento
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5>Lista de Eventos</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Data</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Local</th>
                        <th>Criado por</th>
                        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($evento = $eventos->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($evento['titulo']) ?></td>
                        <td><?= date('d/m/Y', strtotime($evento['data_evento'])) ?></td>
                        <td><?= $evento['hora_evento'] ?></td>
                        <td>
                            <span class="badge bg-info"><?= htmlspecialchars($evento['tipo']) ?></span>
                        </td>
                        <td><?= htmlspecialchars($evento['local']) ?></td>
                        <td><?= htmlspecialchars($evento['criador']) ?></td>
                        <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                        <td>
                            <button class="btn btn-sm btn-warning" onclick="editarEvento(<?= $evento['id'] ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="removerEvento(<?= $evento['id'] ?>)">
                                <i class="fas fa-trash"></i>
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

<?php if ($_SESSION['nivel_acesso'] >= 2): ?>
<!-- Modal Novo Evento -->
<div class="modal fade" id="modalAddEvento" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Novo Evento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label">Título do Evento</label>
                                <input type="text" class="form-control" name="titulo" required>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Tipo</label>
                                <select class="form-control" name="tipo" required>
                                    <option value="loja">Reunião de Loja</option>
                                    <option value="evento">Evento Especial</option>
                                    <option value="palestra">Palestra</option>
                                    <option value="beneficente">Ação Beneficente</option>
                                    <option value="social">Evento Social</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Data do Evento</label>
                                <input type="date" class="form-control" name="data_evento" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Horário</label>
                                <input type="time" class="form-control" name="hora_evento" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Local</label>
                        <input type="text" class="form-control" name="local" placeholder="Ex: Templo da Loja, Salão de Eventos...">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="notificar_irmaos" id="notificar_irmaos" checked>
                        <label class="form-check-label" for="notificar_irmaos">
                            Notificar todos os irmãos por email
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Cadastrar Evento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('calendar')) {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            locale: 'pt-br',
            events: 'api/eventos.php'
        });
        calendar.render();
    }
});
</script>