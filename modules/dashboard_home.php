<div class="row mb-4">
    <div class="col-md-12">
        <h2>Dashboard Principal</h2>
        <p class="text-muted">Bem-vindo ao sistema de gerenciamento da Loja Orvalho do Hermon 2966</p>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Total de Irmãos</h6>
                        <h2><?= $stats['total_irmaos'] ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-users fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Eventos este Mês</h6>
                        <h2><?= $stats['eventos_mes'] ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-calendar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
    <div class="col-md-4">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="card-title">Saldo da Loja</h6>
                        <h2><?= formatMoney($stats['saldo']) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="fas fa-coins fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-calendar"></i> Próximos Eventos</h5>
            </div>
            <div class="card-body">
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bullhorn"></i> Avisos Recentes</h5>
            </div>
            <div class="card-body">
                <?php
                $avisos = $conn->query("SELECT titulo, conteudo, data_criacao FROM avisos WHERE ativo = 1 ORDER BY data_criacao DESC LIMIT 5");
                while ($aviso = $avisos->fetch_assoc()):
                ?>
                <div class="alert alert-info">
                    <h6><?= htmlspecialchars($aviso['titulo']) ?></h6>
                    <p><?= substr(htmlspecialchars($aviso['conteudo']), 0, 100) ?>...</p>
                    <small class="text-muted"><?= date('d/m/Y', strtotime($aviso['data_criacao'])) ?></small>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'pt-br',
        events: 'api/eventos.php'
    });
    calendar.render();
});
</script>