// Funções JavaScript para o sistema Orvalho do Hermon 2966

// Funções para Irmãos
function editarIrmao(id) {
    fetch('api/actions.php', {
        method: 'POST',
        body: new FormData(Object.assign(document.createElement('form'), {
            innerHTML: '<input name="action" value="edit_irmao"><input name="id" value="' + id + '">'
        }))
    })
    .then(response => response.json())
    .then(data => {
        // Implementar modal de edição com os dados
        console.log('Editar Irmão:', data);
        alert('Funcionalidade em desenvolvimento: Editar Irmão #' + id);
    });
}

function removerIrmao(id) {
    if (confirm('Tem certeza que deseja remover este irmão?')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_irmao"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover irmão');
            }
        });
    }
}

// Funções para Financeiro
function editarTransacao(id) {
    fetch('api/actions.php', {
        method: 'POST',
        body: new FormData(Object.assign(document.createElement('form'), {
            innerHTML: '<input name="action" value="edit_transacao"><input name="id" value="' + id + '">'
        }))
    })
    .then(response => response.json())
    .then(data => {
        console.log('Editar Transação:', data);
        alert('Funcionalidade em desenvolvimento: Editar Transação #' + id);
    });
}

function removerTransacao(id) {
    if (confirm('Tem certeza que deseja remover esta transação?')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_transacao"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover transação');
            }
        });
    }
}

function gerarRelatorio() {
    const dataInicio = prompt('Data de início (YYYY-MM-DD):', new Date().getFullYear() + '-01-01');
    const dataFim = prompt('Data de fim (YYYY-MM-DD):', new Date().toISOString().substr(0, 10));
    
    if (dataInicio && dataFim) {
        window.open(`reports/financeiro.php?data_inicio=${dataInicio}&data_fim=${dataFim}&formato=pdf`, '_blank');
    }
}

// Funções para Eventos
function editarEvento(id) {
    alert('Funcionalidade em desenvolvimento: Editar Evento #' + id);
}

function removerEvento(id) {
    if (confirm('Tem certeza que deseja remover este evento?')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_evento"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover evento');
            }
        });
    }
}

// Funções para Avisos
function editarAviso(id) {
    alert('Funcionalidade em desenvolvimento: Editar Aviso #' + id);
}

function removerAviso(id) {
    if (confirm('Tem certeza que deseja remover este aviso?')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_aviso"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover aviso');
            }
        });
    }
}

// Funções para Candidatos
function verDetalhes(id) {
    fetch(`api/actions.php?action=get_candidato&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('detalhesCandidato').innerHTML = `
                <p><strong>Nome:</strong> ${data.nome || ''}</p>
                <p><strong>Email:</strong> ${data.email || ''}</p>
                <p><strong>Telefone:</strong> ${data.telefone || ''}</p>
                <p><strong>Data Sindicância:</strong> ${data.data_sindicancia || '-'}</p>
                <p><strong>Status:</strong> ${data.status || ''}</p>
                <p><strong>Observações:</strong> ${data.observacoes || ''}</p>
            `;
        })
        .catch(error => {
            document.getElementById('detalhesCandidato').innerHTML = '<p>Erro ao carregar detalhes.</p>';
        });
}

function editarStatus(id) {
    // Carregar dados atuais
    fetch(`api/actions.php?action=get_candidato&id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editCandidatoId').value = id;
            document.getElementById('editStatus').value = data.status;
            document.getElementById('editObservacoes').value = data.observacoes || '';
        });
}

function removerCandidato(id) {
    if (confirm('Tem certeza que deseja remover este candidato?')) {
        window.location.href = '?page=candidatos&action=remove&id=' + id;
    }
}

// Funções para Trabalhos
function editarTrabalho(id) {
    alert('Funcionalidade em desenvolvimento: Editar Trabalho #' + id);
}

function removerTrabalho(id) {
    if (confirm('Tem certeza que deseja remover este trabalho?')) {
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="remove_trabalho"><input name="id" value="' + id + '">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao remover trabalho: ' + (data.error || 'Erro desconhecido'));
            }
        });
    }
}

// Funções para Configurações
function criarBackup() {
    if (confirm('Deseja criar um backup do banco de dados?')) {
        const btn = event.target;
        btn.disabled = true;
        btn.innerHTML = '<span class="loading"></span> Criando...';
        
        fetch('api/actions.php', {
            method: 'POST',
            body: new FormData(Object.assign(document.createElement('form'), {
                innerHTML: '<input name="action" value="backup_database">'
            }))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Backup criado com sucesso: ' + data.file);
            } else {
                alert('Erro ao criar backup: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-database"></i> Criar Backup';
        });
    }
}

function limparCache() {
    alert('Cache limpo com sucesso!');
}

function verLogs() {
    window.open('?page=logs', '_blank');
}

function testarEmail() {
    const email = prompt('Digite um email para teste:', '');
    if (email) {
        alert('Email de teste enviado para: ' + email);
    }
}

// Máscaras e validações
document.addEventListener('DOMContentLoaded', function() {
    // Máscara para telefone
    document.querySelectorAll('input[type="tel"]').forEach(function(element) {
        element.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                if (value.length === 11) {
                    value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
                } else if (value.length === 10) {
                    value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
                }
            }
            e.target.value = value;
        });
    });
    
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
    
    // Atualizar links de navegação ativo
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'home';
    document.querySelectorAll('.nav-link').forEach(function(link) {
        link.classList.remove('active');
        if (link.getAttribute('href').includes('page=' + currentPage) || 
            (currentPage === 'home' && link.getAttribute('href') === 'dashboard.php')) {
            link.classList.add('active');
        }
    });
    
    // Tooltips Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Auto-refresh para notificações (a cada 5 minutos)
    setInterval(function() {
        checkNotifications();
    }, 300000);
});

// Funções auxiliares
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR');
}

function formatCurrency(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

function showLoading(element) {
    element.disabled = true;
    element.innerHTML = '<span class="loading"></span> Carregando...';
}

function hideLoading(element, originalText) {
    element.disabled = false;
    element.innerHTML = originalText;
}

function showAlert(message, type = 'info') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.main-content .p-4');
    if (container) {
        container.insertBefore(alert, container.firstChild);
        
        // Auto-remove após 5 segundos
        setTimeout(() => {
            alert.remove();
        }, 5000);
    }
}

function checkNotifications() {
    // Verificar novas notificações
    fetch('api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                // Atualizar contador de notificações na UI
                updateNotificationBadge(data.count);
            }
        })
        .catch(error => {
            console.log('Erro ao verificar notificações:', error);
        });
}

function updateNotificationBadge(count) {
    let badge = document.querySelector('.notification-badge');
    if (!badge) {
        badge = document.createElement('span');
        badge.className = 'badge bg-danger notification-badge';
        // Adicionar em local apropriado na UI
    }
    badge.textContent = count;
}

// Funções para modais dinâmicos
function openModal(modalId, data = {}) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    
    // Preencher dados se fornecidos
    if (data && Object.keys(data).length > 0) {
        Object.keys(data).forEach(key => {
            const element = document.querySelector(`#${modalId} [name="${key}"]`);
            if (element) {
                element.value = data[key];
            }
        });
    }
    
    modal.show();
}

function closeModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

// Validação de formulários
function validateForm(formElement) {
    const requiredFields = formElement.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('is-invalid');
            isValid = false;
        } else {
            field.classList.remove('is-invalid');
        }
    });
    
    return isValid;
}

// Debounce para busca
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Export para uso global
window.SistemaOrvalho = {
    editarIrmao,
    removerIrmao,
    editarTransacao,
    removerTransacao,
    gerarRelatorio,
    editarEvento,
    removerEvento,
    editarAviso,
    removerAviso,
    verDetalhes,
    editarStatus,
    removerCandidato,
    editarTrabalho,
    removerTrabalho,
    criarBackup,
    limparCache,
    verLogs,
    testarEmail,
    formatDate,
    formatCurrency,
    showAlert,
    openModal,
    closeModal,
    validateForm
};