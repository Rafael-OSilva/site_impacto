// scripts.js - Scripts comuns para todas as páginas

document.addEventListener('DOMContentLoaded', function () {
    console.log('Sistema Caixa Impacto carregado!');
    
    // Formatar campos monetários
    const moneyInputs = document.querySelectorAll('input[type="number"][step="0.01"]');
    moneyInputs.forEach(input => {
        input.addEventListener('blur', function () {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
    
    // Adicionar eventos de clique aos cards de ação
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach(card => {
        card.addEventListener('click', function () {
            // Redirecionamento já é feito pelo link, esta função é apenas para feedback
            const action = this.querySelector('.action-title').textContent;
            console.log(`Ação selecionada: ${action}`);
        });
    });
});