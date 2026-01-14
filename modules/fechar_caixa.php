<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Protege a página e conecta ao banco
verificarLogin();
$db = new Database();
$connection = $db->getConnection();

// Verifica o status do caixa e pega as informações dele
$status_caixa = verificarStatusCaixa($connection);
$caixa_info = null;
if ($status_caixa) {
    $caixa_info = obterCaixaAberto($connection);
}

$mensagem = '';
$erro = '';

// Processa o formulário de FECHAMENTO DO CAIXA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!$caixa_info) {
        $erro = "Não há caixa aberto para fechar.";
    } else {
        $valor_final_informado = filter_input(INPUT_POST, 'valor_final', FILTER_VALIDATE_FLOAT);
        $observacoes = filter_input(INPUT_POST, 'observacoes', FILTER_SANITIZE_SPECIAL_CHARS);
        $usuario_fechamento_id = $_SESSION['usuario_id'];
        $caixa_id = $caixa_info['id'];

        if ($valor_final_informado === false || $valor_final_informado < 0) {
            $erro = "Por favor, insira um valor final válido.";
        } else {
            if (finalizarFechamentoCaixa($connection, $caixa_id, $usuario_fechamento_id, $valor_final_informado, $observacoes)) {
                $mensagem = "Caixa fechado com sucesso! Redirecionando para o dashboard...";
                $status_caixa = false; 
                header("refresh:3;url=../index.php");
            } else {
                $erro = "Ocorreu um erro ao fechar o caixa.";
            }
        }
    }
}

// Busca todos os dados e resumos para exibir na página
$resumo_vendas = [];
$vendas_detalhadas = [];
$total_vendas = 0;
$saldo_esperado_dinheiro = 0;

if ($status_caixa) {
    $relatorio_caixa_atual = obterRelatorioVendasPorCaixa($connection, $caixa_info['id']);
    $resumo_vendas = $relatorio_caixa_atual['resumo_pagamentos'];
    $vendas_detalhadas = $relatorio_caixa_atual['vendas'];
    $total_vendas = $relatorio_caixa_atual['total_geral'];

    $resumo_caixa_dinheiro = calcularResumoCaixaAberto($connection, $caixa_info['id']);
    $saldo_esperado_dinheiro = $resumo_caixa_dinheiro['saldo_disponivel'];
}

// Mapeamento de classes CSS para formas de pagamento
$payment_classes = [
    'Dinheiro' => 'dinheiro',
    'Cartão Débito' => 'debito',
    'Cartão Crédito' => 'credito',
    'PIX' => 'pix',
    'A Receber' => 'a-receber'
];

$payment_icons = [
    'Dinheiro' => 'fa-money-bill-wave',
    'Cartão Débito' => 'fa-credit-card',
    'Cartão Crédito' => 'fa-credit-card',
    'PIX' => 'fa-qrcode',
    'A Receber' => 'fa-clock'
];

$badge_classes = [
    'Dinheiro' => 'badge-dinheiro',
    'Cartão Débito' => 'badge-debito',
    'Cartão Crédito' => 'badge-credito',
    'PIX' => 'badge-pix',
    'A Receber' => 'badge-a-receber'
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fechar Caixa - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ESTILOS PROFISSIONAIS PARA FECHAMENTO DE CAIXA */
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .summary-card {
            background: white;
            padding: 30px 25px;
            border-radius: 15px;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 6px solid #007bff;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.15);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--card-color), transparent);
        }

        /* Cores específicas para cada forma de pagamento */
        .summary-card.dinheiro {
            --card-color: #28a745;
            border-left-color: #28a745;
        }

        .summary-card.debito {
            --card-color: #17a2b8;
            border-left-color: #17a2b8;
        }

        .summary-card.credito {
            --card-color: #6f42c1;
            border-left-color: #6f42c1;
        }

        .summary-card.pix {
            --card-color: #20c997;
            border-left-color: #20c997;
        }

        .summary-card.a-receber {
            --card-color: #ffc107;
            border-left-color: #ffc107;
        }

        .summary-card.total {
            --card-color: #dc3545;
            border-left-color: #dc3545;
            background: linear-gradient(135deg, #fff, #f8f9fa);
        }

        .summary-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .summary-card.dinheiro .summary-icon {
            color: #28a745;
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-card.debito .summary-icon {
            color: #17a2b8;
            background: linear-gradient(135deg, #e3f2fd, #b3e0f2);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-card.credito .summary-icon {
            color: #6f42c1;
            background: linear-gradient(135deg, #f3e8ff, #d8c3ff);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-card.pix .summary-icon {
            color: #20c997;
            background: linear-gradient(135deg, #e6fcf5, #b8f2e0);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-card.a-receber .summary-icon {
            color: #ffc107;
            background: linear-gradient(135deg, #fff9e6, #ffeaa7);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-card.total .summary-icon {
            color: #dc3545;
            background: linear-gradient(135deg, #ffe6e6, #ffb3b3);
            border-radius: 50%;
            width: 90px;
            margin: 0 auto 20px;
        }

        .summary-value {
            font-size: 2.2rem;
            font-weight: bold;
            margin: 15px 0;
            color: var(--card-color);
        }

        .summary-label {
            font-size: 1.1rem;
            color: #495057;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .summary-count {
            font-size: 0.9rem;
            color: #6c757d;
            background: rgba(0, 0, 0, 0.05);
            padding: 6px 15px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 10px;
            font-weight: 500;
        }

        /* Badges para as formas de pagamento na tabela */
        .payment-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-dinheiro {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 2px solid #c3e6cb;
        }

        .badge-debito {
            background: linear-gradient(135deg, #cce7ff, #b3d9ff);
            color: #004085;
            border: 2px solid #b3d9ff;
        }

        .badge-credito {
            background: linear-gradient(135deg, #e6d9fc, #d6c3f9);
            color: #38235c;
            border: 2px solid #d6c3f9;
        }

        .badge-pix {
            background: linear-gradient(135deg, #c8f7e5, #a3f0d1);
            color: #0f5132;
            border: 2px solid #a3f0d1;
        }

        .badge-a-receber {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 2px solid #ffeaa7;
        }

        /* Estilos para o formulário de fechamento */
        .closure-form {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border: 3px solid #ffc107;
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 8px 30px rgba(255, 193, 7, 0.15);
        }

        .closure-title {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            padding: 20px 25px;
            margin: -30px -30px 25px -30px;
            border-radius: 17px 17px 0 0;
            font-weight: 700;
            font-size: 1.3rem;
            text-align: center;
        }

        .closure-form .form-group {
            margin-bottom: 25px;
        }

        .closure-form label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 10px;
            display: block;
            font-size: 1rem;
        }

        .closure-form input,
        .closure-form textarea {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .closure-form input:focus,
        .closure-form textarea:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.2);
            outline: none;
        }

        .closure-form input[readonly] {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            color: #6c757d;
            font-weight: 600;
        }

        /* Botão de fechamento */
        .btn-close-register {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 20px 40px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.3rem;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
            margin-top: 20px;
        }

        .btn-close-register:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        /* Tabela de vendas estilizada */
        .sales-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .sales-table thead {
            background: linear-gradient(135deg, #28a745, #20a03a);
            color: white;
        }

        .sales-table th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
        }

        .sales-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .sales-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .sales-table td {
            padding: 16px 15px;
            vertical-align: middle;
        }

        /* Alertas estilizados */
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 2px solid #c3e6cb;
            color: #155724;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 6px solid #28a745;
            font-size: 1.1rem;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border: 2px solid #f5c6cb;
            color: #721c24;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 6px solid #dc3545;
            font-size: 1.1rem;
        }

        .alert-info {
            background: linear-gradient(135deg, #cce7ff, #b3d9ff);
            border: 2px solid #b3d9ff;
            color: #004085;
            padding: 25px;
            border-radius: 12px;
            margin: 25px 0;
            border-left: 6px solid #17a2b8;
            font-size: 1.1rem;
        }

        /* Info box */
        .info-box {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border: 2px solid #90caf9;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }

        .info-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1565c0;
            margin-bottom: 10px;
        }

        .info-text {
            color: #1976d2;
            font-size: 1rem;
            line-height: 1.6;
        }

        /* Animações */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .summary-card {
            animation: fadeInUp 0.6s ease-out;
        }

        .summary-card:nth-child(1) { animation-delay: 0.1s; }
        .summary-card:nth-child(2) { animation-delay: 0.2s; }
        .summary-card:nth-child(3) { animation-delay: 0.3s; }
        .summary-card:nth-child(4) { animation-delay: 0.4s; }
        .summary-card:nth-child(5) { animation-delay: 0.5s; }
        .summary-card:nth-child(6) { animation-delay: 0.6s; }

        /* Efeito de brilho no hover */
        .summary-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.15), transparent);
            transform: rotate(45deg);
            transition: all 0.8s ease;
            opacity: 0;
        }

        .summary-card:hover::after {
            opacity: 1;
            transform: rotate(45deg) translate(50%, 50%);
        }

        /* Responsividade */
        @media (max-width: 1200px) {
            .financial-summary {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .financial-summary {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .summary-card {
                padding: 25px 20px;
            }
            
            .summary-value {
                font-size: 1.8rem;
            }
            
            .summary-icon {
                font-size: 2rem;
                height: 70px;
            }
            
            .summary-card.dinheiro .summary-icon,
            .summary-card.debito .summary-icon,
            .summary-card.credito .summary-icon,
            .summary-card.pix .summary-icon,
            .summary-card.a-receber .summary-icon,
            .summary-card.total .summary-icon {
                width: 70px;
            }
            
            .closure-form {
                padding: 20px;
            }
            
            .closure-title {
                padding: 15px 20px;
                margin: -20px -20px 20px -20px;
            }
            
            .sales-table {
                font-size: 0.8rem;
            }
        }

        /* Destaque para diferença */
        .difference-highlight {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            font-weight: 600;
            color: #856404;
        }
    </style>
</head>
<body>
<div class="container">
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/sidebar.php'; ?>

    <main>
        <h1 class="page-title"><i class="fas fa-door-closed"></i> Fechar Caixa</h1>

        <?php if ($mensagem): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?= $mensagem ?>
            </div>
        <?php endif; ?>

        <?php if ($erro): ?>
            <div class="alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
            </div>
        <?php endif; ?>

        <?php if (!$status_caixa && empty($mensagem)): ?>
            <div class="alert-info">
                <i class="fas fa-info-circle"></i> Não há um caixa aberto no momento. <a href="abrir_caixa.php" style="color: #004085; font-weight: 600;">Abrir um novo caixa?</a>
            </div>
        <?php elseif ($status_caixa): ?>
            <div class="info-box">
                <div class="info-title">Resumo do Dia</div>
                <div class="info-text">Confira o resumo financeiro. Conte o dinheiro em caixa, informe o valor no campo "Saldo Real" e confirme para encerrar o expediente.</div>
            </div>

            <div class="financial-summary">
                <?php foreach ($resumo_vendas as $resumo): 
                    $nome = $resumo['nome'] ?? 'N/A';
                    $classe = $payment_classes[$nome] ?? 'default';
                    $icone = $payment_icons[$nome] ?? 'fa-money-bill-wave';
                ?>
                <div class="summary-card <?= $classe ?>">
                    <div class="summary-icon">
                        <i class="fas <?= $icone ?>"></i>
                    </div>
                    <div class="summary-value"><?= formatarMoeda($resumo['total']) ?></div>
                    <div class="summary-label"><?= htmlspecialchars($nome) ?></div>
                    <div class="summary-count"><?= ($resumo['quantidade'] ?? 0) ?> vendas</div>
                </div>
                <?php endforeach; ?>
                
                <div class="summary-card total">
                    <div class="summary-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="summary-value"><?= formatarMoeda($total_vendas) ?></div>
                    <div class="summary-label">Total de Vendas</div>
                    <div class="summary-count"><?= count($vendas_detalhadas) ?> vendas</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>Detalhamento das Vendas</span>
                    <span>Data: <?= date('d/m/Y', strtotime($caixa_info['data_abertura'])) ?></span>
                </div>
                <div class="card-body">
                    <table class="sales-table">
                        <thead>
                            <tr>
                                <th><i class="fas fa-clock"></i> Hora</th>
                                <th><i class="fas fa-dollar-sign"></i> Valor</th>
                                <th><i class="fas fa-credit-card"></i> Método</th>
                                <th><i class="fas fa-sticky-note"></i> Descrição</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($vendas_detalhadas as $venda): 
                            $forma_pagamento = $venda['forma_pagamento'] ?? 'N/A';
                            $badge_class = $badge_classes[$forma_pagamento] ?? 'badge-dinheiro';
                        ?>
                            <tr>
                                <td><?= date('H:i', strtotime($venda['data_venda'])) ?></td>
                                <td><strong><?= formatarMoeda($venda['valor_total']) ?></strong></td>
                                <td>
                                    <span class="payment-badge <?= $badge_class ?>">
                                        <?= htmlspecialchars($forma_pagamento) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($venda['descricao']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="closure-form">
                <div class="closure-title"><i class="fas fa-calculator"></i> Conferência de Fechamento</div>
                <form id="close-register-form" method="POST">
                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Saldo Inicial (Dinheiro)</label>
                        <input type="text" value="<?= formatarMoeda($resumo_caixa_dinheiro['valor_inicial']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-plus-circle"></i> Vendas (Dinheiro)</label>
                        <input type="text" value="<?= formatarMoeda($resumo_caixa_dinheiro['vendas_dinheiro']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-minus-circle"></i> Retiradas (Dinheiro)</label>
                        <input type="text" value="<?= formatarMoeda($resumo_caixa_dinheiro['total_retiradas']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-equals"></i> Saldo Esperado (Dinheiro em Caixa)</label>
                        <input type="text" id="expected-balance" value="<?= formatarMoeda($saldo_esperado_dinheiro) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="valor_final"><i class="fas fa-money-check"></i> Saldo Real (Valor contado em caixa)</label>
                        <input type="number" id="valor_final" name="valor_final" step="0.01" min="0" placeholder="0,00" required>
                    </div>
                    <div class="form-group">
                        <label for="observacoes"><i class="fas fa-sticky-note"></i> Observações</label>
                        <textarea id="observacoes" name="observacoes" rows="3" placeholder="Ex: Diferença por troco errado, notas contadas..."></textarea>
                    </div>
                    <button type="submit" class="btn-close-register">
                        <i class="fas fa-lock"></i> Confirmar e Fechar Caixa
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </main>

    <footer>Caixa Impacto - Sistema de Gestão &copy; 2025</footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('close-register-form');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const actualBalanceInput = document.getElementById('valor_final');
            const actualBalance = parseFloat(actualBalanceInput.value);
            
            const expectedBalance = <?= $saldo_esperado_dinheiro ?? 0 ?>;
            const difference = actualBalance - expectedBalance;

            let message = `🎯 CONFIRMAR FECHAMENTO DO CAIXA?\n\n`;
            message += `💰 Saldo Esperado em Dinheiro: R$ ${expectedBalance.toFixed(2)}\n`;
            message += `💵 Saldo Real Informado: R$ ${actualBalance.toFixed(2)}\n`;
            message += `📊 Diferença: R$ ${difference.toFixed(2)}\n\n`;
            message += `Esta ação não pode ser desfeita.`;

            if (confirm(message)) {
                form.submit();
            }
        });
    }
});
</script>
</body>
</html>