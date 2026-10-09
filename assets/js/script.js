/**
 * Script JS principal do Sistema de Gestão do Fundo Social
 * Para uso com XAMPP
 */

/**
 * Função para mostrar alertas
 */
function showAlert(message, type = 'success', duration = 3000) {
    const alertContainer = document.createElement('div');
    alertContainer.className = `alert alert-${type} animate`;
    alertContainer.textContent = message;
    
    // Inserir no corpo
    document.body.appendChild(alertContainer);
    
    // Remover após o intervalo
    setTimeout(() => {
        alertContainer.style.opacity = '0';
        alertContainer.style.transition = 'opacity 0.5s';
        setTimeout(() => {
            if (alertContainer.parentNode) {
                alertContainer.parentNode.removeChild(alertContainer);
            }
        }, 500);
    }, duration);
}

/**
 * Função para abrir modal
 */
function openModal(modalId) {
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

/**
 * Função para fechar modal
 */
function closeModal(modalId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
    if (modal) {
        modal.hide();
    }
}

/**
 * Função para deletar registro
 */
async function confirmDelete(url, message = 'Tem certeza que deseja excluir este registro?') {
    const confirmed = confirm(message);
    if (confirmed) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            
            if (response.ok) {
                showAlert('Registro excluído com sucesso!', 'success');
                location.reload();
            } else {
                const result = await response.json();
                showAlert(result.message || 'Erro ao excluir registro.', 'error');
            }
        } catch (error) {
            console.error('Erro:', error);
            showAlert('Erro de conexão. Tente novamente.', 'error');
        }
    }
}

/**
 * Função para verificar se é número
 */
function isNumeric(val) {
    return !isNaN(parseFloat(val)) && isFinite(val);
}

/**
 * Inicialização do sistema
 */
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar modals
    const modalList = [].slice.call(document.querySelectorAll('.modal'));
    modalList.forEach(function(modalEl) {
        new bootstrap.Modal(modalEl);
    });
});
