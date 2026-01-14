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
                <a href="modules/abrir_caixa.php" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-door-open"></i>
                    </div>
                    <div class="action-title">Abrir Caixa</div>
                    <div class="action-desc">Iniciar o dia de trabalho</div>
                </a>

                <a href="modules/registrar_venda.php" class="action-card success">
                    <div class="action-icon">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                    <div class="action-title">Registrar Venda</div>
                    <div class="action-desc">Registrar nova venda</div>
                </a>

                <a href="modules/fechar_caixa.php" class="action-card warning">
                    <div class="action-icon">
                        <i class="fas fa-door-closed"></i>
                    </div>
                    <div class="action-title">Fechar Caixa</div>
                    <div class="action-desc">Encerrar o dia de trabalho</div>
                </a>

                <a href="modules/relatorios.php" class="action-card danger">
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
                    <a href="modules/registrar_venda.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Nova Venda
                    </a>
                </div>
                <div class="card-body">
                    <table>
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th>Cliente</th>
                                <th>Valor</th>
                                <th>Forma Pagamento</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($ultimas_vendas) > 0): ?>
                                <?php foreach ($ultimas_vendas as $venda): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($venda['data_venda'])) ?></td>
                                        <td><?= htmlspecialchars($venda['cliente'] ?? 'Consumidor') ?></td>
                                        <td>R$ <?= number_format($venda['valor_total'], 2, ',', '.') ?></td>
                                        <td><?= ucfirst($venda['forma_pagamento']) ?></td>
                                        <td>
                                            <button class="btn-primary">
                                                <a href="<?= $is_module_page ? 'detalhes.php' : 'modules/detalhes.php' ?>">
                                                    <i class="fas fa-eye"></i> Detalhes
                                                </a>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center;">Nenhuma venda registrada hoje</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
                        <input type="text" id="saldo-inicial" value="R$ <?= number_format($saldo_inicial, 2, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="valor-vendas">Valor Total de Vendas</label>
                        <input type="text" id="valor-vendas" value="R$ <?= number_format($vendas_hoje['total'], 2, ',', '.') ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="total-caixa">Total em Caixa</label>
                        <input type="text" id="total-caixa" value="R$ <?= number_format($total_caixa, 2, ',', '.') ?>" readonly>
                    </div>

                    <?php if ($status_caixa): ?>
                        <a href="modules/fechar_caixa.php" class="btn-success">
                            <i class="fas fa-check"></i> Fechar Caixa
                        </a>
                    <?php else: ?>
                        <a href="modules/abrir_caixa.php" class="btn-primary">
                            <i class="fas fa-door-open"></i> Abrir Caixa
                        </a>
                    <?php endif; ?>

                    <button class="btn-primary" onclick="window.print()">
                        <i class="fas fa-print"></i> Imprimir Relatório
                    </button>
                </div>
            </div>
        </main>

        <?php include 'includes/footer.php'; ?>
    </div>

    <script src="js/scripts.js"></script>
</body>

</html>