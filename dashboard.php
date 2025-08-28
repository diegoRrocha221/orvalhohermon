<?php
require_once 'includes/functions.php';
require_once 'config/database.php';
checkLogin();

$db = new Database();
$conn = $db->getConnection();

// Buscar estatísticas
$stats = [];
$stats['total_irmaos'] = $conn->query("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1")->fetch_assoc()['total'];
$stats['eventos_mes'] = $conn->query("SELECT COUNT(*) as total FROM eventos WHERE MONTH(data_evento) = MONTH(CURDATE())")->fetch_assoc()['total'];
$stats['saldo'] = $conn->query("SELECT (SELECT COALESCE(SUM(valor), 0) FROM financeiro WHERE tipo = 'entrada') - (SELECT COALESCE(SUM(valor), 0) FROM financeiro WHERE tipo = 'saida') as saldo")->fetch_assoc()['saldo'];

// Verificar se deve mostrar logo
$logo_config = $conn->query("SELECT valor FROM configuracoes WHERE chave = 'logo_url'")->fetch_assoc();
$logo_url = $logo_config['valor'] ?? '';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Orvalho do Hermon 2966</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
</head>
<body>
    <!-- Botão toggle para mobile -->
    <button class="sidebar-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar" id="sidebar">
                <div class="p-3 text-white">
                    <?php if ($logo_url): ?>
                        <div class="text-center mb-3">
                            <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" class="img-fluid" style="max-height: 80px;">
                        </div>
                        <h6 class="text-center">Orvalho do Hermon 2966</h6>
                    <?php else: ?>
                        <h5><i class="fas fa-chess-rook"></i> Orvalho do Hermon 2966</h5>
                    <?php endif; ?>
                    <small><?= getNivelNome($_SESSION['nivel_acesso']) ?> - <?= $_SESSION['user_name'] ?></small>
                </div>
                
                <nav class="nav flex-column px-3">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                    
                    <a class="nav-link" href="?page=minha_conta">
                        <i class="fas fa-user-circle"></i> Minha Conta
                    </a>
                    
                    <?php if ($_SESSION['nivel_acesso'] >= 2): ?>
                    <a class="nav-link" href="?page=irmaos">
                        <i class="fas fa-users"></i> Irmãos
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                    <a class="nav-link" href="?page=financeiro">
                        <i class="fas fa-coins"></i> Financeiro
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link" href="?page=eventos">
                        <i class="fas fa-calendar"></i> Eventos
                    </a>
                    
                    <a class="nav-link" href="?page=avisos">
                        <i class="fas fa-bullhorn"></i> Avisos
                    </a>
                    
                    <a class="nav-link" href="?page=atas">
                        <i class="fas fa-file-alt"></i> Atas
                    </a>
                    
                    <?php if ($_SESSION['nivel_acesso'] >= 3): ?>
                    <a class="nav-link" href="?page=candidatos">
                        <i class="fas fa-user-plus"></i> Candidatos
                    </a>
                    <?php endif; ?>
                    
                    <a class="nav-link" href="?page=trabalhos">
                        <i class="fas fa-book"></i> Acervo Digital
                    </a>
                    
                    <?php if ($_SESSION['nivel_acesso'] >= 4): ?>
                    <hr class="border-light">
                    <a class="nav-link" href="?page=configuracoes">
                        <i class="fas fa-cog"></i> Configurações
                    </a>
                    
                    <a class="nav-link" href="?page=logs">
                        <i class="fas fa-list-alt"></i> Logs
                    </a>
                    <?php endif; ?>
                    
                    <hr class="border-light">
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> Sair
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <div class="p-4">
                    <!-- Notificações -->
                    <div id="notification-area"></div>
                    
                    <?php
                    $page = $_GET['page'] ?? 'home';
                    
                    switch($page) {
                        case 'home':
                        default:
                            include 'modules/dashboard_home.php';
                            break;
                        case 'minha_conta':
                            include 'modules/minha_conta.php';
                            break;
                        case 'irmaos':
                            if ($_SESSION['nivel_acesso'] >= 2) {
                                include 'modules/irmaos.php';
                            } else {
                                echo '<div class="alert alert-danger">Acesso negado!</div>';
                            }
                            break;
                        case 'financeiro':
                            if ($_SESSION['nivel_acesso'] >= 3) {
                                include 'modules/financeiro.php';
                            } else {
                                echo '<div class="alert alert-danger">Acesso negado!</div>';
                            }
                            break;
                        case 'eventos':
                            include 'modules/eventos.php';
                            break;
                        case 'avisos':
                            include 'modules/avisos.php';
                            break;
                        case 'atas':
                            include 'modules/atas.php';
                            break;
                        case 'candidatos':
                            if ($_SESSION['nivel_acesso'] >= 3) {
                                include 'modules/candidatos.php';
                            } else {
                                echo '<div class="alert alert-danger">Acesso negado!</div>';
                            }
                            break;
                        case 'trabalhos':
                            include 'modules/trabalhos.php';
                            break;
                        case 'configuracoes':
                            if ($_SESSION['nivel_acesso'] >= 4) {
                                include 'modules/configuracoes.php';
                            } else {
                                echo '<div class="alert alert-danger">Acesso negado!</div>';
                            }
                            break;
                        case 'logs':
                            if ($_SESSION['nivel_acesso'] >= 4) {
                                include 'modules/logs.php';
                            } else {
                                echo '<div class="alert alert-danger">Acesso negado!</div>';
                            }
                            break;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="assets/js/sistema.js"></script>
    
    <script>
    // Função para toggle do sidebar no mobile
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.toggle('show');
    }
    
    // Fechar sidebar ao clicar fora (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.querySelector('.sidebar-toggle');
        
        if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
    
    // Atualizar navegação ativa
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
        
        document.querySelectorAll('.nav-link').forEach(function(link) {
            link.classList.remove('active');
            
            if (link.getAttribute('href') === 'dashboard.php' && currentPage === 'home') {
                link.classList.add('active');
            } else if (link.getAttribute('href').includes('page=' + currentPage)) {
                link.classList.add('active');
            }
        });
        
        // Carregar notificações
        loadNotifications();
    });
    
    // Carregar notificações
    function loadNotifications() {
        fetch('api/notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.unread_count > 0) {
                    const notificationArea = document.getElementById('notification-area');
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-info alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="fas fa-bell"></i> Você tem ${data.unread_count} notificação(ões) não lida(s).
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    notificationArea.appendChild(alertDiv);
                }
            })
            .catch(error => console.log('Erro ao carregar notificações:', error));
    }
    
    // Verificar notificações a cada 5 minutos
    setInterval(loadNotifications, 300000);
    </script>
</body>
</html>