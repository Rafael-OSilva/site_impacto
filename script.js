// Dados simulados - na implementação real, isso virá de um arquivo JSON
const seriesPorNivel = {
    maternal: ["Berçário", "Maternal I", "Maternal II"],
    infantil: ["Infantil I", "Infantil II", "Infantil III"],
    fundamental: ["1º Ano", "2º Ano", "3º Ano", "4º Ano", "5º Ano", "6º Ano", "7º Ano", "8º Ano", "9º Ano"],
    medio: ["1ª Série", "2ª Série", "3ª Série"]
};

// Materiais por série (exemplo simplificado)
const materiaisPorSerie = {
    "1º Ano": [
        { nome: "Caderno brochurão 96 fl.", quantidade: 4, preco: 12.90 },
        { nome: "Lápis preto HB", quantidade: 12, preco: 1.20 },
        { nome: "Borracha branca", quantidade: 2, preco: 1.50 },
        { nome: "Apontador com depósito", quantidade: 1, preco: 3.50 },
        { nome: "Caixa de lápis de cor (12 cores)", quantidade: 1, preco: 15.90 }
    ],
    "2º Ano": [
        { nome: "Caderno brochurão 96 fl.", quantidade: 4, preco: 12.90 },
        { nome: "Lápis preto HB", quantidade: 10, preco: 1.20 },
        { nome: "Caneta esferográfica azul", quantidade: 2, preco: 2.50 },
        { nome: "Borracha branca", quantidade: 2, preco: 1.50 },
        { nome: "Régua 30 cm", quantidade: 1, preco: 3.00 }
    ]
    // Outras séries seriam adicionadas aqui
};

// Elementos do DOM
const modal = document.getElementById('modal-series');
const closeModalBtn = document.querySelector('.close-modal');
const seriesList = document.getElementById('series-list');
const nivelCards = document.querySelectorAll('.nivel-card');
let nivelSelecionado = '';

// Abrir modal ao clicar em um card de nível
nivelCards.forEach(card => {
    card.addEventListener('click', () => {
        nivelSelecionado = card.getAttribute('data-nivel');
        abrirModalSeries(nivelSelecionado);
    });
});

// Fechar modal
closeModalBtn.addEventListener('click', fecharModal);
window.addEventListener('click', (e) => {
    if (e.target === modal) {
        fecharModal();
    }
});

// Função para abrir o modal com as séries
function abrirModalSeries(nivel) {
    // Limpar lista anterior
    seriesList.innerHTML = '';

    // Adicionar séries correspondentes ao nível
    seriesPorNivel[nivel].forEach(serie => {
        const serieItem = document.createElement('div');
        serieItem.className = 'serie-item';
        serieItem.textContent = serie;
        serieItem.addEventListener('click', () => {
            redirecionarParaOrcamento(serie);
        });
        seriesList.appendChild(serieItem);
    });

    // Mostrar modal
    modal.style.display = 'flex';
}

// Função para fechar o modal
function fecharModal() {
    modal.style.display = 'none';
}

// Função para redirecionar para a página de orçamento
function redirecionarParaOrcamento(serie) {
    // Em uma implementação real, isso redirecionaria para uma página específica
    // Aqui vamos simular a exibição de um orçamento
    const materiais = materiaisPorSerie[serie] || [];
    let total = 0;

    let mensagem = `Orçamento para ${serie} do Ensino ${nivelSelecionado.charAt(0).toUpperCase() + nivelSelecionado.slice(1)}:\n\n`;

    materiais.forEach(item => {
        const subtotal = item.quantidade * item.preco;
        total += subtotal;
        mensagem += `${item.quantidade}x ${item.nome} - R$ ${subtotal.toFixed(2)}\n`;
    });

    mensagem += `\nTotal: R$ ${total.toFixed(2)}`;
    mensagem += `\n\nGostaria de encomendar estes materiais?`;

    // Fechar modal
    fecharModal();

    // Mostrar orçamento e opção para WhatsApp
    if (confirm(mensagem + "\n\nClique em OK para ser redirecionado ao WhatsApp.")) {
        enviarWhatsApp(serie, materiais, total);
    }
}

// Função para enviar mensagem via WhatsApp
function enviarWhatsApp(serie, materiais, total) {
    const phone = "5511999999999"; // Substitua pelo número real

    let message = `Olá! Gostaria de encomendar os materiais da ${serie}.\n\n`;
    message += `Itens:\n`;

    materiais.forEach(item => {
        const subtotal = item.quantidade * item.preco;
        message += `${item.quantidade}x ${item.nome} - R$ ${subtotal.toFixed(2)}\n`;
    });

    message += `\nTotal: R$ ${total.toFixed(2)}`;

    const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    window.open(url, '_blank');
}
