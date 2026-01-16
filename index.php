<?php
session_start();
// Verificar se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Incluir configurações e funções
require_once 'config/database.php';
require_once 'includes/functions.php';

// Conectar ao banco de dados
$db = new Database();
$connection = $db->getConnection();

// Obter dados para o dashboard
$hoje = date('Y-m-d');
$vendas_hoje = calcularVendasHoje($connection, $hoje);
$saldo_inicial = obterSaldoInicial($connection, $hoje);
$total_caixa = $saldo_inicial + $vendas_hoje['total'];
$numero_vendas = $vendas_hoje['quantidade'];
$ultimas_vendas = obterUltimasVendas($connection, 5);
$status_caixa = verificarStatusCaixa($connection);

// Definir se estamos em um módulo (para links corretos)
$is_module_page = false; // Index.php não está em modules/
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="favicon.ico" type="">
    <title>Caixa Impacto</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../caixa_impacto/css/style.css">
    <style>
        /* Estilos para os botões de ação */
        .btn-action {
            display: inline-block;
            padding: 6px 12px;
            background-color: #007bff;
            color: white;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .btn-action:hover {
            background-color: #0056b3;
            color: white;
            text-decoration: none;
        }
        
        .btn-action i {
            margin-right: 5px;
        }
        
        .btn-danger {
            background-color: #dc3545;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        /* Melhorar a tabela de últimas vendas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        table th,
        table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        table tr:hover {
            background-color: #f5f5f5;
        }
        
        .actions-cell {
            width: 120px;
            text-align: center;
        }
        
        /* Estilos para o dashboard */
        .dashboard-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .action-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            text-decoration: none;
            color: #333;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            text-decoration: none;
            color: #333;
        }
        
        .action-card.primary {
            border-top: 4px solid #007bff;
        }
        
        .action-card.success {
            border-top: 4px solid #28a745;
        }
        
        .action-card.warning {
            border-top: 4px solid #ffc107;
        }
        
        .action-card.danger {
            border-top: 4px solid #dc3545;
        }
        
        .action-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .action-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-desc {
            color: #666;
            font-size: 14px;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            background-color: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .caixa-status {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .status-aberto {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-fechado {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>

<body>
    <div class="container">
        <?php include 'includes/header.php'; ?>
        <?php include 'includes/sidebar.php'; ?>

        <main>
            <h1 class="page-title">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </h1>

            <div class="dashboard-actions">
                <a href="<?= $is_module_page ? 'abrir_caixa.php' : 'modules/abrir_caixa.php' ?>" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="action-title">Abrir Caixa</div>
                    <div class="action-desc">Iniciar o dia de trabalho</div>
                </a>

                <a href="<?= $is_module_page ? 'registrar_venda.php' : 'modules/registrar_venda.php' ?>" class="action-card success">
                    <div class="action-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-title">Registrar Venda</div>
                    <div class="action-desc">Registrar nova venda</div>
                </a>

                <a href="<?= $is_module_page ? 'fechar_caixa.php' : 'modules/fechar_caixa.php' ?>" class="action-card warning">
                    <div class="action-icon">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div class="action-title">Fechar Caixa</div>
                    <div class="action-desc">Encerrar o dia de trabalho</div>
                </a>

                <a href="<?= $is_module_page ? 'relatorios.php' : 'modules/relatorios.php' ?>" class="action-card danger">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-title">Relatórios</div>
                    <div class="action-desc">Visualizar relatórios</div>
                </a>
            </div>

            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-label">Saldo Inicial</div>
                    <div class="stat-value">R$ <?= number_format($saldo_inicial, 2, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Vendas do Dia</div>
                    <div class="stat-value">R$ <?= number_format($vendas_hoje['total'], 2, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total em Caixa</div>
                    <div class="stat-value">R$ <?= number_format($total_caixa, 2, ',', '.') ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Nº de Vendas</div>
                    <div class="stat-value"><?= $numero_vendas ?></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>Últimas Vendas</span>
                    <a href="<?= $is_module_page ? 'registrar_venda.php' : 'modules/registrar_venda.php' ?>" class="btn-action">
                        <i class="fas fa-plus"></i> Nova Venda
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($ultimas_vendas) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Data/Hora</th>
                                    <th>Cliente</th>
                                    <th>Valor</th>
                                    <th>Forma Pagamento</th>
                                    <th class="actions-cell">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas_vendas as $venda): 
                                    // Extrair nome do cliente da descrição
                                    $cliente_nome = 'Consumidor';
                                    if (!empty($venda['descricao'])) {
                                        if (strpos($venda['descricao'], 'Cliente:') !== false) {
                                            $parts = explode('|', $venda['descricao']);
                                            $cliente_nome = trim(str_replace('Cliente:', '', $parts[0]));
                                        } else {
                                            $cliente_nome = $venda['descricao'];
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?></td>
                                        <td><?= htmlspecialchars($cliente_nome) ?></td>
                                        <td>R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($venda['forma_pagamento'] ?? 'Não informada') ?></td>
                                        <td class="actions-cell">
                                            <!-- Corrigido: Botão com link para detalhes da venda específica -->
                                            <a href="<?= $is_module_page ? 'detalhes_vendas.php?id=' . $venda['id'] : 'modules/detalhes_vendas.php?id=' . $venda['id'] ?>" 
                                               class="btn-action">
                                                <i class="fas fa-eye"></i> Detalhes
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: #666;">
                            <i class="fas fa-info-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                            <p>Nenhuma venda registrada hoje</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span>Fechamento de Caixa</span>
                    <span class="caixa-status <?= $status_caixa ? 'status-aberto' : 'status-fechado' ?>">
                        <?= $status_caixa ? 'Caixa Aberto' : 'Caixa Fechado' ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="saldo-inicial">Saldo Inicial</label>
                        <input type="text" id="saldo-inicial" class="form-control" 
                               value="R$ <?= number_format($saldo_inicial, 2, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="valor-vendas">Valor Total de Vendas</label>
                        <input type="text" id="valor-vendas" class="form-control" 
                               value="R$ <?= number_format($vendas_hoje['total'], 2, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="total-caixa">Total em Caixa</label>
                        <input type="text" id="total-caixa" class="form-control" 
                               value="R$ <?= number_format($total_caixa, 2, ',', '.') ?>" readonly>
                    </div>

                    <div class="button-group">
                        <?php if ($status_caixa): ?>
                            <a href="<?= $is_module_page ? 'fechar_caixa.php' : 'modules/fechar_caixa.php' ?>" class="btn-action" style="background-color: #28a745;">
                                <i class="fas fa-check"></i> Fechar Caixa
                            </a>
                        <?php else: ?>
                            <a href="<?= $is_module_page ? 'abrir_caixa.php' : 'modules/abrir_caixa.php' ?>" class="btn-action">
                                <i class="fas fa-door-open"></i> Abrir Caixa
                            </a>
                        <?php endif; ?>

                        <button class="btn-action" onclick="window.print()">
                            <i class="fas fa-print"></i> Imprimir Relatório
                        </button>
                    </div>
                </div>
            </div>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="js/scripts.js"></script>
</body>

</html>