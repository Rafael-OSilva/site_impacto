<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

verificarLogin();
$db = new Database();
$connection = $db->getConnection();

// Verificar se há caixa aberto
$caixa_aberto = verificarStatusCaixa($connection);
$caixa_info = $caixa_aberto ? obterCaixaAberto($connection) : null;

$mensagem = '';
$erro = '';

// Processar recebimento de conta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['receber_conta'])) {
    $venda_id = intval($_POST['venda_id']);
    $forma_pagamento_id = intval($_POST['forma_pagamento_id']);
    
    if (!$caixa_aberto) {
        $erro = "Não é possível receber pagamento. O caixa está fechado.";
    } elseif (!$forma_pagamento_id) {
        $erro = "Por favor, selecione a forma de pagamento recebida.";
    } else {
        // Buscar informações da venda
        $venda = buscarVendaPorId($connection, $venda_id);
        
        if (!$venda) {
            $erro = "Venda não encontrada.";
        } elseif ($venda['status'] == 'concluida') {
            $erro = "Esta conta já foi recebida anteriormente.";
        } else {
            // Registrar o recebimento
            if (receberPagamentoContaComFormaPagamento($connection, $venda_id, $_SESSION['usuario_id'], $forma_pagamento_id)) {
                $mensagem = "Pagamento recebido com sucesso! Valor: " . formatarMoeda($venda['valor_total']);
            } else {
                $erro = "Erro ao registrar o recebimento. Tente novamente.";
            }
        }
    }
}

// Buscar contas a receber
$filtros = [
    'cliente' => $_GET['cliente'] ?? '',
    'data' => $_GET['data'] ?? '',
    'valor' => $_GET['valor'] ?? ''
];

$contas_receber = obterContasAReceber($connection, $filtros);
$formas_pagamento = obterFormasPagamento($connection);

// Obter estatísticas
$estatisticas = obterEstatisticasContasAReceber($connection);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contas a Receber - Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .payment-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .payment-modal.active {
            display: flex;
        }

        .payment-modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 500px;
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .payment-methods-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .payment-method-option {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-method-option:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }

        .payment-method-option.selected {
            border-color: #28a745;
            background-color: rgba(40, 167, 69, 0.1);
            transform: translateY(-2px);
        }

        .payment-method-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: #6c757d;
        }

        .payment-method-option.selected .payment-method-icon {
            color: #28a745;
        }

        .payment-method-name {
            font-size: 0.85rem;
            font-weight: 600;
            color: #495057;
        }

        .venda-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            border-left: 4px solid #007bff;
        }

        .venda-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .venda-info-label {
            font-weight: 600;
            color: #495057;
        }

        .venda-info-value {
            color: #28a745;
            font-weight: 600;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #28a745, #20a03a);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
        }

        .btn-cancel {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            flex: 1;
        }

        .btn-receber {
            background: linear-gradient(135deg, #28a745, #20a03a);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-receber:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }

        .caixa-status-info {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 10px 0;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php include '../includes/header.php'; ?>
        <?php include '../includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-money-bill-wave"></i> Contas a Receber
            </h1>

            <?php if ($mensagem): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?= $erro ?>
                </div>
            <?php endif; ?>

            <!-- Status do Caixa -->
            <?php if (!$caixa_aberto): ?>
                <div class="caixa-status-info">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Caixa Fechado:</strong> Para receber pagamentos, é necessário ter um caixa aberto. 
                    <a href="abrir_caixa.php">Abrir caixa agora</a>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724;">
                    <i class="fas fa-cash-register"></i>
                    <strong>Caixa Aberto:</strong> Os valores recebidos serão registrados no caixa atual.
                </div>
            <?php endif; ?>

            <!-- Filtros e Estatísticas -->
            <div class="card">
                <div class="card-header">
                    <span>Filtros e Estatísticas</span>
                </div>
                <div class="card-body">
                    <form method="GET" class="filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="cliente">Cliente</label>
                                <input type="text" id="cliente" name="cliente" value="<?= htmlspecialchars($filtros['cliente']) ?>" placeholder="Nome do cliente...">
                            </div>
                            <div class="form-group">
                                <label for="data">Data</label>
                                <input type="date" id="data" name="data" value="<?= htmlspecialchars($filtros['data']) ?>">
                            </div>
                            <div class="form-group">
                                <label for="valor">Valor</label>
                                <select id="valor" name="valor">
                                    <option value="">Todos</option>
                                    <option value="menor100" <?= $filtros['valor'] == 'menor100' ? 'selected' : '' ?>>Menor que R$ 100</option>
                                    <option value="100a500" <?= $filtros['valor'] == '100a500' ? 'selected' : '' ?>>R$ 100 a R$ 500</option>
                                    <option value="maior500" <?= $filtros['valor'] == 'maior500' ? 'selected' : '' ?>>Maior que R$ 500</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <button type="submit" class="btn-primary">Filtrar</button>
                                <a href="contas_receber.php" class="btn-secondary">Limpar</a>
                            </div>
                        </div>
                    </form>

                    <?php if ($estatisticas && $estatisticas['total_contas'] > 0): ?>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-value"><?= $estatisticas['total_contas'] ?></div>
                            <div class="stat-label">Total de Contas</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= formatarMoeda($estatisticas['valor_total']) ?></div>
                            <div class="stat-label">Valor Total</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-value"><?= formatarMoeda($estatisticas['media_valor']) ?></div>
                            <div class="stat-label">Valor Médio</div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lista de Contas a Receber -->
            <div class="card">
                <div class="card-header">
                    <span>Contas Pendentes</span>
                    <span>Total: <?= count($contas_receber) ?> conta(s)</span>
                </div>
                <div class="card-body">
                    <?php if (empty($contas_receber)): ?>
                        <div class="no-results">
                            <i class="fas fa-check-circle"></i>
                            <h3>Nenhuma conta pendente</h3>
                            <p>Todas as contas foram recebidas ou não há contas a receber no momento.</p>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data Venda</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Operador</th>
                                    <th>Dias</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($contas_receber as $conta): 
                                    $dias_atraso = calcularDiasAtraso($conta['data_venda']);
                                    $cliente_nome = 'Cliente não informado';
                                    $descricao = $conta['descricao'] ?? '';
                                    
                                    if (strpos($descricao, 'Cliente:') !== false) {
                                        $parts = explode('|', $descricao);
                                        $cliente_nome = trim(str_replace('Cliente:', '', $parts[0]));
                                    } else {
                                        $cliente_nome = $descricao;
                                    }
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($conta['data_venda'])) ?></td>
                                    <td><?= htmlspecialchars($cliente_nome) ?></td>
                                    <td><strong><?= formatarMoeda($conta['valor_total']) ?></strong></td>
                                    <td><?= htmlspecialchars($conta['operador']) ?></td>
                                    <td>
                                        <span class="badge <?= $dias_atraso > 30 ? 'badge-danger' : 'badge-warning' ?>">
                                            <?= $dias_atraso ?> dia(s)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($caixa_aberto): ?>
                                            <button class="btn-receber" 
                                                    onclick="openPaymentModal(<?= $conta['id'] ?>, '<?= htmlspecialchars($cliente_nome) ?>', <?= $conta['valor_total'] ?>, '<?= date('d/m/Y', strtotime($conta['data_venda'])) ?>')">
                                                <i class="fas fa-hand-holding-usd"></i> Receber
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-secondary" disabled title="Caixa fechado">
                                                <i class="fas fa-lock"></i> Receber
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <?php include '../includes/footer.php'; ?>
    </div>

    <!-- Modal de Recebimento -->
    <div class="payment-modal" id="paymentModal">
        <div class="payment-modal-content">
            <h3><i class="fas fa-hand-holding-usd"></i> Receber Pagamento</h3>
            
            <div class="venda-info" id="vendaInfo">
                <!-- As informações da venda serão preenchidas via JavaScript -->
            </div>

            <form method="POST" id="paymentForm">
                <input type="hidden" name="receber_conta" value="1">
                <input type="hidden" name="venda_id" id="modalVendaId">
                
                <div class="form-group">
                    <label>Forma de Pagamento Recebida *</label>
                    <div class="payment-methods-grid" id="paymentMethods">
                        <?php foreach ($formas_pagamento as $forma): 
                            if ($forma['nome'] == 'A Receber') continue; // Não mostrar "A Receber" como opção de recebimento
                            
                            $icones = [
                                'Dinheiro' => 'fa-money-bill-wave',
                                'Cartão Débito' => 'fa-credit-card',
                                'Cartão Crédito' => 'fa-credit-card',
                                'PIX' => 'fa-qrcode'
                            ];
                            $icone = $icones[$forma['nome']] ?? 'fa-money-bill-wave';
                        ?>
                        <div class="payment-method-option" data-method="<?= $forma['id'] ?>">
                            <div class="payment-method-icon">
                                <i class="fas <?= $icone ?>"></i>
                            </div>
                            <div class="payment-method-name"><?= htmlspecialchars($forma['nome']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="forma_pagamento_id" id="selectedPaymentMethod" required>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closePaymentModal()">Cancelar</button>
                    <button type="submit" class="btn-confirm" id="confirmButton">
                        <i class="fas fa-check"></i> Confirmar Recebimento
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let selectedPaymentMethod = null;

        function openPaymentModal(vendaId, clienteNome, valor, dataVenda) {
            // Preencher informações da venda
            document.getElementById('vendaInfo').innerHTML = `
                <div class="venda-info-item">
                    <span class="venda-info-label">Cliente:</span>
                    <span class="venda-info-value">${clienteNome}</span>
                </div>
                <div class="venda-info-item">
                    <span class="venda-info-label">Valor:</span>
                    <span class="venda-info-value">R$ ${valor.toFixed(2)}</span>
                </div>
                <div class="venda-info-item">
                    <span class="venda-info-label">Data da Venda:</span>
                    <span>${dataVenda}</span>
                </div>
            `;

            // Configurar o formulário
            document.getElementById('modalVendaId').value = vendaId;
            
            // Resetar seleção
            selectedPaymentMethod = null;
            document.getElementById('selectedPaymentMethod').value = '';
            document.querySelectorAll('.payment-method-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Abrir modal
            document.getElementById('paymentModal').classList.add('active');
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').classList.remove('active');
        }

        // Selecionar método de pagamento
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.payment-method-option').forEach(option => {
                option.addEventListener('click', function() {
                    // Remover seleção anterior
                    document.querySelectorAll('.payment-method-option').forEach(opt => {
                        opt.classList.remove('selected');
                    });
                    
                    // Selecionar novo
                    this.classList.add('selected');
                    selectedPaymentMethod = this.dataset.method;
                    document.getElementById('selectedPaymentMethod').value = selectedPaymentMethod;
                });
            });

            // Fechar modal ao clicar fora
            document.getElementById('paymentModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closePaymentModal();
                }
            });
        });
    </script>
</body>
</html>