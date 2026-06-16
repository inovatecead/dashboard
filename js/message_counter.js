/**
 * PrimeiraPagina Pro - Auto-refresh message counter
 * Updates unread message count every 30 seconds without page reload
 */

document.addEventListener('DOMContentLoaded', function() {
    // Função para atualizar contador de mensagens
    function updateMessageCounter() {
        fetch(M.cfg.wwwroot + '/local/dashboard/ajax/messages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                sesskey: M.cfg.sesskey
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const counterElement = document.querySelector('.pp-kpi');
                if (counterElement) {
                    // Anima a mudança se o número for diferente
                    const currentCount = parseInt(counterElement.textContent);
                    const newCount = parseInt(data.unread);
                    
                    if (currentCount !== newCount) {
                        counterElement.style.transition = 'all 0.3s ease';
                        counterElement.style.transform = 'scale(1.1)';
                        counterElement.textContent = newCount;
                        
                        // Volta ao tamanho normal
                        setTimeout(() => {
                            counterElement.style.transform = 'scale(1)';
                        }, 300);
                        
                        // Notificação visual se houver novas mensagens
                        if (newCount > currentCount) {
                            counterElement.style.color = '#ef4444';
                            setTimeout(() => {
                                counterElement.style.color = '';
                            }, 2000);
                        }
                    }
                }
            }
        })
        .catch(error => {
            console.log('Erro ao atualizar contador de mensagens:', error);
        });
    }

    // Atualiza a cada 30 segundos
    setInterval(updateMessageCounter, 30000);
    
    // Primeira atualização após 5 segundos
    setTimeout(updateMessageCounter, 5000);
});
