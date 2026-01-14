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

// Processa o formulário de NOVA RETIRADA
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['registrar_retirada'])) {
    if (!$caixa_info) {
        $erro = "Caixa está fechado. Não é possível fazer retiradas.";
    } else {
        $valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
        $motivo = filter_input(INPUT_POST, 'motivo', FILTER_SANITIZE_SPECIAL_CHARS);
        $usuario_id = $_SESSION['usuario_id'];
        $caixa_id = $caixa_info['id'];

        if ($valor <= 0) {
            $erro = "O valor da retirada deve ser maior que zero.";
        } elseif (empty($motivo)) {
            $erro = "O motivo da retirada é obrigatório.";
        } else {
            if (registrarRetirada($connection, $caixa_id, $usuario_id, $valor, $motivo)) {
                $mensagem = "Retirada registrada com sucesso!";
            } else {
                $erro = "Erro ao registrar a retirada.";
            }
        }
    }
}

// Processa a EXCLUSÃO de uma retirada
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['excluir_retirada'])) {
    $retirada_id = filter_input(INPUT_POST, 'retirada_id', FILTER_VALIDATE_INT);
    if (excluirRetirada($connection, $retirada_id, $_SESSION['usuario_id'])) {
        $mensagem = "Retirada excluída com sucesso.";
    } else {
        $erro = "Erro ao excluir a retirada.";
    }
}

// Busca os dados para exibir na página (apenas se o caixa estiver aberto)
$resumo_caixa = null;
$retiradas_hoje = [];
if ($status_caixa && $caixa_info) {
    $resumo_caixa = calcularResumoCaixaAberto($connection, $caixa_info['id']);
    $retiradas_hoje = obterRetiradasDoCaixaAberto($connection, $caixa_info['id']);
}

// Função para determinar a classe do motivo
function getReasonClass($motivo)
{
    $motivo = strtolower($motivo);
    if (strpos($motivo, 'supri') !== false) return 'reason-suprimentos';
    if (strpos($motivo, 'aliment') !== false) return 'reason-alimentacao';
    if (strpos($motivo, 'transp') !== false) return 'reason-transporte';
    if (strpos($motivo, 'urgen') !== false) return 'reason-urgente';
    if (strpos($motivo, 'troco') !== false) return 'reason-transporte';
    if (strpos($motivo, 'compra') !== false) return 'reason-suprimentos';
    return 'reason-outros';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retiradas - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        /* ESTILOS PARA RETIRADAS - DESIGN PROFISSIONAL */
        .financial-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }

        .summary-card {
            background: white;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            border-left: 5px solid #007bff;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--card-color), transparent);
        }

        /* Cores específicas para retiradas */
        .summary-card.saldo-inicial {
            --card-color: #28a745;
            border-left-color: #28a745;
        }

        .summary-card.vendas-dinheiro {
            --card-color: #17a2b8;
            border-left-color: #17a2b8;
        }

        .summary-card.total-retiradas {
            --card-color: #dc3545;
            border-left-color: #dc3545;
        }

        .summary-card.saldo-disponivel {
            --card-color: #20c997;
            border-left-color: #20c997;
        }

        .summary-icon {
            font-size: 2.2rem;
            margin-bottom: 15px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .summary-card.saldo-inicial .summary-icon {
            color: #28a745;
            background: linear-gradient(135deg, #e8f5e8, #d4edda);
            border-radius: 50%;
            width: 70px;
            margin: 0 auto 15px;
        }

        .summary-card.vendas-dinheiro .summary-icon {
            color: #17a2b8;
            background: linear-gradient(135deg, #e3f2fd, #b3e0f2);
            border-radius: 50%;
            width: 70px;
            margin: 0 auto 15px;
        }

        .summary-card.total-retiradas .summary-icon {
            color: #dc3545;
            background: linear-gradient(135deg, #ffe6e6, #ffb3b3);
            border-radius: 50%;
            width: 70px;
            margin: 0 auto 15px;
        }

        .summary-card.saldo-disponivel .summary-icon {
            color: #20c997;
            background: linear-gradient(135deg, #e6fcf5, #b8f2e0);
            border-radius: 50%;
            width: 70px;
            margin: 0 auto 15px;
        }

        .summary-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 10px 0;
            color: var(--card-color);
        }

        .summary-label {
            font-size: 0.95rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 5px;
        }

        /* CARDS ESPECIAIS PARA FORMULÁRIO DE RETIRADA */
        .withdrawal-form-card {
            background: linear-gradient(135deg, #fff, #f8f9fa);
            border: 2px dashed #ffc107;
            border-radius: 12px;
            padding: 25px;
            margin: 20px 0;
        }

        .withdrawal-form-card .card-header {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border-bottom: 2px solid #ffeaa7;
            padding: 15px 20px;
            margin: -25px -25px 20px -25px;
            border-radius: 10px 10px 0 0;
            font-weight: 600;
        }

        .withdrawal-form-card .form-group {
            margin-bottom: 20px;
        }

        .withdrawal-form-card label {
            color: #495057;
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .withdrawal-form-card input,
        .withdrawal-form-card textarea {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 12px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .withdrawal-form-card input:focus,
        .withdrawal-form-card textarea:focus {
            border-color: #ffc107;
            box-shadow: 0 0 0 3px rgba(255, 193, 7, 0.1);
            outline: none;
        }

        /* BOTÃO DE RETIRADA ESPECIAL */
        .btn-withdrawal {
            background: linear-gradient(135deg, #ffc107, #e0a800);
            color: #212529;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            cursor: pointer;
        }

        .btn-withdrawal:hover {
            background: linear-gradient(135deg, #e0a800, #d39e00);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        /* TABELA DE RETIRADAS ESTILIZADA */
        .withdrawal-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .withdrawal-table thead {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .withdrawal-table th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .withdrawal-table tbody tr {
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .withdrawal-table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .withdrawal-table td {
            padding: 15px 12px;
            vertical-align: middle;
        }

        /* BADGES PARA MOTIVOS DE RETIRADA */
        .withdrawal-reason {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .reason-suprimentos {
            background: linear-gradient(135deg, #cce7ff, #b3d9ff);
            color: #004085;
            border: 1px solid #b3d9ff;
        }

        .reason-alimentacao {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .reason-transporte {
            background: linear-gradient(135deg, #e6d9fc, #d6c3f9);
            color: #38235c;
            border: 1px solid #d6c3f9;
        }

        .reason-outros {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .reason-urgente {
            background: linear-gradient(135deg, #ffe6e6, #ffb3b3);
            color: #721c24;
            border: 1px solid #ffb3b3;
        }

        /* BOTÃO DE EXCLUSÃO ESTILIZADO */
        .btn-delete-withdrawal {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .btn-delete-withdrawal:hover {
            background: linear-gradient(135deg, #c82333, #bd2130);
            transform: scale(1.05);
        }

        /* MENSAGEM DE NENHUMA RETIRADA */
        .no-withdrawals {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .no-withdrawals i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #dee2e6;
        }

        /* ALERTAS ESPECÍFICOS PARA RETIRADAS */
        .alert-withdrawal {
            border-left: 4px solid #ffc107;
            background: linear-gradient(135deg, #fff9e6, #ffeaa7);
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 20px 0;
        }

        /* ANIMAÇÕES */
        @keyframes slideInUp {
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
            animation: slideInUp 0.6s ease-out;
        }

        .summary-card:nth-child(1) {
            animation-delay: 0.1s;
        }

        .summary-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .summary-card:nth-child(3) {
            animation-delay: 0.3s;
        }

        .summary-card:nth-child(4) {
            animation-delay: 0.4s;
        }

        /* RESPONSIVIDADE */
        @media (max-width: 1200px) {
            .financial-summary {
                grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .financial-summary {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .summary-card {
                padding: 20px 15px;
            }

            .summary-value {
                font-size: 1.6rem;
            }

            .summary-icon {
                font-size: 1.8rem;
                height: 60px;
            }

            .summary-card.saldo-inicial .summary-icon,
            .summary-card.vendas-dinheiro .summary-icon,
            .summary-card.total-retiradas .summary-icon,
            .summary-card.saldo-disponivel .summary-icon {
                width: 60px;
            }

            .withdrawal-table {
                font-size: 0.8rem;
            }

            .withdrawal-table th,
            .withdrawal-table td {
                padding: 10px 8px;
            }
        }

        /* EFEITO DE BRILHO NO HOVER */
        .summary-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transform: rotate(45deg);
            transition: all 0.6s ease;
            opacity: 0;
        }

        .summary-card:hover::after {
            opacity: 1;
            transform: rotate(45deg) translate(50%, 50%);
        }

        /* ESTILOS PARA ALERTAS */
        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .alert-warning {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Retiradas em Dinheiro</h1>

            <?php if ($mensagem): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <?php if (!$status_caixa): ?>
                <div class="alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    O caixa está fechado. Você precisa <a href="abrir_caixa.php">abrir o caixa</a> para gerenciar retiradas.
                </div>
            <?php else: ?>
                <div class="financial-summary">
                    <div class="summary-card saldo-inicial">
                        <div class="summary-icon">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="summary-value"><?= formatarMoeda($resumo_caixa['valor_inicial']) ?></div>
                        <div class="summary-label">Saldo Inicial</div>
                    </div>

                    <div class="summary-card vendas-dinheiro">
                        <div class="summary-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="summary-value"><?= formatarMoeda($resumo_caixa['vendas_dinheiro']) ?></div>
                        <div class="summary-label">Vendas em Dinheiro</div>
                    </div>

                    <div class="summary-card total-retiradas">
                        <div class="summary-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="summary-value"><?= formatarMoeda($resumo_caixa['total_retiradas']) ?></div>
                        <div class="summary-label">Total Retiradas</div>
                    </div>

                    <div class="summary-card saldo-disponivel">
                        <div class="summary-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="summary-value"><?= formatarMoeda($resumo_caixa['saldo_disponivel']) ?></div>
                        <div class="summary-label">Saldo Disponível</div>
                    </div>
                </div>

                <div class="withdrawal-form-card">
                    <div class="card-header">
                        <span><i class="fas fa-plus-circle"></i> Nova Retirada</span>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="registrar_retirada" value="1">

                            <div class="form-group">
                                <label for="valor">
                                    <i class="fas fa-money-bill"></i> Valor da Retirada (R$)
                                </label>
                                <input type="number" id="valor" name="valor" step="0.01" min="0.01"
                                    placeholder="0,00" required>
                            </div>

                            <div class="form-group">
                                <label for="motivo">
                                    <i class="fas fa-clipboard-list"></i> Motivo da Retirada
                                </label>
                                <input type="text" id="motivo" name="motivo"
                                    placeholder="Ex: Compra de suprimentos, Alimentação, Transporte..." required>
                            </div>

                            <div class="form-group">
                                <label>
                                    <i class="fas fa-user"></i> Responsável
                                </label>
                                <input type="text" value="<?= htmlspecialchars($_SESSION['usuario_nome']) ?>" readonly
                                    style="background-color: #f8f9fa;">
                            </div>

                            <button type="submit" class="btn-withdrawal">
                                <i class="fas fa-hand-holding-usd"></i> Registrar Retirada
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <span><i class="fas fa-history"></i> Histórico de Retiradas</span>
                        <span class="total-amount" style="color: #dc3545; font-weight: bold;">
                            Total: <?= formatarMoeda($resumo_caixa['total_retiradas']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($retiradas_hoje)): ?>
                            <div class="no-withdrawals">
                                <i class="fas fa-money-bill-wave-slash"></i>
                                <h3>Nenhuma retirada registrada</h3>
                                <p>Não há retiradas registradas neste caixa.</p>
                            </div>
                        <?php else: ?>
                            <table class="withdrawal-table">
                                <thead>
                                    <tr>
                                        <th><i class="fas fa-clock"></i> Hora</th>
                                        <th><i class="fas fa-dollar-sign"></i> Valor</th>
                                        <th><i class="fas fa-tag"></i> Motivo</th>
                                        <th><i class="fas fa-user"></i> Responsável</th>
                                        <th><i class="fas fa-cog"></i> Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($retiradas_hoje as $retirada):
                                        $reasonClass = getReasonClass($retirada['motivo']);
                                    ?>
                                        <tr>
                                            <td>
                                                <i class="fas fa-clock text-muted"></i>
                                                <?= date('H:i', strtotime($retirada['data_retirada'])) ?>
                                            </td>
                                            <td>
                                                <strong style="color: #dc3545;">
                                                    <?= formatarMoeda($retirada['valor']) ?>
                                                </strong>
                                            </td>
                                            <td>
                                                <span class="withdrawal-reason <?= $reasonClass ?>">
                                                    <?= htmlspecialchars($retirada['motivo']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <i class="fas fa-user-circle text-muted"></i>
                                                <?= htmlspecialchars($retirada['nome_usuario']) ?>
                                            </td>
                                            <td>
                                                <form method="POST" onsubmit="return confirm('Tem certeza que deseja excluir esta retirada?');" style="display: inline;">
                                                    <input type="hidden" name="excluir_retirada" value="1">
                                                    <input type="hidden" name="retirada_id" value="<?= $retirada['id'] ?>">
                                                    <button type="submit" class="btn-delete-withdrawal" title="Excluir retirada">
                                                        <i class="fas fa-trash"></i> Excluir
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
        <?php include '../includes/footer.php'; ?>
    </div>
    <script src="../js/scripts.js"></script>
</body>

</html>